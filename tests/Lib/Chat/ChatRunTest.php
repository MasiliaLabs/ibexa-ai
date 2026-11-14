<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Chat;

use Masilia\IbexaAi\Os\Neuron\OsAgentFactory;
use Masilia\IbexaAi\Chat\Attachment\UserMessageBuilder;
use Masilia\IbexaAi\Chat\ChatRun;
use Masilia\IbexaAi\Chat\Refusal;
use Masilia\IbexaAi\Chat\RunRefused;
use Masilia\IbexaAi\Chat\Thread\AgentThreads;
use Masilia\IbexaAi\Chat\Thread\ThreadAccessDeniedException;
use Masilia\IbexaAi\Os\Audit\RunContext;
use Masilia\Contracts\IbexaAi\Provider\ProviderConfigurationException;
use NeuronAI\Workflow\Streaming\ProtocolEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** One chat run: what is refused before the agent is built, how a run that cannot start is told, how a run that fails on the way ends. */
final class ChatRunTest extends TestCase
{
    private const THREAD = 'thread-0001';

    private OsAgentFactory&MockObject $agents;
    private AgentThreads&MockObject $threads;
    private LoggerInterface&MockObject $logger;

    private function chatRun(): ChatRun
    {
        $this->agents = $this->createMock(OsAgentFactory::class);
        $this->threads = $this->createMock(AgentThreads::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        return new ChatRun($this->agents, $this->threads, $this->createMock(UserMessageBuilder::class), $this->createMock(RunContext::class), $this->logger);
    }

    /** @return array<string, mixed> */
    private function input(string $text = 'Hello', string $thread = self::THREAD): array
    {
        return ['threadId' => $thread, 'runId' => 'run-1', 'messages' => [['id' => 'm1', 'role' => 'user', 'content' => $text]]];
    }

    private function refusedWith(callable $call): RunRefused
    {
        try {
            $call();
        } catch (RunRefused $refused) {
            return $refused;
        }
        self::fail('the request should have been refused');
    }

    /** @param iterable<int, string> $frames @return list<string> */
    private static function all(iterable $frames): array
    {
        return [...$frames];
    }

    public function testAThreadIdThatIsMissingOrMalformedIsRefused(): void
    {
        $chat = $this->chatRun();

        foreach ([['messages' => []], $this->input('Hello', 'short'), $this->input('Hello', '../../etc/passwd')] as $input) {
            $refused = $this->refusedWith(static fn () => $chat->start($input, 14, 'site'));
            self::assertSame(Refusal::Invalid, $refused->reason);
            self::assertSame('Missing or invalid threadId', $refused->getMessage());
        }
    }

    public function testAMessageThatIsTooLongIsRefusedBeforeAnythingIsClaimed(): void
    {
        $chat = $this->chatRun();
        $this->threads->expects(self::never())->method('claim');

        $refused = $this->refusedWith(fn () => $chat->start($this->input(str_repeat('a', ChatRun::MAX_MESSAGE + 1)), 14, 'site'));

        self::assertSame(Refusal::TooLong, $refused->reason);
    }

    public function testAThreadOfSomeoneElseIsRefusedWith403(): void
    {
        $chat = $this->chatRun();
        $this->threads->method('claim')->willThrowException(new ThreadAccessDeniedException('not yours'));
        $this->agents->expects(self::never())->method('create');

        $refused = $this->refusedWith(fn () => $chat->start($this->input(), 14, 'site'));

        self::assertSame(Refusal::Forbidden, $refused->reason);
        self::assertSame('ACCESS_DENIED', $refused->error->code);
    }

    public function testTheThreadIsClaimedForTheUserAndTheSiteaccessWithTheFirstMessage(): void
    {
        $chat = $this->chatRun();
        $this->threads->expects(self::once())->method('claim')->with(self::THREAD, 14, 'fossil_exit', 'What is on the home page?');
        $this->agents->method('create')->willThrowException(new ProviderConfigurationException('no provider'));

        $chat->start($this->input('  What is on the home page?  '), 14, 'fossil_exit');
    }

    public function testARunThatCannotStartIsToldAsAFailedRunNotAnHttpError(): void
    {
        $chat = $this->chatRun();
        $this->agents->method('create')->willThrowException(new ProviderConfigurationException('No AI provider is configured.'));
        $this->logger->expects(self::never())->method('error');

        $run = $chat->start($this->input(), 14, 'site');
        $frames = self::all($run->frames);

        self::assertSame('no', $run->headers['X-Accel-Buffering']);
        self::assertStringContainsString('"type":"RUN_STARTED"', $frames[0]);
        self::assertStringContainsString('"type":"RUN_ERROR"', implode('', $frames));
    }

    public function testAnUnexpectedFailureToStartIsLoggedButToldTheSameWay(): void
    {
        $chat = $this->chatRun();
        $this->agents->method('create')->willThrowException(new \RuntimeException('the database is down'));
        $this->logger->expects(self::once())->method('error');

        $frames = self::all($chat->start($this->input(), 14, 'site')->frames);

        self::assertStringContainsString('"type":"RUN_ERROR"', implode('', $frames));
    }

    /** @param list<ProtocolEvent> $events */
    private function events(array $events, ?\Throwable $thenFail = null): \Generator
    {
        foreach ($events as $event) {
            yield $event;
        }
        if ($thenFail !== null) {
            throw $thenFail;
        }
    }

    public function testARunThatFailsOnTheWayEndsWithAnErrorEvent(): void
    {
        $chat = $this->chatRun();
        $this->logger->expects(self::once())->method('error');

        $frames = self::all($chat->stream($this->events([new ProtocolEvent('TEXT_MESSAGE_CONTENT', ['delta' => 'Hel'])], new \RuntimeException('the model hung up'))));

        self::assertCount(2, $frames);
        self::assertStringContainsString('TEXT_MESSAGE_CONTENT', $frames[0]);
        self::assertStringContainsString('"code":"service_error"', $frames[1]);
        self::assertStringContainsString(ChatRun::GENERIC_ERROR, $frames[1]);
    }

    public function testARunThatAlreadyClosedItselfGetsNoSecondTerminalEvent(): void
    {
        $chat = $this->chatRun();

        foreach (['RUN_ERROR', 'RUN_FINISHED'] as $terminal) {
            $frames = self::all($chat->stream($this->events([new ProtocolEvent($terminal, ['message' => 'x'])], new \RuntimeException('late'))));

            self::assertCount(1, $frames, 'after ' . $terminal);
        }
    }

    public function testARunThatEndsWellIsSentAsItIs(): void
    {
        $chat = $this->chatRun();
        $this->logger->expects(self::never())->method('error');

        $frames = self::all($chat->stream($this->events([new ProtocolEvent('RUN_STARTED', []), new ProtocolEvent('RUN_FINISHED', [])])));

        self::assertCount(2, $frames);
    }
}

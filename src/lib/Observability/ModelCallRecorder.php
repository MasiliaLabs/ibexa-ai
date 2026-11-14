<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Observability;

use Masilia\Contracts\IbexaAi\Observability\RequestLog;
use Masilia\Contracts\IbexaAi\Observability\RequestLogger;
use Masilia\Contracts\IbexaAi\Observability\UsageRecorder;
use Masilia\Contracts\IbexaAi\Provider\ChatConnection;
use NeuronAI\Agent\Observability\InferenceStart;
use NeuronAI\Agent\Observability\InferenceStop;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Workflow\Observability\WorkflowError;
use NeuronAI\Workflow\WorkflowInterface;
use ReflectionClass;
use Throwable;

/**
 * {@see UsageRecorder} on Neuron's own events: an inference starts, it stops with its usage, or the run fails. One call of the
 * model is one record.
 */
final readonly class ModelCallRecorder implements UsageRecorder
{
    public function __construct(private RequestLogger $logger)
    {
    }

    public function attach(WorkflowInterface $workflow, ChatConnection $connection, ?string $siteaccess): void
    {
        $clock = new ModelCallClock();

        $workflow->subscribe(InferenceStart::class, static function () use ($clock): void {
            $clock->start();
        });
        $workflow->subscribe(InferenceStop::class, function (InferenceStop $event) use ($clock, $connection, $siteaccess): void {
            $message = $event->response->message();
            $usage = $message->getUsage();
            $this->record($clock, $connection, $siteaccess, true, null, $usage?->inputTokens, $usage?->outputTokens, $message instanceof AssistantMessage ? $message->stopReason() : null);
        });
        // An error while a call to the model is in flight is that call failing (the run's other errors have no call to count).
        $workflow->subscribe(WorkflowError::class, function (WorkflowError $event) use ($clock, $connection, $siteaccess): void {
            $this->record($clock, $connection, $siteaccess, false, $event->exception, null, null, null);
        });
    }

    private function record(ModelCallClock $clock, ChatConnection $connection, ?string $siteaccess, bool $success, ?Throwable $failure, ?int $tokensIn, ?int $tokensOut, ?string $finishReason): void
    {
        $latency = $clock->stop();
        if ($latency === null) {
            return;
        }
        $this->logger->log(new RequestLog(
            providerIdentifier: $connection->providerIdentifier,
            modelIdentifier: $connection->model,
            siteaccess: $siteaccess,
            success: $success,
            latencyMs: $latency,
            errorCode: $failure !== null ? (new ReflectionClass($failure))->getShortName() : null,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            finishReason: $finishReason,
        ));
    }
}

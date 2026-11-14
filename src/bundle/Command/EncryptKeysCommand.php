<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Command;

use Masilia\IbexaAi\Security\ProviderKeyEncryptor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'masilia:ai:encrypt-keys',
    description: 'Encrypt the provider API keys that are still stored as plain text.',
)]
final class EncryptKeysCommand extends Command
{
    public function __construct(
        private readonly ProviderKeyEncryptor $encryptor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only count what would be encrypted.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $result = $this->encryptor->encryptAll($dryRun);

        $io->definitionList(
            [$dryRun ? 'To encrypt' : 'Encrypted' => (string) $result['encrypted']],
            ['Already encrypted' => (string) $result['alreadyEncrypted']],
            ['Without a key' => (string) $result['withoutKey']],
        );
        $io->success($result['encrypted'] === 0 ? 'Nothing to encrypt.' : ($dryRun ? 'Dry run: nothing was changed.' : 'The keys are encrypted.'));

        return Command::SUCCESS;
    }
}

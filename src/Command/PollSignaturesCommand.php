<?php

declare(strict_types=1);

namespace Mosl\OpenSignBridgeBundle\Command;

use Mosl\OpenSignBridgeBundle\Service\OpenSignPollingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Manual/debug trigger for OpenSignPollingService.
 */
#[AsCommand(
    name: 'opensignb:poll-signatures',
    description: 'Checks OpenSign for documents that have been signed and dispatches DocumentSignedEvent for them.',
)]
class PollSignaturesCommand extends Command
{
    public function __construct(
        private readonly OpenSignPollingService $openSignPollingService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('OpenSign Signature Polling');

        try {
            $completedCount = $this->openSignPollingService->pollPendingDocuments();
            $io->success(sprintf('Found %d newly signed document(s).', $completedCount));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to poll OpenSign for signatures: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}

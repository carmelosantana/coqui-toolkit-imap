<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Toolkits\Imap\Runtime\ImapClientFactory;
use CoquiBot\Toolkits\Imap\Runtime\MessageFormatter;

final readonly class ImapListMessagesTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
        private MessageFormatter $formatter,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_list_messages',
            description: 'List messages in a mailbox folder with optional filtering. Returns a paginated table of messages with UID, sender, subject, date, and flags.',
            parameters: [
                new StringParameter('folder', 'Folder name (default: INBOX)', required: false),
                new NumberParameter('limit', 'Number of messages to return (default: 20, max: 50)', required: false, minimum: 1, maximum: 50),
                new NumberParameter('page', 'Page number for pagination (default: 1)', required: false, minimum: 1),
                new EnumParameter('filter', 'Filter messages by status', values: ['all', 'unseen', 'flagged', 'answered', 'recent'], required: false),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $folderName = $args['folder'] ?? 'INBOX';
            $limit = (int) ($args['limit'] ?? 20);
            $page = (int) ($args['page'] ?? 1);
            $filter = $args['filter'] ?? 'all';

            $mailbox = $this->clientFactory->getMailbox();
            $folder = $mailbox->folders()->findOrFail($folderName);

            $query = $folder->messages()
                ->withHeaders()
                ->withFlags();

            $query = match ($filter) {
                'unseen' => $query->unseen(),
                'flagged' => $query->flagged(),
                'answered' => $query->answered(),
                'recent' => $query->recent(),
                default => $query,
            };

            $paginated = $query->sortByDesc('arrival')->paginate($limit, page: $page);

            $status = $folder->status();
            $total = $status['MESSAGES'] ?? 0;
            $unseen = $status['UNSEEN'] ?? 0;

            $lines = [];
            $lines[] = sprintf(
                '**Folder:** %s | **Total:** %d | **Unseen:** %d | **Page:** %d',
                $folderName,
                $total,
                $unseen,
                $page,
            );
            $lines[] = '';
            $lines[] = $this->formatter->formatMessageListHeader();

            foreach ($paginated as $message) {
                $lines[] = $this->formatter->formatMessageSummary($message);
            }

            if ($paginated->count() === 0) {
                $lines[] = '| — | No messages found | — | — | — |';
            }

            return ToolResult::success(implode("\n", $lines));
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to list messages: ' . $e->getMessage());
        }
    }
}

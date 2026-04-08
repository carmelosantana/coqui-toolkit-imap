<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tool;

use Carbon\Carbon;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Toolkits\Imap\Runtime\ImapClientFactory;
use CoquiBot\Toolkits\Imap\Runtime\MessageFormatter;

final readonly class ImapSearchTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
        private MessageFormatter $formatter,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_search',
            description: 'Search for messages in a folder by subject, sender, date range, or custom criteria. Returns matching messages as a table.',
            parameters: [
                new StringParameter('query', 'Search term to match in subject lines', required: false),
                new StringParameter('folder', 'Folder name (default: INBOX)', required: false),
                new StringParameter('from', 'Filter by sender email address or name', required: false),
                new StringParameter('to', 'Filter by recipient email address', required: false),
                new StringParameter('since', 'Messages since this date (YYYY-MM-DD format)', required: false),
                new StringParameter('before', 'Messages before this date (YYYY-MM-DD format)', required: false),
                new NumberParameter('limit', 'Maximum number of results (default: 20, max: 50)', required: false, minimum: 1, maximum: 50),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $folderName = $args['folder'] ?? 'INBOX';
            $limit = (int) ($args['limit'] ?? 20);

            $mailbox = $this->clientFactory->getMailbox();
            $folder = $mailbox->folders()->findOrFail($folderName);

            $query = $folder->messages()
                ->withHeaders()
                ->withFlags();

            if (!empty($args['query'])) {
                $query = $query->subject($args['query']);
            }

            if (!empty($args['from'])) {
                $query = $query->from($args['from']);
            }

            if (!empty($args['to'])) {
                $query = $query->to($args['to']);
            }

            if (!empty($args['since'])) {
                $query = $query->since(Carbon::parse($args['since']));
            }

            if (!empty($args['before'])) {
                $query = $query->before(Carbon::parse($args['before']));
            }

            $messages = $query->sortByDesc('arrival')->paginate($limit);

            $lines = [];
            $lines[] = sprintf('**Search results in %s** (showing up to %d)', $folderName, $limit);
            $lines[] = '';
            $lines[] = $this->formatter->formatMessageListHeader();

            $count = 0;
            foreach ($messages as $message) {
                $lines[] = $this->formatter->formatMessageSummary($message);
                $count++;
            }

            if ($count === 0) {
                $lines[] = '| — | No messages matched your search | — | — | — |';
            }

            $lines[] = '';
            $lines[] = sprintf('**Found:** %d message(s)', $count);

            return ToolResult::success(implode("\n", $lines));
        } catch (\Throwable $e) {
            return ToolResult::error('Search failed: ' . $e->getMessage());
        }
    }
}

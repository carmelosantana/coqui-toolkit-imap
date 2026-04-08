<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Toolkits\Imap\Runtime\ImapClientFactory;

final readonly class ImapListFoldersTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_list_folders',
            description: 'List all mailbox folders with message counts and unread counts. Use this to discover available folders before reading messages.',
            parameters: [],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $mailbox = $this->clientFactory->getMailbox();
            $folders = $mailbox->folders()->get();

            $lines = [];
            $lines[] = '| Folder | Messages | Unseen |';
            $lines[] = '| --- | --- | --- |';

            foreach ($folders as $folder) {
                $status = $folder->status();
                $messages = $status['MESSAGES'] ?? 0;
                $unseen = $status['UNSEEN'] ?? 0;

                $lines[] = sprintf(
                    '| %s | %d | %d |',
                    $folder->path(),
                    $messages,
                    $unseen,
                );
            }

            return ToolResult::success(implode("\n", $lines));
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to list folders: ' . $e->getMessage());
        }
    }
}

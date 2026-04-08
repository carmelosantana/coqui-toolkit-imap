<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Toolkits\Imap\Runtime\ImapClientFactory;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;

final readonly class ImapMoveTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_move',
            description: 'Move one or more messages to a different folder. Provide UIDs as a comma-separated list. Messages are moved from the source folder to the destination.',
            parameters: [
                new StringParameter('uids', 'Comma-separated message UIDs to move (e.g., "123,456,789")', required: true),
                new StringParameter('source', 'Source folder name (default: INBOX)', required: false),
                new StringParameter('destination', 'Destination folder name (required)', required: true),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $uidList = $this->parseUids($args['uids']);
            $source = $args['source'] ?? 'INBOX';
            $destination = $args['destination'];

            if ($uidList === []) {
                return ToolResult::error('No valid UIDs provided.');
            }

            $mailbox = $this->clientFactory->getMailbox();
            $folder = $mailbox->folders()->findOrFail($source);

            // Verify destination exists
            $mailbox->folders()->findOrFail($destination);

            $moved = 0;
            $failed = [];

            foreach ($uidList as $uid) {
                $message = $folder->messages()->find($uid, ImapFetchIdentifier::Uid);

                if ($message === null) {
                    $failed[] = $uid;
                    continue;
                }

                $message->move($destination);
                $moved++;
            }

            $result = sprintf('Moved %d message(s) from %s to %s.', $moved, $source, $destination);

            if ($failed !== []) {
                $result .= sprintf(' Failed UIDs (not found): %s', implode(', ', $failed));
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to move messages: ' . $e->getMessage());
        }
    }

    /**
     * @return int[]
     */
    private function parseUids(string $uids): array
    {
        return array_values(
            array_filter(
                array_map(
                    fn(string $uid): int => (int) trim($uid),
                    explode(',', $uids),
                ),
                fn(int $uid): bool => $uid > 0,
            ),
        );
    }
}

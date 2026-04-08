<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Toolkits\Imap\Runtime\ImapClientFactory;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;

final readonly class ImapFlagTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_flag',
            description: 'Set or clear flags on one or more messages. Supports marking as read/unread, flagged/unflagged, answered, and draft.',
            parameters: [
                new StringParameter('uids', 'Comma-separated message UIDs (e.g., "123,456,789")', required: true),
                new StringParameter('folder', 'Folder name (default: INBOX)', required: false),
                new EnumParameter('action', 'Whether to set or clear the flag', values: ['set', 'clear'], required: true),
                new EnumParameter('flag', 'The flag to set or clear', values: ['seen', 'flagged', 'answered', 'draft', 'deleted'], required: true),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $uidList = $this->parseUids($args['uids']);
            $folderName = $args['folder'] ?? 'INBOX';
            $action = $args['action'];
            $flag = $args['flag'];

            if ($uidList === []) {
                return ToolResult::error('No valid UIDs provided.');
            }

            $mailbox = $this->clientFactory->getMailbox();
            $folder = $mailbox->folders()->findOrFail($folderName);

            $updated = 0;
            $failed = [];

            foreach ($uidList as $uid) {
                $message = $folder->messages()
                    ->withFlags()
                    ->find($uid, ImapFetchIdentifier::Uid);

                if ($message === null) {
                    $failed[] = $uid;
                    continue;
                }

                $this->applyFlag($message, $flag, $action === 'set');
                $updated++;
            }

            $actionVerb = $action === 'set' ? 'Set' : 'Cleared';
            $result = sprintf('%s "%s" flag on %d message(s) in %s.', $actionVerb, $flag, $updated, $folderName);

            if ($failed !== []) {
                $result .= sprintf(' Failed UIDs (not found): %s', implode(', ', $failed));
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to update flags: ' . $e->getMessage());
        }
    }

    private function applyFlag(mixed $message, string $flag, bool $set): void
    {
        match (true) {
            $flag === 'seen' && $set => $message->markSeen(),
            $flag === 'seen' && !$set => $message->unmarkSeen(),
            $flag === 'flagged' && $set => $message->markFlagged(),
            $flag === 'flagged' && !$set => $message->unmarkFlagged(),
            $flag === 'answered' && $set => $message->markAnswered(),
            $flag === 'answered' && !$set => $message->unmarkAnswered(),
            $flag === 'draft' && $set => $message->markDraft(),
            $flag === 'draft' && !$set => $message->unmarkDraft(),
            $flag === 'deleted' && $set => $message->markDeleted(),
            $flag === 'deleted' && !$set => $message->unmarkDeleted(),
        };
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

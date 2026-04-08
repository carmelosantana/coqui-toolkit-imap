<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\BoolParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Toolkits\Imap\Runtime\ImapClientFactory;
use CoquiBot\Toolkits\Imap\Runtime\MessageFormatter;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;

final readonly class ImapReadMessageTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
        private MessageFormatter $formatter,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_read_message',
            description: 'Read a single email message by UID. Returns full headers, body text, and attachment listing. Use imap_list_messages first to find the UID.',
            parameters: [
                new NumberParameter('uid', 'Message UID (unique identifier)', required: true),
                new StringParameter('folder', 'Folder name (default: INBOX)', required: false),
                new BoolParameter('mark_read', 'Mark the message as read after viewing (default: false)', required: false),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $uid = (int) $args['uid'];
            $folderName = $args['folder'] ?? 'INBOX';
            $markRead = (bool) ($args['mark_read'] ?? false);

            $mailbox = $this->clientFactory->getMailbox();
            $folder = $mailbox->folders()->findOrFail($folderName);

            $message = $folder->messages()
                ->withHeaders()
                ->withFlags()
                ->withBody()
                ->find($uid, ImapFetchIdentifier::Uid);

            if ($message === null) {
                return ToolResult::error(sprintf('Message with UID %d not found in %s.', $uid, $folderName));
            }

            if ($markRead) {
                $message->markSeen();
            }

            $output = $this->formatter->formatMessage($message, includeBody: true);

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to read message: ' . $e->getMessage());
        }
    }
}

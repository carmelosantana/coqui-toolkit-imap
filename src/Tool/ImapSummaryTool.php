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

final readonly class ImapSummaryTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_summary',
            description: 'Generate a summary of messages in a folder. Returns total count, unread count, sender frequency breakdown, and a brief message list. Ideal for daily digests or inbox overviews. Works well as a background task.',
            parameters: [
                new StringParameter('folder', 'Folder name (default: INBOX)', required: false),
                new StringParameter('since', 'Summarize messages since this date (YYYY-MM-DD, default: today)', required: false),
                new NumberParameter('limit', 'Maximum messages to include in summary (default: 50, max: 100)', required: false, minimum: 1, maximum: 100),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $folderName = $args['folder'] ?? 'INBOX';
            $sinceDate = !empty($args['since'])
                ? Carbon::parse($args['since'])
                : Carbon::today();
            $limit = (int) ($args['limit'] ?? 50);

            $mailbox = $this->clientFactory->getMailbox();
            $folder = $mailbox->folders()->findOrFail($folderName);

            $status = $folder->status();
            $totalInFolder = $status['MESSAGES'] ?? 0;
            $unseenInFolder = $status['UNSEEN'] ?? 0;

            $messages = $folder->messages()
                ->withHeaders()
                ->withFlags()
                ->since($sinceDate)
                ->sortByDesc('arrival')
                ->paginate($limit);

            // Build sender frequency map
            $senders = [];
            $unreadCount = 0;
            $messageList = [];

            foreach ($messages as $message) {
                $from = $message->from();
                $senderEmail = $from !== null ? $from->email() : 'unknown';
                $senderName = $from !== null ? ($from->name() ?? $senderEmail) : 'Unknown';

                $senders[$senderEmail] = ($senders[$senderEmail] ?? 0) + 1;

                if (!$message->isSeen()) {
                    $unreadCount++;
                }

                $subject = $message->subject() ?? '(No subject)';
                $date = $message->date();
                $dateStr = $date !== null ? $date->format('M j H:i') : '';
                $flags = [];
                if (!$message->isSeen()) {
                    $flags[] = '●NEW';
                }
                if ($message->isFlagged()) {
                    $flags[] = '⚑';
                }
                if ($message->hasAttachments()) {
                    $flags[] = '📎';
                }

                $flagStr = $flags !== [] ? ' [' . implode(' ', $flags) . ']' : '';

                $messageList[] = sprintf(
                    '- **%s** — %s (%s)%s',
                    $this->truncate($senderName, 25),
                    $this->truncate($subject, 60),
                    $dateStr,
                    $flagStr,
                );
            }

            // Sort senders by frequency
            arsort($senders);
            $topSenders = array_slice($senders, 0, 10, true);

            // Build output
            $lines = [];
            $lines[] = sprintf('# Email Summary: %s', $folderName);
            $lines[] = sprintf('**Period:** Since %s', $sinceDate->format('Y-m-d'));
            $lines[] = '';
            $lines[] = '## Overview';
            $lines[] = sprintf('- **Total in folder:** %d', $totalInFolder);
            $lines[] = sprintf('- **Unread in folder:** %d', $unseenInFolder);
            $lines[] = sprintf('- **Messages in period:** %d', $messages->count());
            $lines[] = sprintf('- **Unread in period:** %d', $unreadCount);
            $lines[] = '';

            if ($topSenders !== []) {
                $lines[] = '## Top Senders';
                $lines[] = '| Sender | Count |';
                $lines[] = '| --- | --- |';
                foreach ($topSenders as $sender => $count) {
                    $lines[] = sprintf('| %s | %d |', $this->truncate((string) $sender, 40), $count);
                }
                $lines[] = '';
            }

            if ($messageList !== []) {
                $lines[] = '## Recent Messages';
                foreach ($messageList as $item) {
                    $lines[] = $item;
                }
            }

            return ToolResult::success(implode("\n", $lines));
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to generate summary: ' . $e->getMessage());
        }
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1) . '…';
    }
}

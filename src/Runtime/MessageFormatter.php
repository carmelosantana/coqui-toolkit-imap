<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Runtime;

use DirectoryTree\ImapEngine\MessageInterface;

final class MessageFormatter
{
    private const int MAX_BODY_LENGTH = 4000;

    public function formatMessage(MessageInterface $message, bool $includeBody = true): string
    {
        $lines = [];

        $lines[] = $this->formatHeaders($message);

        if ($includeBody) {
            $lines[] = '';
            $lines[] = '## Body';
            $lines[] = '';
            $lines[] = $this->formatBody($message);
        }

        if ($message->hasAttachments()) {
            $lines[] = '';
            $lines[] = '## Attachments';
            $lines[] = '';
            $lines[] = $this->formatAttachments($message);
        }

        return implode("\n", $lines);
    }

    public function formatMessageSummary(MessageInterface $message): string
    {
        $from = $message->from();
        $fromStr = $from !== null
            ? (($name = trim((string) $from->name())) !== '' ? sprintf('%s <%s>', $name, $from->email()) : $from->email())
            : 'Unknown';

        $date = $message->date();
        $dateStr = $date !== null ? $date->format('Y-m-d H:i') : 'Unknown';

        $flags = $this->formatFlags($message);
        $subject = $message->subject() ?? '(No subject)';
        $attachments = $message->hasAttachments()
            ? sprintf(' 📎%d', $message->attachmentCount())
            : '';

        return sprintf(
            '| %d | %s | %s | %s | %s%s |',
            $message->uid(),
            $this->truncate($fromStr, 30),
            $this->truncate($subject, 50),
            $dateStr,
            $flags,
            $attachments,
        );
    }

    public function formatMessageListHeader(): string
    {
        return "| UID | From | Subject | Date | Flags |\n| --- | --- | --- | --- | --- |";
    }

    private function formatHeaders(MessageInterface $message): string
    {
        $lines = [];
        $lines[] = '## Headers';
        $lines[] = '';

        $from = $message->from();
        if ($from !== null) {
            $lines[] = sprintf('**From:** %s', $this->formatAddress($from));
        }

        $to = $message->to();
        if ($to !== []) {
            $addresses = array_map(fn($addr) => $this->formatAddress($addr), $to);
            $lines[] = sprintf('**To:** %s', implode(', ', $addresses));
        }

        $cc = $message->cc();
        if ($cc !== []) {
            $addresses = array_map(fn($addr) => $this->formatAddress($addr), $cc);
            $lines[] = sprintf('**Cc:** %s', implode(', ', $addresses));
        }

        $date = $message->date();
        if ($date !== null) {
            $lines[] = sprintf('**Date:** %s', $date->format('Y-m-d H:i:s T'));
        }

        $lines[] = sprintf('**Subject:** %s', $message->subject() ?? '(No subject)');
        $lines[] = sprintf('**UID:** %d', $message->uid());

        $messageId = $message->messageId();
        if ($messageId !== null) {
            $lines[] = sprintf('**Message-ID:** %s', $messageId);
        }

        $flags = $this->formatFlags($message);
        if ($flags !== '') {
            $lines[] = sprintf('**Flags:** %s', $flags);
        }

        return implode("\n", $lines);
    }

    private function formatBody(MessageInterface $message): string
    {
        $text = $message->text();
        if ($text !== '' && $text !== null) {
            return $this->truncateBody($text);
        }

        $html = $message->html();
        if ($html !== '' && $html !== null) {
            $stripped = $this->stripHtml($html);

            return $this->truncateBody($stripped);
        }

        return '*No body content available.*';
    }

    private function formatAttachments(MessageInterface $message): string
    {
        $lines = [];
        $lines[] = '| Filename | Content-Type | Size |';
        $lines[] = '| --- | --- | --- |';

        foreach ($message->attachments() as $attachment) {
            $filename = $attachment->filename() ?? 'unnamed';
            $contentType = $attachment->contentType();
            $lines[] = sprintf('| %s | %s | — |', $filename, $contentType);
        }

        return implode("\n", $lines);
    }

    private function formatAddress(mixed $address): string
    {
        if ($address === null) {
            return 'Unknown';
        }

        $name = $address->name();
        $email = $address->email();

        if ($name !== null && $name !== '') {
            return sprintf('%s <%s>', $name, $email);
        }

        return $email ?? 'Unknown';
    }

    private function formatFlags(MessageInterface $message): string
    {
        $flags = [];

        if ($message->isSeen()) {
            $flags[] = '✓Read';
        }

        if ($message->isFlagged()) {
            $flags[] = '⚑Flagged';
        }

        if ($message->isAnswered()) {
            $flags[] = '↩Answered';
        }

        if ($message->isDraft()) {
            $flags[] = '✎Draft';
        }

        if ($message->isDeleted()) {
            $flags[] = '✗Deleted';
        }

        return implode(' ', $flags);
    }

    private function stripHtml(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\/div>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $text) ?? $text;
        $text = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function truncateBody(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_BODY_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_BODY_LENGTH) . "\n\n... [truncated — message body exceeds 4000 characters]";
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1) . '…';
    }
}

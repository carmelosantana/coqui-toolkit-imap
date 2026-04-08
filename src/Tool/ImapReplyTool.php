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
use CoquiBot\Toolkits\Imap\Runtime\SmtpTransportFactory;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use Symfony\Component\Mime\Email;

final readonly class ImapReplyTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
        private SmtpTransportFactory $smtpFactory,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_reply',
            description: 'Reply to an existing email by UID. Fetches the original message, constructs a reply with proper headers (In-Reply-To, References), quotes the original body, and sends via SMTP. Marks the original as answered.',
            parameters: [
                new NumberParameter('uid', 'UID of the message to reply to', required: true),
                new StringParameter('folder', 'Folder containing the message (default: INBOX)', required: false),
                new StringParameter('body', 'Reply body content', required: true),
                new BoolParameter('reply_all', 'Reply to all recipients (default: false — reply to sender only)', required: false),
                new BoolParameter('html', 'Send reply as HTML (default: false — plain text)', required: false),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $mailer = $this->smtpFactory->createMailer();

            if ($mailer === null) {
                return ToolResult::error(
                    'SMTP is not configured. Set SMTP credentials using the credentials tool.',
                );
            }

            $uid = (int) $args['uid'];
            $folderName = $args['folder'] ?? 'INBOX';
            $replyBody = $args['body'];
            $replyAll = (bool) ($args['reply_all'] ?? false);
            $isHtml = (bool) ($args['html'] ?? false);

            $mailbox = $this->clientFactory->getMailbox();
            $folder = $mailbox->folders()->findOrFail($folderName);

            $original = $folder->messages()
                ->withHeaders()
                ->withFlags()
                ->withBody()
                ->find($uid, ImapFetchIdentifier::Uid);

            if ($original === null) {
                return ToolResult::error(sprintf('Message with UID %d not found in %s.', $uid, $folderName));
            }

            $from = $this->smtpFactory->getSenderAddress();
            $originalFrom = $original->from();
            $originalSubject = $original->subject() ?? '';

            // Build reply subject
            $subject = str_starts_with(strtolower($originalSubject), 're:')
                ? $originalSubject
                : 'Re: ' . $originalSubject;

            $email = (new Email())
                ->from($from)
                ->subject($subject);

            // Set reply-to address (reply to original sender)
            $replyTo = $original->replyTo();
            $replyTarget = $replyTo ?? $originalFrom;

            if ($replyTarget !== null) {
                $email->addTo($replyTarget->email());
            }

            // Reply-all: add original To and CC (excluding ourselves)
            if ($replyAll) {
                foreach ($original->to() as $addr) {
                    if (strtolower($addr->email()) !== strtolower($from)) {
                        $email->addTo($addr->email());
                    }
                }

                foreach ($original->cc() as $addr) {
                    if (strtolower($addr->email()) !== strtolower($from)) {
                        $email->addCc($addr->email());
                    }
                }
            }

            // Set threading headers
            $messageId = $original->messageId();
            if ($messageId !== null) {
                $email->getHeaders()->addTextHeader('In-Reply-To', $messageId);
                $email->getHeaders()->addTextHeader('References', $messageId);
            }

            // Build reply body with quoted original
            $originalBody = $original->text() ?? '';
            $originalDate = $original->date();
            $originalFromStr = $originalFrom !== null ? $originalFrom->email() : 'Unknown';

            $quotedHeader = sprintf(
                "\n\nOn %s, %s wrote:\n",
                $originalDate !== null ? $originalDate->format('D, M j, Y \a\t g:i A') : 'unknown date',
                $originalFromStr,
            );

            if ($isHtml) {
                $quotedOriginal = sprintf(
                    '<br><br><div>On %s, %s wrote:</div><blockquote style="margin:0 0 0 0.8em;border-left:2px solid #ccc;padding-left:1em;">%s</blockquote>',
                    $originalDate !== null ? $originalDate->format('D, M j, Y \a\t g:i A') : 'unknown date',
                    htmlspecialchars($originalFromStr),
                    $original->html() !== '' ? $original->html() : nl2br(htmlspecialchars($originalBody)),
                );
                $email->html($replyBody . $quotedOriginal);
            } else {
                $quotedLines = implode("\n", array_map(
                    fn(string $line): string => '> ' . $line,
                    explode("\n", $originalBody),
                ));
                $email->text($replyBody . $quotedHeader . $quotedLines);
            }

            $mailer->send($email);

            // Mark original as answered
            $original->markAnswered();

            $recipientInfo = $replyTarget !== null ? $replyTarget->email() : 'unknown';
            if ($replyAll) {
                $recipientInfo .= ' (reply-all)';
            }

            return ToolResult::success(sprintf(
                'Reply sent to %s. Subject: "%s". Original message marked as answered.',
                $recipientInfo,
                $subject,
            ));
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to send reply: ' . $e->getMessage());
        }
    }
}

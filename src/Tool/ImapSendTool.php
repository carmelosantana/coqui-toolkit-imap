<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\BoolParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Toolkits\Imap\Runtime\SmtpTransportFactory;
use Symfony\Component\Mime\Email;

final readonly class ImapSendTool
{
    public function __construct(
        private SmtpTransportFactory $smtpFactory,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_send',
            description: 'Send an email via SMTP. Requires SMTP credentials to be configured (falls back to IMAP credentials if SMTP-specific ones are not set).',
            parameters: [
                new StringParameter('to', 'Recipient email address (comma-separated for multiple recipients)', required: true),
                new StringParameter('subject', 'Email subject line', required: true),
                new StringParameter('body', 'Email body content', required: true),
                new StringParameter('cc', 'CC recipients (comma-separated)', required: false),
                new StringParameter('bcc', 'BCC recipients (comma-separated)', required: false),
                new BoolParameter('html', 'Send as HTML email (default: false — sends as plain text)', required: false),
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
                    'SMTP is not configured. Set SMTP credentials (or IMAP credentials which are used as fallback) using the credentials tool: '
                    . 'credentials(action: "set", key: "SMTP_HOST", value: "smtp.example.com")',
                );
            }

            $from = $this->smtpFactory->getSenderAddress();
            $to = $args['to'];
            $subject = $args['subject'];
            $body = $args['body'];
            $isHtml = (bool) ($args['html'] ?? false);

            $email = (new Email())
                ->from($from)
                ->subject($subject);

            // Add recipients
            foreach ($this->parseAddresses($to) as $address) {
                $email->addTo($address);
            }

            // Add CC
            if (!empty($args['cc'])) {
                foreach ($this->parseAddresses($args['cc']) as $address) {
                    $email->addCc($address);
                }
            }

            // Add BCC
            if (!empty($args['bcc'])) {
                foreach ($this->parseAddresses($args['bcc']) as $address) {
                    $email->addBcc($address);
                }
            }

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $mailer->send($email);

            $recipientCount = count($this->parseAddresses($to));

            return ToolResult::success(sprintf(
                'Email sent successfully to %d recipient(s). Subject: "%s"',
                $recipientCount,
                $subject,
            ));
        } catch (\Throwable $e) {
            return ToolResult::error('Failed to send email: ' . $e->getMessage());
        }
    }

    /**
     * @return string[]
     */
    private function parseAddresses(string $addresses): array
    {
        return array_values(
            array_filter(
                array_map('trim', explode(',', $addresses)),
                fn(string $addr): bool => $addr !== '',
            ),
        );
    }
}

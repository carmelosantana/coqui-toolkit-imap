<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Runtime;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

final class SmtpTransportFactory
{
    public function createMailer(): ?Mailer
    {
        $host = $this->resolveSmtpHost();
        $username = $this->resolveSmtpUsername();
        $password = $this->resolveSmtpPassword();

        if ($host === '' || $username === '' || $password === '') {
            return null;
        }

        $port = (int) $this->resolveEnv('SMTP_PORT', '587');
        $encryption = $this->resolveEnv('SMTP_ENCRYPTION', 'tls');

        $scheme = match ($encryption) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            'none' => 'smtp',
            default => 'smtp',
        };

        $dsn = sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            urlencode($username),
            urlencode($password),
            $host,
            $port,
        );

        $transport = Transport::fromDsn($dsn);

        return new Mailer($transport);
    }

    public function isConfigured(): bool
    {
        return $this->resolveSmtpHost() !== ''
            && $this->resolveSmtpUsername() !== ''
            && $this->resolveSmtpPassword() !== '';
    }

    public function getSenderAddress(): string
    {
        return $this->resolveSmtpUsername();
    }

    private function resolveSmtpHost(): string
    {
        $smtp = $this->resolveEnv('SMTP_HOST');

        return $smtp !== '' ? $smtp : $this->resolveEnv('IMAP_HOST');
    }

    private function resolveSmtpUsername(): string
    {
        $smtp = $this->resolveEnv('SMTP_USERNAME');

        return $smtp !== '' ? $smtp : $this->resolveEnv('IMAP_USERNAME');
    }

    private function resolveSmtpPassword(): string
    {
        $smtp = $this->resolveEnv('SMTP_PASSWORD');

        return $smtp !== '' ? $smtp : $this->resolveEnv('IMAP_PASSWORD');
    }

    private function resolveEnv(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return $value !== false ? $value : $default;
    }
}

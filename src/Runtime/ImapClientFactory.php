<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Runtime;

use DirectoryTree\ImapEngine\Mailbox;

final class ImapClientFactory
{
    private ?Mailbox $mailbox = null;

    public function getMailbox(): Mailbox
    {
        if ($this->mailbox !== null && $this->mailbox->connected()) {
            return $this->mailbox;
        }

        $this->mailbox = new Mailbox([
            'host' => $this->resolveEnv('IMAP_HOST'),
            'port' => (int) $this->resolveEnv('IMAP_PORT', '993'),
            'encryption' => $this->resolveEnv('IMAP_ENCRYPTION', 'ssl'),
            'username' => $this->resolveEnv('IMAP_USERNAME'),
            'password' => $this->resolveEnv('IMAP_PASSWORD'),
            'validate_cert' => true,
            'authentication' => 'login',
        ]);

        return $this->mailbox;
    }

    public function disconnect(): void
    {
        if ($this->mailbox !== null) {
            $this->mailbox->disconnect();
            $this->mailbox = null;
        }
    }

    public function isConfigured(): bool
    {
        return $this->resolveEnv('IMAP_HOST') !== ''
            && $this->resolveEnv('IMAP_USERNAME') !== ''
            && $this->resolveEnv('IMAP_PASSWORD') !== '';
    }

    private function resolveEnv(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return $value !== false ? $value : $default;
    }
}

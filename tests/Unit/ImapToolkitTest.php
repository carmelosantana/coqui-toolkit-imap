<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tests\Unit;

use CoquiBot\Toolkits\Imap\ImapToolkit;
use CoquiBot\Toolkits\Imap\Runtime\MessageFormatter;
use CoquiBot\Toolkits\Imap\Runtime\SmtpTransportFactory;

test('toolkit provides expected tools', function () {
    $toolkit = new ImapToolkit();
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(11);

    $names = array_map(fn($tool) => $tool->name(), $tools);
    sort($names);

    expect($names)->toBe([
        'imap_delete',
        'imap_flag',
        'imap_folder_manage',
        'imap_list_folders',
        'imap_list_messages',
        'imap_move',
        'imap_read_message',
        'imap_reply',
        'imap_search',
        'imap_send',
        'imap_summary',
    ]);
});

test('toolkit can be created via fromEnv factory', function () {
    $toolkit = ImapToolkit::fromEnv();

    expect($toolkit)->toBeInstanceOf(ImapToolkit::class);
    expect($toolkit->tools())->toHaveCount(11);
});

test('guidelines contain XML tags', function () {
    $toolkit = new ImapToolkit();
    $guidelines = $toolkit->guidelines();

    expect($guidelines)->toContain('<IMAP-TOOLKIT-GUIDELINES>');
    expect($guidelines)->toContain('</IMAP-TOOLKIT-GUIDELINES>');
});

test('guidelines contain tool reference table', function () {
    $toolkit = new ImapToolkit();
    $guidelines = $toolkit->guidelines();

    expect($guidelines)->toContain('imap_list_folders');
    expect($guidelines)->toContain('imap_list_messages');
    expect($guidelines)->toContain('imap_read_message');
    expect($guidelines)->toContain('imap_search');
    expect($guidelines)->toContain('imap_send');
    expect($guidelines)->toContain('imap_reply');
    expect($guidelines)->toContain('imap_move');
    expect($guidelines)->toContain('imap_flag');
    expect($guidelines)->toContain('imap_delete');
    expect($guidelines)->toContain('imap_folder_manage');
    expect($guidelines)->toContain('imap_summary');
});

test('all tools produce valid function schemas', function () {
    $toolkit = new ImapToolkit();

    foreach ($toolkit->tools() as $tool) {
        $schema = $tool->toFunctionSchema();

        expect($schema)->toHaveKey('type');
        expect($schema['type'])->toBe('function');
        expect($schema)->toHaveKey('function');
        expect($schema['function'])->toHaveKey('name');
        expect($schema['function'])->toHaveKey('description');
        expect($schema['function'])->toHaveKey('parameters');
        expect($schema['function']['parameters'])->toHaveKey('type');
        expect($schema['function']['parameters']['type'])->toBe('object');
    }
});

test('tool names follow snake_case convention with imap_ prefix', function () {
    $toolkit = new ImapToolkit();

    foreach ($toolkit->tools() as $tool) {
        expect($tool->name())->toStartWith('imap_');
        expect($tool->name())->toMatch('/^[a-z_]+$/');
    }
});

test('tool descriptions are not empty', function () {
    $toolkit = new ImapToolkit();

    foreach ($toolkit->tools() as $tool) {
        expect($tool->description())->not->toBeEmpty();
        expect(strlen($tool->description()))->toBeGreaterThan(10);
    }
});

test('message formatter truncates long bodies', function () {
    $formatter = new MessageFormatter();
    $reflection = new \ReflectionClass($formatter);
    $method = $reflection->getMethod('truncateBody');

    $shortText = 'Hello world';
    expect($method->invoke($formatter, $shortText))->toBe($shortText);

    $longText = str_repeat('a', 5000);
    $truncated = $method->invoke($formatter, $longText);
    expect(mb_strlen($truncated))->toBeLessThan(5000);
    expect($truncated)->toContain('truncated');
});

test('message formatter strips HTML correctly', function () {
    $formatter = new MessageFormatter();
    $reflection = new \ReflectionClass($formatter);
    $method = $reflection->getMethod('stripHtml');

    $html = '<p>Hello <strong>World</strong></p><br><div>Footer</div>';
    $text = $method->invoke($formatter, $html);

    expect($text)->toContain('Hello');
    expect($text)->toContain('World');
    expect($text)->toContain('Footer');
    expect($text)->not->toContain('<p>');
    expect($text)->not->toContain('<strong>');
});

test('smtp factory detects unconfigured state', function () {
    // Clear any SMTP env vars for this test
    $originalHost = getenv('SMTP_HOST');
    $originalImapHost = getenv('IMAP_HOST');

    putenv('SMTP_HOST=');
    putenv('IMAP_HOST=');

    $factory = new SmtpTransportFactory();
    expect($factory->isConfigured())->toBeFalse();

    // Restore
    if ($originalHost !== false) {
        putenv('SMTP_HOST=' . $originalHost);
    }
    if ($originalImapHost !== false) {
        putenv('IMAP_HOST=' . $originalImapHost);
    }
});

test('smtp factory falls back to IMAP credentials', function () {
    $originalSmtpHost = getenv('SMTP_HOST');
    $originalSmtpUser = getenv('SMTP_USERNAME');
    $originalImapHost = getenv('IMAP_HOST');
    $originalImapUser = getenv('IMAP_USERNAME');
    $originalImapPass = getenv('IMAP_PASSWORD');

    putenv('SMTP_HOST=');
    putenv('SMTP_USERNAME=');
    putenv('IMAP_HOST=mail.example.com');
    putenv('IMAP_USERNAME=user@example.com');
    putenv('IMAP_PASSWORD=secret');

    $factory = new SmtpTransportFactory();
    expect($factory->isConfigured())->toBeTrue();
    expect($factory->getSenderAddress())->toBe('user@example.com');

    // Restore
    if ($originalSmtpHost !== false) {
        putenv('SMTP_HOST=' . $originalSmtpHost);
    } else {
        putenv('SMTP_HOST');
    }
    if ($originalSmtpUser !== false) {
        putenv('SMTP_USERNAME=' . $originalSmtpUser);
    } else {
        putenv('SMTP_USERNAME');
    }
    if ($originalImapHost !== false) {
        putenv('IMAP_HOST=' . $originalImapHost);
    } else {
        putenv('IMAP_HOST');
    }
    if ($originalImapUser !== false) {
        putenv('IMAP_USERNAME=' . $originalImapUser);
    } else {
        putenv('IMAP_USERNAME');
    }
    if ($originalImapPass !== false) {
        putenv('IMAP_PASSWORD=' . $originalImapPass);
    } else {
        putenv('IMAP_PASSWORD');
    }
});

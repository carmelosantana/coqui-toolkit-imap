<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap;

use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CoquiBot\Toolkits\Imap\Runtime\ImapClientFactory;
use CoquiBot\Toolkits\Imap\Runtime\MessageFormatter;
use CoquiBot\Toolkits\Imap\Runtime\SmtpTransportFactory;
use CoquiBot\Toolkits\Imap\Tool\ImapDeleteTool;
use CoquiBot\Toolkits\Imap\Tool\ImapFlagTool;
use CoquiBot\Toolkits\Imap\Tool\ImapFolderManageTool;
use CoquiBot\Toolkits\Imap\Tool\ImapListFoldersTool;
use CoquiBot\Toolkits\Imap\Tool\ImapListMessagesTool;
use CoquiBot\Toolkits\Imap\Tool\ImapMoveTool;
use CoquiBot\Toolkits\Imap\Tool\ImapReadMessageTool;
use CoquiBot\Toolkits\Imap\Tool\ImapReplyTool;
use CoquiBot\Toolkits\Imap\Tool\ImapSearchTool;
use CoquiBot\Toolkits\Imap\Tool\ImapSendTool;
use CoquiBot\Toolkits\Imap\Tool\ImapSummaryTool;

final class ImapToolkit implements ToolkitInterface
{
    public function __construct(
        private readonly ImapClientFactory $clientFactory = new ImapClientFactory(),
        private readonly SmtpTransportFactory $smtpFactory = new SmtpTransportFactory(),
        private readonly MessageFormatter $formatter = new MessageFormatter(),
    ) {}

    public static function fromEnv(): self
    {
        return new self();
    }

    public function tools(): array
    {
        return [
            (new ImapListFoldersTool($this->clientFactory))->build(),
            (new ImapListMessagesTool($this->clientFactory, $this->formatter))->build(),
            (new ImapReadMessageTool($this->clientFactory, $this->formatter))->build(),
            (new ImapSearchTool($this->clientFactory, $this->formatter))->build(),
            (new ImapMoveTool($this->clientFactory))->build(),
            (new ImapFlagTool($this->clientFactory))->build(),
            (new ImapDeleteTool($this->clientFactory))->build(),
            (new ImapFolderManageTool($this->clientFactory))->build(),
            (new ImapSendTool($this->smtpFactory))->build(),
            (new ImapReplyTool($this->clientFactory, $this->smtpFactory))->build(),
            (new ImapSummaryTool($this->clientFactory))->build(),
        ];
    }

    public function guidelines(): string
    {
        return <<<'GUIDELINES'
        <IMAP-TOOLKIT-GUIDELINES>
        ## Email Management via IMAP

        You have access to a full email management toolkit that can read, search, send, reply to, and organize emails.

        ### Tool Selection Guide

        | Task | Tool | Key Parameters |
        | --- | --- | --- |
        | See available folders | `imap_list_folders` | — |
        | Browse messages in a folder | `imap_list_messages` | folder, filter (unseen/flagged) |
        | Read a specific email | `imap_read_message` | uid (from list results) |
        | Search for emails | `imap_search` | query, from, since, before |
        | Send a new email | `imap_send` | to, subject, body |
        | Reply to an email | `imap_reply` | uid, body, reply_all |
        | Move emails to folder | `imap_move` | uids, destination |
        | Flag/unflag emails | `imap_flag` | uids, flag, action |
        | Delete emails | `imap_delete` | uids |
        | Manage folders | `imap_folder_manage` | action (create/delete/rename) |
        | Get inbox summary | `imap_summary` | folder, since |

        ### Recommended Workflows

        **Checking inbox:**
        1. `imap_list_folders` — see folder structure
        2. `imap_list_messages(filter: "unseen")` — see unread messages
        3. `imap_read_message(uid: X)` — read specific emails

        **Replying to an email:**
        1. `imap_read_message(uid: X)` — read the original
        2. `imap_reply(uid: X, body: "...")` — send the reply

        **Organizing inbox:**
        1. `imap_search(from: "newsletter@...")` — find messages to organize
        2. `imap_move(uids: "1,2,3", destination: "Archive")` — move them
        3. `imap_flag(uids: "4,5", flag: "seen", action: "set")` — mark as read

        **Daily digest (via background task):**
        Use `imap_summary(since: "YYYY-MM-DD")` for a quick overview.

        ### Security Rules

        - **NEVER** include full email credentials in tool results or conversation
        - Truncate long email bodies — only show what the user needs
        - Warn the user about suspicious emails (phishing indicators, unknown senders asking for credentials)
        - When forwarding or replying, confirm the recipient with the user if it looks unusual
        - Do not download or execute email attachments without explicit user approval

        ### Important Notes

        - UIDs are unique per folder — always specify the folder when working with UIDs
        - Use `imap_list_messages` or `imap_search` to discover UIDs before reading/moving/deleting
        - SMTP sending requires either SMTP-specific credentials or falls back to IMAP credentials
        - The `imap_summary` tool is optimized for background tasks and daily digests
        - IMAP date filtering (since/before) works at the date level, not time level
        </IMAP-TOOLKIT-GUIDELINES>
        GUIDELINES;
    }
}

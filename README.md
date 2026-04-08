# Coqui Toolkit: IMAP

IMAP email management toolkit for Coqui — read, search, send, reply, and organize emails directly from the terminal.

## Features

- **Read & browse** — list folders, paginate messages, read full emails
- **Search** — find emails by subject, sender, recipient, date range
- **Send & reply** — compose new emails and replies with proper threading headers
- **Organize** — move messages between folders, flag/unflag, delete
- **Folder management** — create, rename, and delete IMAP folders
- **Inbox summaries** — background-task-friendly digest of recent activity
- **Credential management** — integrates with Coqui's credential guard system
- **Safety gating** — destructive operations require user confirmation

## Installation

```bash
composer require coquibot/coqui-toolkit-imap
```

Coqui auto-discovers the toolkit on next boot — no configuration code needed.

## Credential Setup

The toolkit requires three credentials to connect. Coqui will prompt you automatically when you first use an IMAP tool.

### Required Credentials

| Credential | Description |
|-----------|-------------|
| `IMAP_HOST` | IMAP server hostname |
| `IMAP_USERNAME` | Email address / username |
| `IMAP_PASSWORD` | Password or app-specific password |

### Optional Credentials

| Credential | Default | Description |
|-----------|---------|-------------|
| `IMAP_PORT` | `993` | IMAP server port |
| `IMAP_ENCRYPTION` | `ssl` | Encryption: `ssl` or `tls` |
| `SMTP_HOST` | Same as `IMAP_HOST` | SMTP server hostname |
| `SMTP_PORT` | `587` | SMTP server port |
| `SMTP_USERNAME` | Same as `IMAP_USERNAME` | SMTP username |
| `SMTP_PASSWORD` | Same as `IMAP_PASSWORD` | SMTP password |
| `SMTP_ENCRYPTION` | `tls` | SMTP encryption: `tls` or `ssl` |

### Provider Setup

#### Gmail

1. Enable 2-Step Verification at [myaccount.google.com/security](https://myaccount.google.com/security)
2. Generate an App Password at [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)
3. Set credentials:

```
IMAP_HOST=imap.gmail.com
IMAP_USERNAME=you@gmail.com
IMAP_PASSWORD=<app-password>
```

Gmail uses the same credentials for SMTP — no separate SMTP config needed.

#### Microsoft 365 / Outlook

```
IMAP_HOST=outlook.office365.com
IMAP_USERNAME=you@outlook.com
IMAP_PASSWORD=<your-password>
SMTP_HOST=smtp.office365.com
```

> **Note:** Microsoft may require OAuth2 for some accounts. App passwords work for accounts with basic auth enabled.

#### Generic IMAP Server

```
IMAP_HOST=mail.example.com
IMAP_USERNAME=user@example.com
IMAP_PASSWORD=secret
SMTP_HOST=smtp.example.com
```

## Tools Reference

| Tool | Description | Gated |
|------|-------------|-------|
| `imap_list_folders` | List all mailbox folders with message counts | No |
| `imap_list_messages` | Browse messages in a folder with filters | No |
| `imap_read_message` | Read a specific email by UID | No |
| `imap_search` | Search emails by subject, sender, date | No |
| `imap_move` | Move messages to another folder | **Yes** |
| `imap_flag` | Set or clear flags (seen, flagged, etc.) | No |
| `imap_delete` | Delete messages | **Yes** |
| `imap_folder_manage` | Create, rename, or delete folders | delete: **Yes** |
| `imap_send` | Send a new email via SMTP | **Yes** |
| `imap_reply` | Reply to an email with threading | **Yes** |
| `imap_summary` | Get inbox activity summary | No |

Gated tools require user confirmation before execution (unless `--auto-approve` is enabled).

## Example Workflows

### Check unread emails

> "Check my inbox for unread emails"

The agent will:
1. List folders to find the inbox
2. Fetch unread messages
3. Present a summary of new emails

### Search and organize

> "Find all emails from newsletters@example.com and move them to a Newsletters folder"

The agent will:
1. Search for matching emails
2. Create the Newsletters folder if needed
3. Move matched messages (with your confirmation)

### Send a reply

> "Reply to the last email from Alice saying I'll be there at 3pm"

The agent will:
1. Search for Alice's email
2. Read it for context
3. Compose and send a reply with proper threading headers

### Daily digest

> "Give me a summary of emails from the last 3 days"

The agent will use `imap_summary` with a date filter to generate a digest showing unread counts, top senders, and recent messages.

## Dependencies

| Package | Purpose |
|---------|---------|
| [directorytree/imapengine](https://github.com/DirectoryTree/ImapEngine) | IMAP protocol client (no ext-imap required) |
| [symfony/mailer](https://github.com/symfony/mailer) | SMTP email sending |
| [symfony/mime](https://github.com/symfony/mime) | Email message construction |

## Development

### Run tests

```bash
composer install
./vendor/bin/pest
```

### Project structure

```
src/
├── ImapToolkit.php              # Entry point (ToolkitInterface)
├── Runtime/
│   ├── ImapClientFactory.php    # Lazy IMAP connection management
│   ├── MessageFormatter.php     # LLM-friendly email formatting
│   └── SmtpTransportFactory.php # SMTP transport with IMAP fallback
└── Tool/
    ├── ImapDeleteTool.php
    ├── ImapFlagTool.php
    ├── ImapFolderManageTool.php
    ├── ImapListFoldersTool.php
    ├── ImapListMessagesTool.php
    ├── ImapMoveTool.php
    ├── ImapReadMessageTool.php
    ├── ImapReplyTool.php
    ├── ImapSearchTool.php
    ├── ImapSendTool.php
    └── ImapSummaryTool.php
tests/
└── Unit/
    └── ImapToolkitTest.php
```

## License

MIT

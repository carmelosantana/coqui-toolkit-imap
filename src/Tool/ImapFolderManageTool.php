<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Imap\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Toolkits\Imap\Runtime\ImapClientFactory;

final readonly class ImapFolderManageTool
{
    public function __construct(
        private ImapClientFactory $clientFactory,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'imap_folder_manage',
            description: 'Create, delete, or rename mailbox folders. Use imap_list_folders first to see existing folders.',
            parameters: [
                new EnumParameter('action', 'The operation to perform', values: ['create', 'delete', 'rename'], required: true),
                new StringParameter('name', 'Folder name (for create/delete) or current name (for rename)', required: true),
                new StringParameter('new_name', 'New folder name (required for rename action only)', required: false),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    private function execute(array $args): ToolResult
    {
        try {
            $action = $args['action'];
            $name = $args['name'];
            $newName = $args['new_name'] ?? '';

            $mailbox = $this->clientFactory->getMailbox();

            return match ($action) {
                'create' => $this->createFolder($mailbox, $name),
                'delete' => $this->deleteFolder($mailbox, $name),
                'rename' => $this->renameFolder($mailbox, $name, $newName),
                default => ToolResult::error(sprintf('Unknown action: %s', $action)),
            };
        } catch (\Throwable $e) {
            return ToolResult::error('Folder operation failed: ' . $e->getMessage());
        }
    }

    private function createFolder(mixed $mailbox, string $name): ToolResult
    {
        $mailbox->folders()->create($name);

        return ToolResult::success(sprintf('Folder "%s" created successfully.', $name));
    }

    private function deleteFolder(mixed $mailbox, string $name): ToolResult
    {
        $folder = $mailbox->folders()->findOrFail($name);
        $folder->delete();

        return ToolResult::success(sprintf('Folder "%s" deleted successfully.', $name));
    }

    private function renameFolder(mixed $mailbox, string $name, string $newName): ToolResult
    {
        if ($newName === '') {
            return ToolResult::error('The new_name parameter is required for rename operations.');
        }

        $folder = $mailbox->folders()->findOrFail($name);
        $folder->move($newName);

        return ToolResult::success(sprintf('Folder "%s" renamed to "%s" successfully.', $name, $newName));
    }
}

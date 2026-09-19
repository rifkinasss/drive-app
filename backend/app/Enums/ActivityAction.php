<?php

namespace App\Enums;

enum ActivityAction: string
{
    case FileUploaded = 'file.uploaded';
    case FileRenamed = 'file.renamed';
    case FileMoved = 'file.moved';
    case FileStarred = 'file.starred';
    case FileUnstarred = 'file.unstarred';
    case FileDownloaded = 'file.downloaded';
    case FileTrashed = 'file.trashed';
    case FileRestored = 'file.restored';
    case FileDeleted = 'file.deleted';
    case FolderCreated = 'folder.created';
    case FolderRenamed = 'folder.renamed';
    case FolderMoved = 'folder.moved';
    case FolderStarred = 'folder.starred';
    case FolderUnstarred = 'folder.unstarred';
    case FolderTrashed = 'folder.trashed';
    case FolderRestored = 'folder.restored';
    case FolderDeleted = 'folder.deleted';
    case TrashEmptied = 'trash.emptied';
    case ShareCreated = 'share.created';
    case ShareReceived = 'share.received';
    case SharePermissionUpdated = 'share.permission_updated';
    case ShareRevoked = 'share.revoked';
    case PublicLinkEnabled = 'public_link.enabled';
    case PublicLinkDisabled = 'public_link.disabled';
    case PublicLinkRegenerated = 'public_link.regenerated';
}

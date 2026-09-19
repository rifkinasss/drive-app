export type CloudItemKind = "file" | "folder";
export type CloudFileType =
  | "document"
  | "image"
  | "video"
  | "archive"
  | "code"
  | "design"
  | "other";

export interface CloudItem {
  id: string;
  ownerId: string;
  name: string;
  kind: CloudItemKind;
  fileType: CloudFileType;
  mimeType: string;
  extension: string;
  size: number;
  parentId: string | null;
  path: string;
  createdAt: string;
  updatedAt: string;
  accessedAt: string;
  starred: boolean;
  deletedAt: string | null;
  originalParentId: string | null;
  thumbnail?: string;
}

export type SharePermission = "viewer" | "editor";

export interface InternalShare {
  id: string;
  itemId: string;
  ownerId: string;
  recipientUserId: string;
  permission: SharePermission;
  createdAt: string;
  itemType?: CloudItemKind;
  updatedAt?: string;
  sharedItem?: CloudItem;
  recipientName?: string;
  ownerName?: string;
}

export interface PublicShareLink {
  id: string;
  itemId: string;
  itemType: CloudItemKind;
  ownerId: string;
  token: string;
  enabled: boolean;
  permission: "viewer";
  createdAt: string;
  updatedAt?: string;
  sharedItem?: CloudItem;
}

export interface PublicSharedItem {
  id?: string;
  name: string;
  kind: CloudItemKind;
  fileType: CloudFileType;
  mimeType: string;
  extension: string;
  size: number;
  modifiedAt: string;
  thumbnail?: string;
  children: PublicSharedItem[];
}

export type PublicShareResolution =
  | { status: "active"; link: PublicShareLink; item: PublicSharedItem; ownerDisplayName: string }
  | { status: "disabled" | "expired" | "unavailable" };

export type CloudFile = CloudItem & { kind: "file" };
export type CloudFolder = CloudItem & { kind: "folder" };
export type RecentActivityType = "opened" | "modified" | "uploaded" | "moved";
export interface RecentRecord {
  itemId: string;
  activityType: RecentActivityType;
  activityAt: string;
}

export interface StorageSummary {
  total: number;
  used: number;
  categories: Record<string, number>;
}

export type UploadTaskStatus =
  | "queued"
  | "uploading"
  | "completed"
  | "failed"
  | "cancelled";
export interface UploadTask {
  id: string;
  name: string;
  size: number;
  mimeType: string;
  progress: number;
  status: UploadTaskStatus;
  error?: string;
  file: File;
  targetFolderId: string | null;
  createdAt: string;
  conflict?: boolean;
}

export interface CloudActivity {
  id: string;
  action:
    | "uploaded"
    | "opened"
    | "created"
    | "renamed"
    | "moved"
    | "copied"
    | "downloaded"
    | "deleted"
    | "restored"
    | "starred"
    | "unstarred"
    | "permanently-deleted"
    | "emptied-trash"
    | "shared"
    | "sharing-stopped";
  itemName: string;
  itemId?: string;
  location: string;
  timestamp: string;
  itemType?: CloudItemKind;
  previousName?: string;
  targetLocation?: string;
  metadata?: string;
}

export interface BreadcrumbItem {
  id: string | null;
  name: string;
}

export interface BrowserResponse {
  currentFolder: { id: string; name: string; parentId: string | null; createdAt: string; updatedAt: string; trashedAt: string | null } | null;
  breadcrumb: BreadcrumbItem[];
  folders: Array<{ id: string; name: string; parentId: string | null; createdAt: string; updatedAt: string; trashedAt: string | null }>;
  files: Array<{ id: string; name: string; folderId: string | null; extension: string | null; mimeType: string; sizeBytes: number; starred: boolean; trashedAt: string | null; createdAt: string; updatedAt: string }>;
  meta: Record<string, unknown>;
}

export interface StorageApiSummary {
  quotaBytes: number;
  usedBytes: number;
  availableBytes: number;
  trashBytes: number;
  usagePercentage: number;
  categories: Record<string, number>;
  largestFiles: CloudItem[];
}

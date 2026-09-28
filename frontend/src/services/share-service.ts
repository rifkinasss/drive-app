import { api, apiBlob, apiDownload } from "@/lib/api/client";
import type {
  CloudItem,
  InternalShare,
  PublicShareLink,
  SharePermission,
} from "@/types/cloud";

type ShareDto = {
  id: string;
  permission: SharePermission | "owner";
  type: string;
  user: { id: string | number; name: string; email: string };
  createdAt: string;
  updatedAt: string;
};
type ListDto = {
  type: "file" | "folder";
  id: string;
  name: string;
  owner: { id: number; name: string };
  permission: SharePermission;
  sharedAt: string;
  mimeType?: string | null;
  sizeBytes?: number | null;
  recipientCount?: number;
  recipients?: Array<{ id: number; name: string; permission: SharePermission }>;
  publicLinkEnabled?: boolean;
};
type PublicLinkDto = {
  type: "file" | "folder";
  id: string;
  name: string;
  enabled: boolean;
  permission: "viewer";
  url: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  expiresAt?: string | null;
  passwordProtected?: boolean;
  allowDownload?: boolean;
};
const itemEndpoint = (item: Pick<CloudItem, "id" | "kind">, suffix: string) =>
  `/api/${item.kind === "folder" ? "folders" : "files"}/${encodeURIComponent(item.id)}${suffix}`;
function sharedItem(row: ListDto): CloudItem {
  const name = row.name;
  const mimeType = row.mimeType ?? "";
  const extension = name.includes(".")
    ? name.split(".").pop()!.toLowerCase()
    : "";
  const fileType: CloudItem["fileType"] =
    row.type === "folder"
      ? "other"
      : mimeType.startsWith("image/")
        ? "image"
        : mimeType.startsWith("video/")
          ? "video"
          : mimeType.includes("zip")
            ? "archive"
            : ["js", "ts", "tsx", "jsx", "json", "css", "sql"].includes(
                  extension,
                )
              ? "code"
              : mimeType.startsWith("text/") || mimeType.includes("pdf")
                ? "document"
                : "other";
  return {
    id: row.id,
    ownerId: String(row.owner.id),
    name,
    kind: row.type,
    fileType,
    mimeType,
    extension,
    size: Number(row.sizeBytes ?? 0),
    parentId: null,
    path: "Shared",
    createdAt: row.sharedAt,
    updatedAt: row.sharedAt,
    accessedAt: row.sharedAt,
    starred: false,
    deletedAt: null,
    originalParentId: null,
  };
}

export const shareService = {
  async getFileRequests() {
    return (
      await api.get<{
        items: Array<{
          id: string;
          title: string;
          folderName?: string;
          enabled: boolean;
          expiresAt: string | null;
          url: string | null;
        }>;
      }>("/api/file-requests")
    ).items;
  },
  async createFileRequest(input: {
    title: string;
    folderId: string;
    expiration: "never" | "24h" | "7d" | "30d";
  }) {
    return api.post<{
      id: string;
      title: string;
      folderName?: string;
      enabled: boolean;
      expiresAt: string | null;
      url: string;
    }>("/api/file-requests", input);
  },
  async updateFileRequest(id: string, input: { enabled: boolean }) {
    return api.patch(`/api/file-requests/${encodeURIComponent(id)}`, input);
  },
  async deleteFileRequest(id: string) {
    await api.delete(`/api/file-requests/${encodeURIComponent(id)}`);
  },
  async getItemShares(
    item: CloudItem,
    ownerId: string,
  ): Promise<InternalShare[]> {
    const data = await api.get<ShareDto[]>(itemEndpoint(item, "/shares"));
    return data
      .filter((share) => share.type !== "owner")
      .map((share) => ({
        id: share.id,
        itemId: item.id,
        itemType: item.kind,
        ownerId,
        recipientUserId: String(share.user.id),
        permission: share.permission as SharePermission,
        createdAt: share.createdAt,
        updatedAt: share.updatedAt,
      }));
  },
  async searchUsers(
    query: string,
  ): Promise<Array<{ id: string; name: string; email: string }>> {
    if (query.trim().length < 2) return [];
    const response = await api.get<{
      items: Array<{ id: number; name: string; email: string }>;
    }>(`/api/users/search?q=${encodeURIComponent(query.trim())}`);
    return response.items.map((user) => ({ ...user, id: String(user.id) }));
  },
  async shareWithUser(
    item: CloudItem,
    recipientId: string,
    permission: SharePermission,
  ): Promise<void> {
    await api.post(itemEndpoint(item, "/shares"), {
      recipientId: Number(recipientId),
      permission,
    });
  },
  async updatePermission(
    id: string,
    permission: SharePermission,
  ): Promise<void> {
    await api.patch(`/api/shares/${encodeURIComponent(id)}`, { permission });
  },
  async getSharedWithMe(): Promise<InternalShare[]> {
    return (
      await api.get<{ items: ListDto[] }>("/api/shared/with-me?limit=100")
    ).items.map((row) => ({
      id: row.id,
      itemId: row.id,
      itemType: row.type,
      ownerId: String(row.owner.id),
      ownerName: row.owner.name,
      recipientUserId: "",
      permission: row.permission,
      createdAt: row.sharedAt,
      sharedItem: sharedItem(row),
    }));
  },
  async getSharedByMe(): Promise<InternalShare[]> {
    return (
      await api.get<{ items: ListDto[] }>("/api/shared/by-me?limit=100")
    ).items.flatMap((row) =>
      (row.recipients ?? []).map((recipient) => ({
        id: `item:${row.id}`,
        itemId: row.id,
        itemType: row.type,
        ownerId: String(row.owner.id),
        ownerName: row.owner.name,
        recipientUserId: String(recipient.id),
        recipientName: recipient.name,
        permission: recipient.permission,
        createdAt: row.sharedAt,
        sharedItem: sharedItem(row),
      })),
    );
  },
  async getPublicLinks(
    userOrStatus: string = "all",
    maybeStatus?: "active" | "disabled" | "all",
  ): Promise<Array<PublicShareLink & { url: string | null }>> {
    const status =
      maybeStatus ??
      (userOrStatus === "active" || userOrStatus === "disabled"
        ? userOrStatus
        : "all");
    const response = await api.get<{ items: PublicLinkDto[] }>(
      `/api/shared/links?status=${status}`,
    );
    return response.items.map((link) => ({
      id: link.id,
      itemId: link.id,
      itemType: link.type,
      ownerId: "",
      token: "",
      url: link.url,
      enabled: link.enabled,
      permission: "viewer",
      createdAt: link.createdAt ?? "",
      updatedAt: link.updatedAt ?? undefined,
      expiresAt: link.expiresAt ?? null,
      passwordProtected: link.passwordProtected ?? false,
      allowDownload: link.allowDownload ?? true,
      sharedItem: sharedItem({
        ...link,
        owner: { id: 0, name: "" },
        permission: "viewer",
        sharedAt: link.createdAt ?? "",
        mimeType: null,
        sizeBytes: 0,
      }),
    }));
  },
  async getPublicLink(
    item: CloudItem,
  ): Promise<PublicShareLink & { url: string | null }> {
    const link = await api.get<PublicLinkDto>(
      itemEndpoint(item, "/public-link"),
    );
    return {
      id: link.id,
      itemId: item.id,
      itemType: item.kind,
      ownerId: "",
      token: "",
      url: link.url,
      enabled: link.enabled,
      permission: "viewer",
      createdAt: link.createdAt ?? "",
      updatedAt: link.updatedAt ?? undefined,
      expiresAt: link.expiresAt ?? null,
      passwordProtected: link.passwordProtected ?? false,
      allowDownload: link.allowDownload ?? true,
    };
  },
  async enablePublicLink(
    item: CloudItem,
  ): Promise<PublicShareLink & { url: string | null }> {
    const link = await api.post<PublicLinkDto>(
      itemEndpoint(item, "/public-link"),
    );
    return {
      id: link.id,
      itemId: item.id,
      itemType: item.kind,
      ownerId: "",
      token: "",
      url: link.url,
      enabled: link.enabled,
      permission: "viewer",
      createdAt: link.createdAt ?? "",
      updatedAt: link.updatedAt ?? undefined,
      expiresAt: link.expiresAt ?? null,
      passwordProtected: link.passwordProtected ?? false,
      allowDownload: link.allowDownload ?? true,
    };
  },
  async disablePublicLink(item: CloudItem | string): Promise<void> {
    await api.delete(
      typeof item === "string"
        ? `/api/files/${encodeURIComponent(item)}/public-link`
        : itemEndpoint(item, "/public-link"),
    );
  },
  async removeRecipient(id: string): Promise<void> {
    await api.delete(`/api/shares/${encodeURIComponent(id)}`);
  },
  async regeneratePublicLink(
    item: CloudItem | string,
  ): Promise<PublicShareLink & { url: string | null }> {
    const target =
      typeof item === "string" ? { id: item, kind: "file" as const } : item;
    const link = await api.post<PublicLinkDto>(
      itemEndpoint(target, "/public-link/regenerate"),
    );
    return {
      id: link.id,
      itemId: target.id,
      itemType: target.kind,
      ownerId: "",
      token: "",
      url: link.url,
      enabled: link.enabled,
      permission: "viewer",
      createdAt: link.createdAt ?? "",
      updatedAt: link.updatedAt ?? undefined,
      expiresAt: link.expiresAt ?? null,
      passwordProtected: link.passwordProtected ?? false,
      allowDownload: link.allowDownload ?? true,
    };
  },
  async updatePublicLink(
    item: CloudItem,
    options: {
      expiration: "never" | "24h" | "7d" | "30d" | "custom";
      expiresAt?: string;
      password?: string;
      allowDownload: boolean;
    },
  ): Promise<PublicShareLink & { url: string | null }> {
    const link = await api.patch<PublicLinkDto>(
      itemEndpoint(item, "/public-link"),
      options,
    );
    return {
      id: link.id,
      itemId: item.id,
      itemType: item.kind,
      ownerId: "",
      token: "",
      url: link.url,
      enabled: link.enabled,
      permission: "viewer",
      createdAt: link.createdAt ?? "",
      updatedAt: link.updatedAt ?? undefined,
      expiresAt: link.expiresAt ?? null,
      passwordProtected: link.passwordProtected ?? false,
      allowDownload: link.allowDownload ?? true,
    };
  },
  async resolvePublicShare(token: string, password?: string) {
    return api.get<{
      type: "file" | "folder";
      name: string;
      mimeType: string | null;
      sizeBytes: number | null;
      modifiedAt: string;
      ownerDisplayName: string;
      permission: "viewer";
      allowDownload: boolean;
    }>(`/api/public/shares/${encodeURIComponent(token)}`, {
      credentials: "omit",
      headers: password ? { "X-Share-Password": password } : undefined,
    });
  },
  async browsePublicShare(token: string, folderId?: string, password?: string) {
    const params = folderId ? `?folderId=${encodeURIComponent(folderId)}` : "";
    return api.get<{
      sharedRoot: { id: string; name: string };
      currentFolder: { id: string; name: string; parentId: string | null };
      breadcrumb: Array<{ id: string; name: string }>;
      folders: Array<{
        id: string;
        name: string;
        parentId: string;
        updatedAt: string;
      }>;
      files: Array<{
        id: string;
        name: string;
        mimeType: string;
        sizeBytes: number;
        updatedAt: string;
      }>;
      permission: "viewer";
      allowDownload: boolean;
    }>(`/api/public/shares/${encodeURIComponent(token)}/browser${params}`, {
      credentials: "omit",
      headers: password ? { "X-Share-Password": password } : undefined,
    });
  },
  async publicPreview(token: string, fileId?: string, password?: string) {
    return apiBlob(
      `/api/public/shares/${encodeURIComponent(token)}${fileId ? `/files/${encodeURIComponent(fileId)}/preview` : "/preview"}`,
      true,
      password ? { "X-Share-Password": password } : undefined,
    );
  },
  async publicDownload(
    token: string,
    filename: string,
    fileId?: string,
    password?: string,
  ) {
    return apiDownload(
      `/api/public/shares/${encodeURIComponent(token)}${fileId ? `/files/${encodeURIComponent(fileId)}/download` : "/download"}`,
      filename,
      true,
      password ? { "X-Share-Password": password } : undefined,
    );
  },
};

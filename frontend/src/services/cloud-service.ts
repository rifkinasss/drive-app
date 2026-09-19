import { api, apiBlob, apiBaseUrl, ApiError, csrfCookie } from "@/lib/api/client";
import type { BrowserResponse, CloudActivity, CloudFileType, CloudItem, StorageApiSummary } from "@/types/cloud";

type ApiItem = {
  id: string;
  type: "file" | "folder";
  name: string;
  extension?: string | null;
  mimeType?: string | null;
  sizeBytes?: number | null;
  starred?: boolean;
  folderId?: string | null;
  location?: { folderId: string | null; folderName: string } | null;
  createdAt: string;
  updatedAt: string;
};

function fileType(mimeType: string, extension: string): CloudFileType {
  if (mimeType.startsWith("image/")) return "image";
  if (mimeType.startsWith("video/")) return "video";
  if (/zip|compressed|archive/.test(mimeType)) return "archive";
  if (/javascript|typescript|json|sql|html|css|text\/x-/.test(mimeType) || /^(ts|tsx|js|jsx|json|sql|css|html|yml|yaml|py|php)$/.test(extension)) return "code";
  return mimeType ? "document" : "other";
}

function toCloudItem(item: ApiItem, ownerId: string, parentId: string | null = item.folderId ?? null): CloudItem {
  const kind = item.type;
  const mimeType = kind === "folder" ? "inode/directory" : item.mimeType ?? "application/octet-stream";
  const extension = item.extension ?? "";
  const path = item.location?.folderName ? `My Files / ${item.location.folderName}` : "My Files";
  return {
    id: item.id,
    ownerId,
    name: item.name,
    kind,
    fileType: kind === "folder" ? "other" : fileType(mimeType, extension),
    mimeType,
    extension,
    size: Number(item.sizeBytes ?? 0),
    parentId,
    path,
    createdAt: item.createdAt,
    updatedAt: item.updatedAt,
    accessedAt: item.updatedAt,
    starred: Boolean(item.starred),
    deletedAt: null,
    originalParentId: parentId,
  };
}

function mapBrowser(data: BrowserResponse, ownerId: string): CloudItem[] {
  const ancestors = data.breadcrumb.map((part, index) => ({
    id: part.id ?? "",
    name: part.name,
    parentId: index > 0 ? data.breadcrumb[index - 1]?.id ?? null : null,
    createdAt: "",
    updatedAt: data.currentFolder?.updatedAt ?? "",
    trashedAt: null,
  })).filter((part) => part.id);
  const currentFolder = data.currentFolder ? [{ ...data.currentFolder, type: "folder" as const, location: null }] : [];
  return [
    ...ancestors.map((folder) => toCloudItem({ ...folder, type: "folder", location: null }, ownerId, folder.parentId)),
    ...currentFolder.map((folder) => toCloudItem(folder, ownerId, folder.parentId)),
    ...data.folders.map((folder) => toCloudItem({ ...folder, type: "folder", location: data.breadcrumb.at(-1) ? { folderId: data.currentFolder?.id ?? null, folderName: data.breadcrumb.at(-1)!.name } : null }, ownerId, folder.parentId)),
    ...data.files.map((file) => toCloudItem({ ...file, type: "file", location: data.breadcrumb.at(-1) ? { folderId: data.currentFolder?.id ?? null, folderName: data.breadcrumb.at(-1)!.name } : null }, ownerId, file.folderId)),
  ];
}

function mapCollection(items: ApiItem[], ownerId: string): CloudItem[] {
  return items.map((item) => toCloudItem(item, ownerId));
}

function endpoint(item: CloudItem, suffix = ""): string {
  return `/api/${item.kind === "folder" ? "folders" : "files"}/${encodeURIComponent(item.id)}${suffix}`;
}

function cookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const entry = document.cookie.split("; ").find((part) => part.startsWith(`${name}=`));
  return entry ? decodeURIComponent(entry.slice(name.length + 1)) : undefined;
}

async function uploadRequest(file: File, folderId: string | null, strategy: "ask" | "keep_both" | "replace", signal: AbortSignal | undefined, onProgress?: (progress: number) => void): Promise<ApiItem> {
  await csrfCookie();
  const body = new FormData();
  body.append("file", file);
  if (folderId) body.append("folderId", folderId);
  body.append("conflictStrategy", strategy);
  return new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();
    request.open("POST", `${apiBaseUrl()}/api/files/upload`);
    request.withCredentials = true;
    request.setRequestHeader("Accept", "application/json");
    const csrf = cookie("XSRF-TOKEN");
    if (csrf) request.setRequestHeader("X-XSRF-TOKEN", csrf);
    request.upload.onprogress = (event) => {
      if (event.lengthComputable) onProgress?.(Math.floor((event.loaded / event.total) * 100));
    };
    request.onerror = () => reject(new Error("Network error while uploading the file."));
    request.onabort = () => reject(new DOMException("Upload cancelled", "AbortError"));
    request.onload = () => {
      let payload: { data?: ApiItem; message?: string; errors?: Record<string, string[]> } = {};
      try { payload = JSON.parse(request.responseText); } catch { /* handled as a normalized HTTP error */ }
      if (request.status >= 200 && request.status < 300 && payload.data) resolve(payload.data);
      else reject(new ApiError({ status: request.status, message: payload.message ?? request.statusText ?? "Upload failed.", errors: payload.errors }));
    };
    if (signal) {
      if (signal.aborted) request.abort();
      signal.addEventListener("abort", () => request.abort(), { once: true });
    }
    request.send(body);
  });
}

export const cloudService = {
  async getBrowser(ownerId: string, folderId: string | null = null, search?: string): Promise<{ items: CloudItem[]; browser: BrowserResponse }> {
    const params = new URLSearchParams();
    if (folderId) params.set("folderId", folderId);
    if (search?.trim()) params.set("search", search.trim());
    const browser = await api.get<BrowserResponse>(`/api/browser${params.size ? `?${params}` : ""}`);
    return { items: mapBrowser(browser, ownerId), browser };
  },
  async getRecent(ownerId: string, limit = 100): Promise<CloudItem[]> {
    const response = await api.get<{ items: ApiItem[] }>(`/api/recent?limit=${limit}`);
    return mapCollection(response.items, ownerId);
  },
  async getStarred(ownerId: string): Promise<CloudItem[]> {
    const response = await api.get<{ items: ApiItem[] }>("/api/starred");
    return mapCollection(response.items, ownerId);
  },
  async getStorage(ownerId: string): Promise<StorageApiSummary> { const response = await api.get<Omit<StorageApiSummary, "largestFiles"> & { largestFiles: Array<ApiItem & { trashedAt?: string | null }> }>("/api/storage"); return { ...response, largestFiles: response.largestFiles.map(file => toCloudItem({ ...file, type: "file" }, ownerId, file.folderId ?? null)) }; },
  async getTrash(ownerId: string): Promise<CloudItem[]> {
    const entries = await api.get<Array<{ id: string; type: "file" | "folder"; name: string; sizeBytes: number | null; trashedAt: string; originalLocation: Array<{ id: string; name: string }> }>>("/api/trash");
    return entries.map((entry) => ({ id: entry.id, ownerId, name: entry.name, kind: entry.type, fileType: "other", mimeType: entry.type === "folder" ? "inode/directory" : "application/octet-stream", extension: "", size: Number(entry.sizeBytes ?? 0), parentId: null, path: entry.originalLocation.map((part) => part.name).join(" / ") || "My Files", createdAt: entry.trashedAt, updatedAt: entry.trashedAt, accessedAt: entry.trashedAt, starred: false, deletedAt: entry.trashedAt, originalParentId: entry.originalLocation.at(-1)?.id ?? null }));
  },
  async getActivity(limit = 100): Promise<CloudActivity[]> {
    const data = await api.get<{ items: Array<{ id: string; action: string; subject: { id?: string; name?: string; type?: string } | null; metadata: Record<string, unknown>; createdAt: string }> }>(`/api/activity?limit=${limit}`);
    const actionMap: Record<string, CloudActivity["action"]> = { "file.uploaded": "uploaded", "file.renamed": "renamed", "file.moved": "moved", "file.starred": "starred", "file.unstarred": "unstarred", "file.downloaded": "downloaded", "file.trashed": "deleted", "file.restored": "restored", "file.deleted": "permanently-deleted", "folder.created": "created", "folder.renamed": "renamed", "folder.moved": "moved", "folder.starred": "starred", "folder.unstarred": "unstarred", "folder.trashed": "deleted", "folder.restored": "restored", "folder.deleted": "permanently-deleted", "trash.emptied": "emptied-trash", "share.created": "shared", "share.received": "shared", "share.permission_updated": "shared", "share.revoked": "sharing-stopped", "public_link.enabled": "shared", "public_link.disabled": "sharing-stopped", "public_link.regenerated": "shared" };
    return data.items.map((entry) => ({ id: entry.id, action: actionMap[entry.action] ?? "created", itemName: entry.subject?.name ?? "Cloud", itemId: entry.subject?.id, itemType: entry.subject?.type === "folder" ? "folder" : "file", location: "My Files", timestamp: entry.createdAt, previousName: typeof entry.metadata.from === "string" ? entry.metadata.from : undefined, targetLocation: typeof entry.metadata.to === "string" ? entry.metadata.to : undefined, metadata: JSON.stringify(entry.metadata) }));
  },
  async createFolder(name: string, parentId: string | null): Promise<void> { await api.post("/api/folders", { name, parentId }); },
  async rename(item: CloudItem, name: string): Promise<void> { await api.patch(endpoint(item), { name }); },
  async move(item: CloudItem, parentId: string | null): Promise<void> { await api.post(endpoint(item, "/move"), item.kind === "folder" ? { parentId } : { folderId: parentId }); },
  async star(item: CloudItem, starred: boolean): Promise<void> { await (starred ? api.post : api.delete)(endpoint(item, "/star")); },
  async trash(item: CloudItem): Promise<void> { await api.post(endpoint(item, "/trash")); },
  async restore(item: CloudItem): Promise<void> { await api.post(endpoint(item, "/restore")); },
  async permanent(item: CloudItem): Promise<void> { await api.delete(endpoint(item, "/permanent")); },
  async emptyTrash(): Promise<void> { await api.delete("/api/trash"); },
  async upload(file: File, folderId: string | null, strategy: "ask" | "keep_both" | "replace" = "ask", signal?: AbortSignal, progress?: (value: number) => void): Promise<CloudItem> {
    const result = await uploadRequest(file, folderId, strategy, signal, progress);
    return toCloudItem({ ...result, type: "file" }, "", folderId);
  },
  async preview(item: CloudItem): Promise<Blob> { return apiBlob(endpoint(item, "/preview")); },
  async download(item: CloudItem): Promise<void> { const anchor = document.createElement("a"); anchor.href = `${apiBaseUrl()}${endpoint(item, "/download")}`; anchor.rel = "noreferrer"; anchor.click(); },
  async search(ownerId: string, query: string, folderId: string | null = null): Promise<CloudItem[]> {
    return (await this.getBrowser(ownerId, folderId, query)).items;
  },
};

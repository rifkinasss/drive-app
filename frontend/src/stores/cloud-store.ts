"use client";

import { createContext, createElement, useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";
import { useThemePreference } from "@/stores/theme-store";
import { percentageOf } from "@/lib/format";
import { filesApi as cloudService } from "@/features/files/api/files.api";
import { useAuthStore } from "@/stores/auth-store";
import type { CloudActivity, CloudItem, StorageApiSummary, UploadTask } from "@/types/cloud";

type CloudStore = ReturnType<typeof useCloudStoreValue>;
type DataDomain = "browser" | "storage" | "trash" | "recent" | "starred" | "activity";
type DomainErrors = Record<DataDomain, string>;
const CloudContext = createContext<CloudStore | null>(null);

export function CloudStoreProvider({ children }: { children: React.ReactNode }) {
  const store = useCloudStoreValue();
  return createElement(CloudContext.Provider, { value: store }, children);
}

export function useCloudStore(folderId?: string | null) {
  const store = useContext(CloudContext);
  if (!store) throw new Error("useCloudStore must be used inside CloudStoreProvider.");
  const { setFolder } = store;
  useEffect(() => { if (folderId !== undefined) void setFolder(folderId); }, [folderId, setFolder]);
  return store;
}

function useCloudStoreValue() {
  const auth = useAuthStore();
  const userId = auth.currentUserId;
  const [folderId, setFolderId] = useState<string | null>(null);
  const [items, setItems] = useState<CloudItem[]>([]);
  const [searchItems, setSearchItems] = useState<CloudItem[] | null>(null);
  const [recentItems, setRecentItems] = useState<CloudItem[]>([]);
  const [starredItems, setStarredItems] = useState<CloudItem[]>([]);
  const [trashItems, setTrashItems] = useState<CloudItem[]>([]);
  const [activities, setActivities] = useState<CloudActivity[]>([]);
  const [storageSummary, setStorageSummary] = useState<StorageApiSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [domainErrors, setDomainErrors] = useState<DomainErrors>({ browser: "", storage: "", trash: "", recent: "", starred: "", activity: "" });
  const [view, setView] = useState<"list" | "grid">("grid");
  const [uploadTasks, setUploadTasks] = useState<UploadTask[]>([]);
  const uploads = useRef(new Map<string, AbortController>());
  const { theme: selectedTheme, setTheme } = useThemePreference();
  const theme: "light" | "dark" | "system" = selectedTheme === "light" || selectedTheme === "dark" ? selectedTheme : "system";

  const refresh = useCallback(async (domains: DataDomain[] = ["browser", "storage", "trash", "recent", "starred", "activity"]) => {
    if (!userId) return;
    setError("");
    setIsRefreshing(true);
    setDomainErrors((current) => domains.reduce((next, domain) => ({ ...next, [domain]: "" }), current));
    const requests = domains.map(async (domain) => {
      try {
        switch (domain) {
          case "browser": { setItems((await cloudService.getBrowser(userId, folderId)).items); setSearchItems(null); break; }
          case "storage": setStorageSummary(await cloudService.getStorage(userId)); break;
          case "trash": setTrashItems(await cloudService.getTrash(userId)); break;
          case "recent": setRecentItems(await cloudService.getRecent(userId)); break;
          case "starred": setStarredItems(await cloudService.getStarred(userId)); break;
          case "activity": setActivities(await cloudService.getActivity()); break;
        }
      } catch (reason) {
        const message = reason instanceof Error ? reason.message : "Unable to load Drive data.";
        setDomainErrors((current) => ({ ...current, [domain]: message }));
        throw reason;
      }
    });
    const results = await Promise.allSettled(requests);
    const failure = results.find((result): result is PromiseRejectedResult => result.status === "rejected");
    if (failure) setError(failure.reason instanceof Error ? failure.reason.message : "Unable to load Drive data.");
    setIsRefreshing(false);
  }, [folderId, userId]);

  // The store resets and refreshes when the authenticated account changes.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (!userId) { setItems([]); setRecentItems([]); setStarredItems([]); setTrashItems([]); setActivities([]); setStorageSummary(null); setDomainErrors({ browser: "", storage: "", trash: "", recent: "", starred: "", activity: "" }); setLoading(false); return; }
    setLoading(true);
    void refresh().finally(() => { setLoading(false); setIsRefreshing(false); });
  }, [refresh, userId]);

  const run = useCallback(async (operation: () => Promise<void>, domains: DataDomain[]) => {
    setError("");
    try { await operation(); await refresh(domains); return true; }
    catch (reason) { setError(reason instanceof Error ? reason.message : "The request failed."); return false; }
  }, [refresh]);

  const activeItems = items;
  const storage = useMemo(() => { const total = Number(storageSummary?.quotaBytes ?? 0); const used = Number(storageSummary?.usedBytes ?? 0); return { total, used, available: Number(storageSummary?.availableBytes ?? 0), trash: Number(storageSummary?.trashBytes ?? 0), trashCount: Number(storageSummary?.trashCount ?? 0), percentage: percentageOf(used, total), categories: storageSummary?.categories ?? {}, largestFiles: storageSummary?.largestFiles ?? [], cleanup: storageSummary?.cleanup ?? { oldDays: 180, largeMinBytes: 100 * 1024 * 1024, largeCount: 0, largeBytes: 0, oldCount: 0, oldBytes: 0, largeFiles: [], oldFiles: [], duplicateGroups: [] } }; }, [storageSummary]);

  const toggleStar = useCallback((id: string) => {
    const item = [...items, ...recentItems, ...starredItems].find((entry) => entry.id === id);
    if (item) void run(() => cloudService.star(item, !item.starred), ["browser", "starred", "recent", "activity"]);
  }, [items, recentItems, run, starredItems]);
  const findItem = useCallback((id: string) => [...items, ...trashItems, ...recentItems, ...starredItems].find((entry) => entry.id === id), [items, recentItems, starredItems, trashItems]);
  const createFolder = useCallback((name: string, parentId: string | null) => run(() => cloudService.createFolder(name, parentId), ["browser", "activity"]), [run]);
  const rename = useCallback((id: string, name: string) => { const item = findItem(id); return item ? run(() => cloudService.rename(item, name), ["browser", "recent", "starred", "activity"]) : Promise.resolve(false); }, [findItem, run]);
  const move = useCallback((id: string, parentId: string | null) => { const item = findItem(id); return item ? run(() => cloudService.move(item, parentId), ["browser", "recent", "starred", "activity"]) : Promise.resolve(false); }, [findItem, run]);
  const moveToTrash = useCallback((id: string) => { const item = findItem(id); return item ? run(() => cloudService.trash(item), ["browser", "storage", "trash", "recent", "starred", "activity"]) : Promise.resolve(false); }, [findItem, run]);
  const restore = useCallback((id: string) => { const item = findItem(id); return item ? run(() => cloudService.restore(item), ["browser", "storage", "trash", "recent", "starred", "activity"]) : Promise.resolve(false); }, [findItem, run]);
  const deletePermanently = useCallback((id: string) => { const item = findItem(id); return item ? run(() => cloudService.permanent(item), ["browser", "storage", "trash", "recent", "starred", "activity"]) : Promise.resolve(false); }, [findItem, run]);
  const bulkStar = useCallback((ids: string[], starred: boolean) => run(async () => { await Promise.all(ids.map(id => { const item = findItem(id); return item ? cloudService.star(item, starred) : Promise.resolve(); })); }, ["browser", "starred", "recent", "activity"]), [findItem, run]);
  const bulkTrash = useCallback((ids: string[]) => run(async () => { await Promise.all(ids.map(id => { const item = findItem(id); return item ? cloudService.trash(item) : Promise.resolve(); })); }, ["browser", "storage", "trash", "recent", "starred", "activity"]), [findItem, run]);
  const bulkRestore = useCallback((ids: string[]) => run(async () => { await Promise.all(ids.map(id => { const item = findItem(id); return item ? cloudService.restore(item) : Promise.resolve(); })); }, ["browser", "storage", "trash", "recent", "starred", "activity"]), [findItem, run]);
  const bulkDeletePermanently = useCallback((ids: string[]) => run(async () => { await Promise.all(ids.map(id => { const item = findItem(id); return item ? cloudService.permanent(item) : Promise.resolve(); })); }, ["browser", "storage", "trash", "recent", "starred", "activity"]), [findItem, run]);
  const bulkDownload = useCallback((ids: string[]) => { ids.map(findItem).filter((item): item is CloudItem => item?.kind === "file").forEach(item => { void cloudService.download(item); }); }, [findItem]);
  const emptyTrash = useCallback(() => run(() => cloudService.emptyTrash(), ["browser", "storage", "trash", "recent", "starred", "activity"]), [run]);

  const upload = useCallback((file: File, targetFolderId: string | null) => {
    const id = crypto.randomUUID();
    const controller = new AbortController();
    uploads.current.set(id, controller);
    const task: UploadTask = { id, name: file.name, size: file.size, mimeType: file.type || "application/octet-stream", progress: 0, status: "queued", file, targetFolderId, createdAt: new Date().toISOString() };
    setUploadTasks((current) => [...current, task]);
    setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "uploading" } : entry));
    void cloudService.upload(file, targetFolderId, "ask", controller.signal, (progress) => setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, progress } : entry)))
      .then(() => { setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "completed", progress: 100 } : entry)); void refresh(["browser", "storage", "recent", "starred", "activity"]).catch(() => undefined); })
      .catch((reason: unknown) => {
        if (controller.signal.aborted) return;
        const conflict = (reason as { status?: number }).status === 409;
        const message = reason instanceof Error ? reason.message : "Upload failed.";
        setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "failed", error: message, conflict } : entry));
      }).finally(() => uploads.current.delete(id));
  }, [refresh]);
  const cancelUpload = useCallback((id: string) => { uploads.current.get(id)?.abort(); setUploadTasks((current) => current.map((task) => task.id === id ? { ...task, status: "cancelled" } : task)); }, []);
  const retryUpload = useCallback((id: string) => {
    const task = uploadTasks.find((entry) => entry.id === id);
    if (!task) return;
    setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "uploading", error: undefined, progress: 0 } : entry));
    const controller = new AbortController(); uploads.current.set(id, controller);
    void cloudService.upload(task.file, task.targetFolderId, "ask", controller.signal, (progress) => setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, progress } : entry)))
      .then(() => { setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "completed", progress: 100 } : entry)); void refresh(["browser", "storage", "recent", "starred", "activity"]).catch(() => undefined); })
      .catch((reason: unknown) => setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "failed", error: reason instanceof Error ? reason.message : "Upload failed." } : entry)))
      .finally(() => uploads.current.delete(id));
  }, [refresh, uploadTasks]);
  const resolveUpload = useCallback((id: string, resolution: "keep" | "replace" | "cancel") => {
    if (resolution === "cancel") { setUploadTasks((current) => current.map((task) => task.id === id ? { ...task, status: "cancelled" } : task)); return; }
    const task = uploadTasks.find((entry) => entry.id === id);
    if (!task) return;
    const strategy = resolution === "keep" ? "keep_both" : "replace";
    const controller = new AbortController(); uploads.current.set(id, controller);
    setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "uploading", error: undefined, progress: 0 } : entry));
    void cloudService.upload(task.file, task.targetFolderId, strategy, controller.signal, (progress) => setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, progress } : entry)))
      .then(() => { setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "completed", progress: 100, conflict: false } : entry)); void refresh(["browser", "storage", "recent", "starred", "activity"]).catch(() => undefined); })
      .catch((reason: unknown) => setUploadTasks((current) => current.map((entry) => entry.id === id ? { ...entry, status: "failed", error: reason instanceof Error ? reason.message : "Upload failed." } : entry)))
      .finally(() => uploads.current.delete(id));
  }, [refresh, uploadTasks]);
  const dismissUpload = useCallback((id: string) => setUploadTasks((current) => current.filter((task) => task.id !== id)), []);

  const setFolder = useCallback(async (id: string | null) => setFolderId(id), []);
  const search = useCallback(async (query: string) => {
    if (!userId) return;
    try { setSearchItems(query.trim() ? await cloudService.search(userId, query, folderId) : null); setError(""); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Unable to search this folder."); setSearchItems([]); }
  }, [folderId, userId]);
  const addActivity = useCallback(() => undefined, []);
  const copy = useCallback(() => { setError("Copying files is not available in the backend yet."); return false; }, []);
  return { items, activeItems, searchItems, search, recentItems, starredItems, trashItems, activities, loading, isRefreshing, error, domainErrors, clearError: () => setError(""), theme, setTheme, view, setView, toggleStar, createFolder, rename, move, copy, moveToTrash, restore, deletePermanently, bulkStar, bulkTrash, bulkRestore, bulkDeletePermanently, bulkDownload, emptyTrash, upload, uploadTasks, cancelUpload, retryUpload, resolveUpload, dismissUpload, storage, addActivity, refresh, setFolder };
}

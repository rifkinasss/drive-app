import { api } from "@/lib/api/client";
import type { NotificationItem, NotificationType } from "@/types/notification";

type ApiNotification = {
  id: string;
  data: Record<string, unknown>;
  readAt: string | null;
  createdAt: string;
};
function mapNotification(value: ApiNotification): NotificationItem {
  const data = value.data;
  const type = String(data.type ?? "system");
  const actor =
    data.actor && typeof data.actor === "object"
      ? (data.actor as { name?: string }).name
      : undefined;
  const item = typeof data.itemName === "string" ? data.itemName : "Drive";
  const title =
    type === "share.received"
      ? "Ada item yang dibagikan"
      : type === "storage.quota_warning"
        ? "Penyimpanan hampir penuh"
        : type === "share.permission_changed"
          ? "Izin berbagi berubah"
          : type === "share.revoked"
            ? "Akses berbagi dicabut"
            : "Pembaruan Drive";
  const message =
    type === "storage.quota_warning"
      ? `${String(data.usagePercentage ?? "")}% ruang penyimpanan telah digunakan.`
      : `${actor ? `${actor} · ` : ""}${item}`;
  const target = data.target && typeof data.target === "object"
    ? data.target as { kind?: string; itemId?: string; itemType?: string }
    : undefined;
  const itemId = typeof data.itemId === "string" ? data.itemId : target?.itemId;
  const itemType = data.itemType === "file" || data.itemType === "folder"
    ? data.itemType
    : target?.itemType === "file" || target?.itemType === "folder"
      ? target.itemType
      : undefined;
  const targetUrl = target?.kind === "storage"
    ? "/storage"
    : (type === "share.received" || type === "share.permission_changed") && itemId && itemType
      ? `/shared?resourceId=${encodeURIComponent(itemId)}&resourceType=${itemType}`
      : type === "share.revoked"
        ? "/activity"
        : type === "system"
          ? "/activity"
          : undefined;
  const notificationType: NotificationType = type.startsWith("share.")
    ? "sharing"
    : type === "storage.quota_warning"
      ? "storage"
      : "system";
  return {
    id: value.id,
    type: notificationType,
    title,
    message,
    createdAt: value.createdAt,
    read: Boolean(value.readAt),
    targetUrl,
  };
}

export const notificationService = {
  async list(perPage = 10): Promise<NotificationItem[]> {
    const result = await api.get<{ items: ApiNotification[] }>(
      `/api/notifications?perPage=${perPage}`,
    );
    return result.items.map(mapNotification);
  },
  async unreadCount(): Promise<number> {
    return (
      await api.get<{ unreadCount: number }>("/api/notifications/unread-count")
    ).unreadCount;
  },
  async read(id: string): Promise<void> {
    await api.post(`/api/notifications/${encodeURIComponent(id)}/read`);
  },
  async readAll(): Promise<void> {
    await api.post("/api/notifications/read-all");
  },
  async getPreferences() {
    return api.get<{
      shares: boolean;
      quota: boolean;
      accountSecurity: boolean;
    }>("/api/notification-preferences");
  },
  async updatePreferences(
    value: Partial<{
      shares: boolean;
      quota: boolean;
      accountSecurity: boolean;
    }>,
  ) {
    return api.patch<{
      shares: boolean;
      quota: boolean;
      accountSecurity: boolean;
    }>("/api/notification-preferences", value);
  },
};

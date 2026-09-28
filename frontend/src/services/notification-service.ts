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
      ? "An item was shared with you"
      : type === "storage.quota_warning"
        ? "Storage is almost full"
        : type === "share.permission_changed"
          ? "Sharing permission changed"
          : type === "share.revoked"
            ? "Sharing access removed"
            : "Drive update";
  const message =
    type === "storage.quota_warning"
      ? `Storage is ${String(data.usagePercentage ?? "")} percent used.`
      : `${actor ? `${actor} · ` : ""}${item}`;
  const targetUrl =
    data.target &&
    typeof data.target === "object" &&
    (data.target as { kind?: string }).kind === "storage"
      ? "/storage"
      : type.startsWith("share.") && typeof data.itemId === "string"
        ? "/shared"
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

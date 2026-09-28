import { api } from "@/lib/api/client";

export const systemSettingsApi = {
  get: <T>(group: string) =>
    api.get<{ group: string; settings: Record<string, unknown> }>(`/api/admin/settings/${group}`) as Promise<T>,
  update: (group: string, settings: Record<string, unknown>) =>
    api.patch<{ settings: Record<string, unknown> }>(`/api/admin/settings/${group}`, { settings }),
};

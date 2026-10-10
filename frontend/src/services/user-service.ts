import { api } from "@/lib/api/client";
import type { CloudUser, PasswordPolicy, UserRole, UserStatus } from "@/types/user";

export type AccountSetupMethod = "invitation" | "manual";
// Invitation validation is repeated by the backend; this only prevents obviously invalid submissions.
export const passwordPolicy: PasswordPolicy = { minimumPasswordLength: 8, requireUppercase: false, requireNumber: false, requireSpecialCharacter: false };

function mapUser(value: Record<string, unknown>): CloudUser {
  const name = String(value.name ?? "");
  return {
    id: String(value.id), name, email: String(value.email ?? ""), initials: name.split(/\s+/).map((part) => part[0]).join("").slice(0, 2).toUpperCase(),
    role: value.role === "admin" ? "admin" : "user", status: (value.status ?? "active") as UserStatus,
    quotaBytes: Number(value.quotaBytes ?? 0), usedBytes: Number(value.usedBytes ?? 0),
    createdAt: String(value.createdAt ?? ""), lastActiveAt: typeof value.lastActiveAt === "string" ? value.lastActiveAt : null,
    emailVerifiedAt: typeof value.emailVerifiedAt === "string" ? value.emailVerifiedAt : null,
    invitationToken: null, verificationToken: null, invitedAt: null, activatedAt: null,
    preferences: { theme: "system", defaultView: "grid", density: "comfortable", showFileExtensions: true, confirmPermanentDelete: true, defaultSort: "name", uploadConflict: "ask" },
  };
}

export const userService = {
  async getUsers(search = ""): Promise<CloudUser[]> {
    const params = new URLSearchParams({ perPage: "100" });
    if (search.trim()) params.set("search", search.trim());
    const response = await api.get<{ items: Array<Record<string, unknown>> }>(`/api/admin/users?${params}`);
    return response.items.map(mapUser);
  },
  async getUser(id: string): Promise<CloudUser> { return mapUser(await api.get<Record<string, unknown>>(`/api/admin/users/${encodeURIComponent(id)}`)); },
  async getPasswordPolicy(): Promise<PasswordPolicy> {
    const response = await api.get<{ settings: Record<string, unknown> }>("/api/admin/settings/security");
    return { minimumPasswordLength: Number(response.settings.passwordMinLength ?? 8), requireUppercase: Boolean(response.settings.passwordRequireUppercase), requireNumber: Boolean(response.settings.passwordRequireNumber), requireSpecialCharacter: Boolean(response.settings.passwordRequireSpecial) };
  },
  async createAccount(input: { name: string; email: string; role: UserRole; quotaBytes: number; setup: AccountSetupMethod; password?: string }): Promise<CloudUser> {
    const result = input.setup === "invitation"
      ? await api.post<{ user: Record<string, unknown> }>("/api/admin/users/invitations", { name: input.name, email: input.email, role: input.role })
      : await api.post<{ user: Record<string, unknown> }>("/api/admin/users", { name: input.name, email: input.email, role: input.role, password: input.password, password_confirmation: input.password });
    const user = mapUser(result.user);
    if (input.quotaBytes > 0) await api.patch(`/api/admin/users/${encodeURIComponent(user.id)}`, { quotaBytes: input.quotaBytes });
    return user;
  },
  async updateUser(id: string, patch: { name?: string; role?: UserRole; quotaBytes?: number }): Promise<CloudUser> { return mapUser(await api.patch<Record<string, unknown>>(`/api/admin/users/${encodeURIComponent(id)}`, patch)); },
  async setStatus(id: string, status: UserStatus): Promise<CloudUser> { return mapUser(await api.post<Record<string, unknown>>(`/api/admin/users/${encodeURIComponent(id)}/${status === "disabled" ? "disable" : "enable"}`)); },
  async deleteUser(id: string): Promise<void> { await api.delete(`/api/admin/users/${encodeURIComponent(id)}`); },
  async resendInvitation(id: string): Promise<void> { await api.post(`/api/admin/users/${encodeURIComponent(id)}/invitation/resend`); },
  async regenerateInvitation(id: string): Promise<void> { await api.post(`/api/admin/users/${encodeURIComponent(id)}/invitation/regenerate`); },
  async resendVerification(id: string): Promise<void> { await api.post(`/api/admin/users/${encodeURIComponent(id)}/verification/resend`); },
  async verifyEmail(id: string): Promise<CloudUser> { return mapUser(await api.post<Record<string, unknown>>(`/api/admin/users/${encodeURIComponent(id)}/verify`)); },
  async unverifyEmail(id: string): Promise<CloudUser> { return mapUser(await api.post<Record<string, unknown>>(`/api/admin/users/${encodeURIComponent(id)}/unverify`)); },
  async sendPasswordReset(id: string): Promise<void> { await api.post(`/api/admin/users/${encodeURIComponent(id)}/password-reset`); },
  async setPassword(id: string, password: string): Promise<CloudUser> { return mapUser(await api.post<Record<string, unknown>>(`/api/admin/users/${encodeURIComponent(id)}/password`, { password, password_confirmation: password })); },
};

export { mapUser as mapCloudUser };

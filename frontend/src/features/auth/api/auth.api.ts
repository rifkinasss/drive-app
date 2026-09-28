import { api } from "@/lib/api/client";
import type { CloudUser } from "@/types/user";
export { passwordPolicy } from "@/services/user-service";

export const authApi = {
  getCurrentUser: () => api.get<{ user: CloudUser }>("/api/auth/user"),
  login: (email: string, password: string, remember: boolean) =>
    api.post("/api/auth/login", { email, password, remember }),
  logout: () => api.post("/api/auth/logout"),
  updateProfile: (name: string) => api.patch("/api/auth/profile", { name }),
  requestPasswordReset: (email: string) =>
    api.post("/api/auth/forgot-password", { email }),
  resetPassword: (token: string, email: string, password: string, confirmation: string) =>
    api.post("/api/auth/reset-password", {
      token,
      email,
      password,
      password_confirmation: confirmation,
    }),
  getInvitation: (token: string) =>
    api.get<{ name: string; email: string }>(`/api/invitations/${encodeURIComponent(token)}`),
  acceptInvitation: (token: string, password: string, confirmation: string) =>
    api.post(`/api/invitations/${encodeURIComponent(token)}/accept`, {
      password,
      password_confirmation: confirmation,
    }),
  getVerification: (token: string) =>
    api.get<{ email: string }>(`/api/email-verification/${encodeURIComponent(token)}`),
  verifyEmail: (token: string) =>
    api.post(`/api/email-verification/${encodeURIComponent(token)}/verify`),
};

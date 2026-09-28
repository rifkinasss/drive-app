"use client";

import { useCallback, useEffect, useSyncExternalStore } from "react";
import { ApiError, csrfCookie } from "@/lib/api/client";
import { authApi } from "@/features/auth/api/auth.api";
import type { CloudUser } from "@/types/user";

let currentUserId = "";
let currentUser: CloudUser | null = null;
let loading = true;
let sessionError: ApiError | null = null;
const listeners = new Set<() => void>();
const subscribe = (listener: () => void) => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};
const getSnapshot = () => currentUserId;
const getServerSnapshot = () => "";
function mapUser(user: Partial<CloudUser> & { id: string | number }): CloudUser {
  const now = new Date().toISOString();
  return {
    id: String(user.id), name: user.name ?? "", email: user.email ?? "", initials: (user.name ?? "").split(" ").map((part) => part[0]).join("").slice(0, 2).toUpperCase(),
    role: user.role ?? "user", status: user.status ?? "active", quotaBytes: user.quotaBytes ?? 0, usedBytes: user.usedBytes ?? 0,
    createdAt: user.createdAt ?? now, lastActiveAt: user.lastActiveAt ?? null, emailVerifiedAt: user.emailVerifiedAt ?? null,
    invitationToken: null, verificationToken: null, invitedAt: null, activatedAt: null,
    preferences: user.preferences ?? { theme: "system", defaultView: "grid", density: "comfortable", showFileExtensions: true, confirmPermanentDelete: true, defaultSort: "name", uploadConflict: "ask" },
  };
}
export function useAuthStore() {
  const userId = useSyncExternalStore(
    subscribe,
    getSnapshot,
    getServerSnapshot,
  );
  const refreshUser = useCallback(async () => {
    sessionError = null;
    try {
      const response = await authApi.getCurrentUser();
      currentUser = mapUser(response.user);
      currentUserId = currentUser.id;
    } catch (reason) {
      currentUser = null;
      currentUserId = "";
      if (reason instanceof ApiError) sessionError = reason;
      else sessionError = new ApiError({ status: 0, message: "Unable to connect to Drive. Check your connection and try again." });
    } finally {
      loading = false;
      listeners.forEach((listener) => listener());
    }
    return currentUser;
  }, []);
  useEffect(() => { if (loading) void refreshUser(); }, [refreshUser]);
  return {
    currentUserId: userId,
    currentUser,
    loading,
    sessionError,
    isAuthenticated: Boolean(userId),
    refreshUser,
    login: async (email: string, password: string, remember = false) => {
      await csrfCookie();
      await authApi.login(email, password, remember);
      const user = await refreshUser();
      if (!user) throw sessionError ?? new ApiError({ status: 401, message: "Unable to establish an authenticated session." });
      return user;
    },
    logout: async () => {
      try { await authApi.logout(); } finally {
      currentUserId = "";
      currentUser = null;
      loading = false;
      listeners.forEach((listener) => listener());
      }
    },
  };
}

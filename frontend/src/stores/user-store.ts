"use client";

import { useCallback, useEffect, useState } from "react";
import { usersApi as userService } from "@/features/admin/api/users.api";
import type { AccountSetupMethod } from "@/features/admin/types/user.types";
import type { CloudUser, UserRole, UserStatus } from "@/types/user";
import { useAuthStore } from "@/stores/auth-store";

export function useUserStore() {
  const auth = useAuthStore();
  const [users, setUsers] = useState<CloudUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const refresh = useCallback(async () => {
    setError("");
    try { setUsers(await userService.getUsers()); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Unable to load users."); }
    finally { setLoading(false); }
  }, []);
  // User management state follows the authenticated administrator role.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { if (auth.currentUser?.role === "admin") void refresh(); else { setUsers([]); setLoading(false); } }, [auth.currentUser?.role, refresh]);

  const addUser = async (input: { name: string; email: string; role: UserRole; quotaBytes: number; setup?: AccountSetupMethod; password?: string }) => {
    try { const user = await userService.createAccount({ ...input, setup: input.setup ?? "manual" }); await refresh(); return user; }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Unable to create user."); throw reason; }
  };
  const updateUser = async (id: string, patch: { name?: string; role?: UserRole; quotaBytes?: number }) => {
    try { const user = await userService.updateUser(id, patch); await refresh(); return user; }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Unable to update user."); throw reason; }
  };
  const setStatus = async (id: string, status: UserStatus) => {
    try { const user = await userService.setStatus(id, status); await refresh(); return user; }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Unable to update user status."); throw reason; }
  };
  const deleteUser = async (id: string) => {
    try { await userService.deleteUser(id); await refresh(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Unable to delete user."); throw reason; }
  };
  const perform = async (action: () => Promise<void>) => { try { await action(); await refresh(); } catch (reason) { setError(reason instanceof Error ? reason.message : "The request failed."); throw reason; } };

  return { users, currentUser: auth.currentUser, currentUserId: auth.currentUserId, loading, error, refresh, addUser, updateUser, setStatus, deleteUser, resendInvitation: (id: string) => perform(() => userService.resendInvitation(id)), regenerateInvitation: (id: string) => perform(() => userService.regenerateInvitation(id)), resendVerification: (id: string) => perform(() => userService.resendVerification(id)), sendPasswordReset: (id: string) => perform(() => userService.sendPasswordReset(id)) };
}

export type UserRole = "admin" | "user";
export type UserStatus = "pending" | "active" | "disabled";
export type UserDensity = "comfortable" | "compact";
export type DefaultSort = "name" | "modified" | "size";
export type UploadConflict = "ask" | "replace" | "keep-both";
export interface PasswordPolicy { minimumPasswordLength: number; requireUppercase: boolean; requireNumber: boolean; requireSpecialCharacter: boolean; }

export interface UserPreferences {
  theme: "light" | "dark" | "system";
  defaultView: "list" | "grid";
  density: UserDensity;
  showFileExtensions: boolean;
  confirmPermanentDelete: boolean;
  defaultSort: DefaultSort;
  uploadConflict: UploadConflict;
}

export interface CloudSession {
  id: string;
  device: string;
  location: string;
  lastActiveAt: string | null;
  current?: boolean;
}

export interface CloudUser {
  id: string;
  name: string;
  email: string;
  initials: string;
  avatarUrl?: string;
  role: UserRole;
  status: UserStatus;
  quotaBytes: number;
  usedBytes: number;
  createdAt: string;
  lastActiveAt: string | null;
  emailVerifiedAt: string | null;
  invitationToken: string | null;
  verificationToken: string | null;
  invitedAt: string | null;
  activatedAt: string | null;
  preferences: UserPreferences;
}

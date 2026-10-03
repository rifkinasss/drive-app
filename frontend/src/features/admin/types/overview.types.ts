export type AdminOverview = {
  users: { total: number; active: number }
  storage: { usedBytes: number; quotaBytes: number; availableBytes: number }
  files: { files: number; folders: number }
  sharing: { internalShares: number; publicLinks: number }
  activity: { recentCount: number }
  trash: { items: number; sizeBytes: number }
  system: { applicationVersion: string }
}

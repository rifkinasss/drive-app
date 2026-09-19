import type { CloudActivity } from "@/types/cloud";
export const mockActivities: CloudActivity[] = [
  {
    id: "activity-1",
    action: "uploaded",
    itemName: "portfolio.pdf",
    itemId: "portfolio",
    location: "Projects / NasLabs",
    timestamp: "2026-09-17T08:12:00Z",
  },
  {
    id: "activity-2",
    action: "renamed",
    itemName: "homelab-notes.md",
    itemId: "homelab",
    location: "Documents",
    timestamp: "2026-09-16T16:40:00Z",
  },
  {
    id: "activity-3",
    action: "moved",
    itemName: "database-backup.sql",
    itemId: "db",
    location: "Backups",
    timestamp: "2026-09-15T13:20:00Z",
  },
  {
    id: "activity-4",
    action: "deleted",
    itemName: "old-source.zip",
    itemId: "trash-old",
    location: "Archive",
    timestamp: "2026-09-14T11:05:00Z",
  },
  {
    id: "activity-5",
    action: "starred",
    itemName: "cloud-ui.fig",
    itemId: "fig",
    location: "Projects / NasLabs",
    timestamp: "2026-09-13T09:30:00Z",
  },
];

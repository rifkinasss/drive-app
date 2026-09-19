import type { CloudActivity } from "@/types/cloud";

export function formatActivityMessage(activity: CloudActivity): string {
  if (activity.action === "renamed")
    return `Renamed “${activity.previousName ?? "item"}” to “${activity.itemName}”`;
  if (activity.action === "moved")
    return `Moved “${activity.itemName}” to ${activity.targetLocation ?? activity.location}`;
  if (activity.action === "emptied-trash")
    return `Emptied Trash · ${activity.itemName} removed`;
  if (activity.action === "permanently-deleted")
    return `Permanently deleted “${activity.itemName}”`;
  if (activity.action === "deleted")
    return `Moved “${activity.itemName}” to Trash`;
  if (activity.action === "created")
    return `Created folder “${activity.itemName}”`;
  if (activity.action === "shared") return `Shared “${activity.itemName}”`;
  if (activity.action === "sharing-stopped")
    return `Stopped sharing “${activity.itemName}”`;
  return `${activity.action[0].toUpperCase()}${activity.action.slice(1)} “${activity.itemName}”`;
}

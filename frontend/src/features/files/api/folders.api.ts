import { cloudService } from "@/services/cloud-service";

export const foldersApi = {
  createFolder: cloudService.createFolder,
  renameFolder: (folder: Parameters<typeof cloudService.rename>[0], name: string) =>
    cloudService.rename(folder, name),
  moveFolder: (folder: Parameters<typeof cloudService.move>[0], parentId: string | null) =>
    cloudService.move(folder, parentId),
};

import { cloudService } from "@/services/cloud-service";

export const filesApi = {
  getBrowser: cloudService.getBrowser,
  getDetails: cloudService.getDetails,
  getRecent: cloudService.getRecent,
  getStarred: cloudService.getStarred,
  getTrash: cloudService.getTrash,
  createFolder: cloudService.createFolder,
  rename: cloudService.rename,
  move: cloudService.move,
  star: cloudService.star,
  trash: cloudService.trash,
  restore: cloudService.restore,
  permanent: cloudService.permanent,
  emptyTrash: cloudService.emptyTrash,
  upload: cloudService.upload,
  preview: cloudService.preview,
  download: cloudService.download,
  search: (ownerId: string, query: string, folderId: string | null = null) =>
    cloudService.search(ownerId, query, folderId),
  getStorage: cloudService.getStorage,
  getActivity: cloudService.getActivity,
};

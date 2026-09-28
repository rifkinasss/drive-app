import { shareService } from "@/services/share-service";

export const fileRequestsApi = {
  list: shareService.getFileRequests,
  getFileRequests: shareService.getFileRequests,
  create: shareService.createFileRequest,
  createFileRequest: shareService.createFileRequest,
  update: shareService.updateFileRequest,
  updateFileRequest: shareService.updateFileRequest,
  remove: shareService.deleteFileRequest,
  deleteFileRequest: shareService.deleteFileRequest,
};

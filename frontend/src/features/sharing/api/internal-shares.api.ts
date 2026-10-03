import { shareService } from "@/services/share-service";
import { publicLinksApi } from "./public-links.api";

export const internalSharesApi = {
  ...publicLinksApi,
  getForItem: shareService.getItemShares,
  getItemShares: shareService.getItemShares,
  searchUsers: shareService.searchUsers,
  shareWithUser: shareService.shareWithUser,
  updatePermission: shareService.updatePermission,
  removeRecipient: shareService.removeRecipient,
  getSharedWithMe: shareService.getSharedWithMe,
  getSharedByMe: shareService.getSharedByMe,
  browseSharedFolder: shareService.browseSharedFolder,
};

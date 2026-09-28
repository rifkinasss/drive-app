import { cloudService } from "@/services/cloud-service";

export const activityApi = {
  list: cloudService.getActivity,
};

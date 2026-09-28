import { securityService } from "@/services/security-service";

export const sessionsApi = {
  getSessions: securityService.getSessions,
  getNotificationPreferences: securityService.getNotificationPreferences,
  updateNotificationPreferences: securityService.updateNotificationPreferences,
};

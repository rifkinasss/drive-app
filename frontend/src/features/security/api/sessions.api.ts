import { securityService } from "@/services/security-service";

export const sessionsApi = {
  getSessions: securityService.getSessions,
  revokeSession: securityService.revokeSession,
  revokeOtherSessions: securityService.revokeOtherSessions,
  getNotificationPreferences: securityService.getNotificationPreferences,
  updateNotificationPreferences: securityService.updateNotificationPreferences,
};

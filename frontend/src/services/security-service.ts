import { api } from '@/lib/api/client'

export interface NotificationPreferences {
  shares: boolean
  quota: boolean
  accountSecurity: boolean
}

export interface SecuritySession {
  id: string
  device: string
  deviceLabel: string
  browser: string
  os: string
  ipAddress: string | null
  lastActiveAt: string
  createdAt: string | null
  approximateStatus: 'active' | 'recent'
  isCurrent: boolean
}

export const securityService = {
  async getNotificationPreferences(): Promise<NotificationPreferences> {
    return api.get<NotificationPreferences>('/api/notification-preferences')
  },
  async updateNotificationPreferences(value: Partial<NotificationPreferences>): Promise<NotificationPreferences> {
    return api.patch<NotificationPreferences>('/api/notification-preferences', value)
  },
  async getSessions(): Promise<SecuritySession[]> {
    const response = await api.get<{ items: SecuritySession[] }>('/api/security/sessions')
    return response.items
  },
  async revokeSession(id: string): Promise<void> {
    await api.delete(`/api/security/sessions/${encodeURIComponent(id)}`)
  },
  async revokeOtherSessions(): Promise<{ revokedCount: number }> {
    return api.delete<{ revokedCount: number }>('/api/security/sessions/others')
  },
}

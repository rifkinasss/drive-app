export type NotificationType = 'upload' | 'sharing' | 'storage' | 'system'

export interface NotificationItem {
  id: string
  type: NotificationType
  title: string
  message: string
  createdAt: string
  read: boolean
  targetUrl?: string
}

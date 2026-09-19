import type { NotificationItem } from '@/types/notification'

export const mockNotifications: NotificationItem[] = [
  { id: 'notification-upload', type: 'upload', title: 'Upload completed', message: 'portfolio.pdf was uploaded successfully.', createdAt: '2026-09-18T08:30:00.000Z', read: false, targetUrl: '/files' },
  { id: 'notification-sharing', type: 'sharing', title: 'Shared with you', message: 'Partner shared “photos.zip”.', createdAt: '2026-09-17T16:20:00.000Z', read: false, targetUrl: '/shared' },
  { id: 'notification-storage', type: 'storage', title: 'Storage warning', message: 'You have used 85% of your storage.', createdAt: '2026-09-16T12:00:00.000Z', read: true, targetUrl: '/storage' },
  { id: 'notification-system', type: 'system', title: 'System', message: 'Maintenance is scheduled.', createdAt: '2026-09-15T09:00:00.000Z', read: true, targetUrl: '/activity' },
]

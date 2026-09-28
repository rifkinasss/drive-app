/* Drive's worker is intentionally limited to Push and app-window lifecycle. */
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()))
self.addEventListener('push', (event) => {
  let data = { title: 'Drive by NasLabs', body: 'You have a new notification.', url: '/home' }
  try { if (event.data) data = { ...data, ...event.data.json() } } catch { /* keep the safe fallback */ }
  event.waitUntil(self.registration.showNotification(data.title, { body: data.body, icon: '/brand/drive-icon-192.png', badge: '/brand/drive-icon-192.png', data: { url: data.url } }))
})
self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const requested = new URL(event.notification.data?.url || '/home', self.location.origin)
  const target = (requested.origin === self.location.origin && requested.pathname.startsWith('/')) ? requested.href : new URL('/home', self.location.origin).href
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
    const existing = clients.find((client) => 'focus' in client)
    if (existing) { existing.navigate(target); return existing.focus() }
    return self.clients.openWindow(target)
  }))
})

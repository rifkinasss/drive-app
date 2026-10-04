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
  let target = new URL('/home', self.location.origin).href
  try {
    const requested = new URL(typeof event.notification.data?.url === 'string' ? event.notification.data.url : '/home', self.location.origin)
    if (requested.origin === self.location.origin && requested.pathname.startsWith('/') && !requested.pathname.startsWith('//')) target = requested.href
  } catch { /* keep the safe fallback */ }
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
    const existing = clients.find((client) => 'focus' in client)
    if (existing) { existing.navigate(target); return existing.focus() }
    return self.clients.openWindow(target)
  }))
})

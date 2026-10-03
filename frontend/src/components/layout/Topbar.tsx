'use client'
import Link from 'next/link'
import { Bell, Check, CloudUpload, LoaderCircle, Menu, Search, Settings, ShieldAlert, Share2, UserRound, X } from 'lucide-react'
import { forwardRef, useEffect, useRef, useState } from 'react'
import { ItemIcon } from '@/components/ui/Icon'
import { ThemeToggle } from '@/components/ui/ThemeToggle'
import { useAuthStore } from '@/stores/auth-store'
import { useRouter } from 'next/navigation'
import { formatRelative } from '@/lib/format'
import type { NotificationItem, NotificationType } from '@/types/notification'
import { notificationsApi as notificationService } from '@/features/notifications/api/notifications.api'
import { useCloudStore } from '@/stores/cloud-store'
export function Topbar({ onMenu }: { onMenu: () => void }) {
  const router = useRouter()
  const auth = useAuthStore()
  const currentUser = auth.currentUser
  const cloud = useCloudStore()
  const searchCloud = cloud.search
  const [query, setQuery] = useState('')
  const [accountOpen, setAccountOpen] = useState(false)
  const [notificationsOpen, setNotificationsOpen] = useState(false)
  const [notifications, setNotifications] = useState<NotificationItem[]>([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [notificationError, setNotificationError] = useState('')
  const [notificationsLoading, setNotificationsLoading] = useState(false)
  const [notificationActionLoading, setNotificationActionLoading] = useState(false)
  const [notificationsLoaded, setNotificationsLoaded] = useState(false)
  const notificationTriggerRef = useRef<HTMLButtonElement>(null)
  const notificationPanelRef = useRef<HTMLDivElement>(null)
  const accountTriggerRef = useRef<HTMLButtonElement>(null)
  const results = cloud.searchItems ?? []

  const refreshNotifications = async () => {
    setNotificationsLoading(true)
    setNotificationError('')
    try {
      const [list, count] = await Promise.all([notificationService.list(), notificationService.unreadCount()])
      setNotifications(list)
      setUnreadCount(count)
      setNotificationsLoaded(true)
    } catch {
      setNotificationError('Notifikasi belum dapat dimuat. Coba lagi.')
    } finally {
      setNotificationsLoading(false)
    }
  }

  const logout = async () => {
    try { await auth.logout() } catch { /* Local session state is cleared in the auth store even if the network request fails. */ } finally { setAccountOpen(false); router.replace('/login') }
  }

  useEffect(() => {
    const timer = window.setTimeout(() => { void searchCloud(query) }, query.trim() ? 250 : 0)
    return () => window.clearTimeout(timer)
  }, [searchCloud, query])

  useEffect(() => { void notificationService.unreadCount().then(setUnreadCount).catch(() => undefined) }, [])

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        if (accountOpen) accountTriggerRef.current?.focus()
        else if (notificationsOpen) notificationTriggerRef.current?.focus()
        setAccountOpen(false)
        setNotificationsOpen(false)
      }
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [accountOpen, notificationsOpen])

  useEffect(() => {
    if (!notificationsOpen) return
    const panel = notificationPanelRef.current
    const firstFocusable = panel?.querySelector<HTMLElement>('button:not([disabled])')
    window.setTimeout(() => firstFocusable?.focus(), 0)
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key !== 'Tab' || !panel) return
      const focusable = Array.from(panel.querySelectorAll<HTMLElement>('button:not([disabled])'))
      if (!focusable.length) return
      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [notificationsOpen])

  const markAllRead = async () => {
    setNotificationActionLoading(true)
    setNotificationError('')
    try { await notificationService.readAll(); await refreshNotifications() }
    catch { setNotificationError('Notifikasi belum dapat diperbarui. Coba lagi.') }
    finally { setNotificationActionLoading(false) }
  }

  const openNotification = async (notification: NotificationItem) => {
    setNotificationActionLoading(true)
    setNotificationError('')
    try {
      await notificationService.read(notification.id)
      await refreshNotifications()
      setNotificationsOpen(false)
      notificationTriggerRef.current?.focus()
      if (notification.targetUrl) router.push(notification.targetUrl)
    } catch { setNotificationError('Notifikasi belum dapat diperbarui. Coba lagi.') }
    finally { setNotificationActionLoading(false) }
  }

  const openAccount = () => { setNotificationsOpen(false); setAccountOpen(value => !value) }
  const closeNotifications = () => { setNotificationsOpen(false); notificationTriggerRef.current?.focus() }

  return <header className="topbar">
    <button className="menu-button icon-button" onClick={onMenu} aria-label="Open menu"><Menu size={20} /></button>
    <div className="search-wrap"><Search size={17} /><input value={query} onChange={event => setQuery(event.target.value)} placeholder="Search files and folders" aria-label="Search files and folders" />{query && <div className="search-results">{results.length ? results.map(item => <Link key={item.id} href={item.kind === 'folder' ? `/files?folder=${item.id}` : '/files'} onClick={() => setQuery('')}><ItemIcon item={item} /><span>{item.name}</span><small>{item.path}</small></Link>) : <p>No files found</p>}</div>}</div>
    <div className="topbar-actions">
      <div className="notification-wrap">
        <button ref={notificationTriggerRef} className="icon-button notification-trigger" onClick={() => { setAccountOpen(false); if (!notificationsOpen) void refreshNotifications(); setNotificationsOpen(value => !value) }} aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ''}`} aria-expanded={notificationsOpen} aria-haspopup="dialog"><Bell size={17} />{unreadCount > 0 && <span className="notification-badge" aria-hidden="true">{unreadCount > 99 ? '99+' : unreadCount}</span>}</button>
        {notificationsOpen && <><button className="notification-backdrop" aria-label="Close notifications" onClick={closeNotifications} /><NotificationPopover ref={notificationPanelRef} loading={notificationsLoading} loaded={notificationsLoaded} actionLoading={notificationActionLoading} notifications={notifications} error={notificationError} unreadCount={unreadCount} onOpen={openNotification} onMarkAllRead={markAllRead} onRetry={() => void refreshNotifications()} onClose={closeNotifications} onViewActivity={() => { closeNotifications(); router.push('/activity') }} /></>}
      </div>
      <ThemeToggle />
      <div className="account-menu-wrap"><button ref={accountTriggerRef} className="avatar account-trigger" onClick={openAccount} aria-expanded={accountOpen} aria-haspopup="menu">{currentUser?.initials ?? "?"}</button>{accountOpen && <div className="account-menu account-menu-rich" role="menu"><div className="account-menu-profile"><div className="avatar large">{currentUser?.initials ?? "?"}</div><div><strong>{currentUser?.name ?? ""}</strong><span>{currentUser?.email ?? ""}</span><small>{currentUser?.role === 'admin' ? 'Administrator' : 'User'}</small></div></div><div className="account-menu-divider" /><Link href="/settings/profile" role="menuitem" onClick={() => setAccountOpen(false)}><UserRound size={15} />Profile</Link><Link href="/settings" role="menuitem" onClick={() => setAccountOpen(false)}><Settings size={15} />Settings</Link><Link href="/storage" role="menuitem" onClick={() => setAccountOpen(false)}><CloudUpload size={15} />My Storage</Link><div className="account-menu-divider" /><button onClick={logout} role="menuitem" className="sign-out-item"><ShieldAlert size={15} />Sign out</button></div>}</div>
    </div>
  </header>
}

const NotificationPopover = forwardRef<HTMLDivElement, { loading: boolean; loaded: boolean; actionLoading: boolean; notifications: NotificationItem[]; error: string; unreadCount: number; onOpen: (notification: NotificationItem) => void; onMarkAllRead: () => void; onRetry: () => void; onClose: () => void; onViewActivity: () => void }>(function NotificationPopover({ loading, loaded, actionLoading, notifications, error, unreadCount, onOpen, onMarkAllRead, onRetry, onClose, onViewActivity }, ref) {
  return <div ref={ref} className="notification-popover" role="dialog" aria-modal="true" aria-labelledby="notification-title"><div className="notification-drag-handle" aria-hidden="true" /><div className="notification-head"><strong id="notification-title">Notifikasi</strong><div className="notification-head-actions">{unreadCount > 0 && <button className="text-action" onClick={onMarkAllRead} disabled={loading || actionLoading}>Tandai semua dibaca</button>}<button className="icon-button notification-close" onClick={onClose} aria-label="Tutup notifikasi"><X size={16} /></button></div></div>{error && <div className="notification-error" role="alert"><span>{error}</span><button className="text-action" onClick={onRetry} disabled={loading}>Coba lagi</button></div>}{loading && <div className="notification-state" role="status"><LoaderCircle className="spin" size={16} /><span>Memuat notifikasi…</span></div>}{!loading && !error && loaded && notifications.length === 0 && <div className="notification-state notification-empty"><Bell aria-hidden="true" /><strong>Belum ada notifikasi</strong><span>Aktivitas penting di Drive akan muncul di sini.</span><button className="text-action" onClick={onViewActivity}>Lihat aktivitas</button></div>}<div className="notification-list" aria-busy={loading}>{!loading && notifications.map(notification => <button className={`notification-item ${notification.read ? '' : 'unread'}`} key={notification.id} onClick={() => onOpen(notification)} disabled={actionLoading}><NotificationIcon type={notification.type} /><span className="notification-copy"><strong>{notification.title}</strong><span>{notification.message}</span><small>{formatRelative(notification.createdAt)}</small></span>{!notification.read && <i aria-label="Belum dibaca" />}</button>)}</div>{notifications.length > 0 && <div className="notification-foot"><button className="text-action" onClick={onViewActivity}>Lihat aktivitas</button></div>}</div>
})

function NotificationIcon({ type }: { type: NotificationType }) { const Icon = type === 'upload' ? CloudUpload : type === 'sharing' ? Share2 : type === 'storage' ? ShieldAlert : Check; return <span className={`notification-icon ${type}`}><Icon size={14} /></span> }

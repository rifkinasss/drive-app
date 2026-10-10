'use client'

import { LoaderCircle } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { usePathname, useRouter } from 'next/navigation'
import { Sidebar } from '@/components/layout/Sidebar'
import { Topbar } from '@/components/layout/Topbar'
import { CloudStoreProvider, useCloudStore } from '@/stores/cloud-store'
import { useAuthStore } from '@/stores/auth-store'
import { UploadManager } from '@/components/cloud/UploadManager'
import { useSidebarPreference } from '@/stores/sidebar-store'
import { CommandPalette } from '@/components/layout/CommandPalette'
import { getReturnPath } from '@/lib/auth-redirect'
import { MobileNavigation } from '@/components/layout/MobileNavigation'
export function AppShell({ children }: { children: React.ReactNode }) {
  return <CloudStoreProvider><AuthenticatedShell>{children}</AuthenticatedShell></CloudStoreProvider>
}

function AuthenticatedShell({ children }: { children: React.ReactNode }) {
  const router = useRouter(); const pathname = usePathname(); const auth = useAuthStore(); const store = useCloudStore(); const { collapsed, setCollapsed } = useSidebarPreference(); const [mobileOpen, setMobileOpen] = useState(false); const redirecting = useRef(false)
  const returnTo = typeof window === 'undefined' ? pathname : getReturnPath(pathname, window.location.search.slice(1))
  useEffect(() => { if (!auth.authInitialized || redirecting.current) return; if (auth.sessionError?.status === 503) { redirecting.current = true; try { window.sessionStorage.setItem('drive-maintenance-message', auth.sessionError.message) } catch { /* Storage may be unavailable; the maintenance page has a safe fallback. */ } router.replace('/maintenance') } else if (auth.sessionError?.status === 403) { redirecting.current = true; router.replace('/account-disabled') } else if (!auth.isAuthenticated && !auth.sessionError) { redirecting.current = true; router.replace(`/login?returnTo=${encodeURIComponent(returnTo)}`) } }, [auth.authInitialized, auth.isAuthenticated, auth.sessionError, returnTo, router])
  if (!auth.authInitialized || auth.loading) return <div className="loading-state" role="status" aria-live="polite"><LoaderCircle className="spin" size={18} aria-hidden="true" /><span>{auth.sessionError?.status === 0 ? auth.sessionError.message : 'Checking your session…'}</span>{auth.sessionError?.status === 0 && <button className="button secondary" onClick={() => void auth.refreshUser()}>Retry</button>}</div>
  if (auth.sessionError) return <div className="loading-state" role="alert"><span>{auth.sessionError.message}</span><button className="button secondary" onClick={() => void auth.refreshUser()}>Retry</button></div>
  if (!auth.isAuthenticated) return null
  return <div className={`app-shell ${collapsed ? 'sidebar-collapsed' : ''}`}><Sidebar storage={store.storage} open={mobileOpen} collapsed={collapsed} onToggle={() => setCollapsed(!collapsed)} onClose={() => setMobileOpen(false)} /><div className="main-shell"><Topbar onMenu={() => setMobileOpen(true)} /><main className="page-scroll">{store.error && <div className="form-hint error" role="alert">{store.error} <button className="text-action" onClick={() => void store.refresh()}>Retry</button></div>}{children}</main></div><MobileNavigation store={store} /><UploadManager store={store} /><CommandPalette store={store} />{mobileOpen && <button className="mobile-scrim" aria-label="Close menu" onClick={() => setMobileOpen(false)} />}</div>
}

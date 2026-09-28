'use client'
import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { Sidebar } from '@/components/layout/Sidebar'
import { Topbar } from '@/components/layout/Topbar'
import { CloudStoreProvider, useCloudStore } from '@/stores/cloud-store'
import { useAuthStore } from '@/stores/auth-store'
import { UploadManager } from '@/components/cloud/UploadManager'
import { useSidebarPreference } from '@/stores/sidebar-store'
import { CommandPalette } from '@/components/layout/CommandPalette'
export function AppShell({ children }: { children: React.ReactNode }) {
  return <CloudStoreProvider><AuthenticatedShell>{children}</AuthenticatedShell></CloudStoreProvider>
}

function AuthenticatedShell({ children }: { children: React.ReactNode }) {
  const router = useRouter(); const auth = useAuthStore(); const store = useCloudStore(); const { collapsed, setCollapsed } = useSidebarPreference(); const [mobileOpen, setMobileOpen] = useState(false)
  useEffect(() => { if (!auth.loading && auth.sessionError?.status === 503) router.replace('/maintenance'); else if (!auth.loading && auth.sessionError?.status === 403) router.replace('/account-disabled'); else if (!auth.loading && !auth.isAuthenticated && auth.sessionError?.status === 401) router.replace('/login') }, [auth.isAuthenticated, auth.loading, auth.sessionError, router])
  if (auth.loading || !auth.isAuthenticated) return <div className="loading-state"><span>{auth.sessionError?.status === 0 ? auth.sessionError.message : 'Checking your session…'}</span>{auth.sessionError?.status === 0 && <button className="button secondary" onClick={() => void auth.refreshUser()}>Retry</button>}</div>
  return <div className={`app-shell ${collapsed ? 'sidebar-collapsed' : ''}`}><Sidebar storage={store.storage} open={mobileOpen} collapsed={collapsed} onToggle={() => setCollapsed(!collapsed)} onClose={() => setMobileOpen(false)} /><div className="main-shell"><Topbar onMenu={() => setMobileOpen(true)} /><main className="page-scroll">{store.error && <div className="form-hint error" role="alert">{store.error} <button className="text-action" onClick={() => void store.refresh()}>Retry</button></div>}{store.loading && <p role="status">Loading Drive data…</p>}{children}</main></div><UploadManager store={store} /><CommandPalette store={store} />{mobileOpen && <button className="mobile-scrim" aria-label="Close menu" onClick={() => setMobileOpen(false)} />}</div>
}

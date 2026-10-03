'use client'

import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import type { ReactNode } from 'react'
import { settingsSections, SettingsNavigation, type SettingsSection } from '@/components/files/SettingsView'
import { useUserStore } from '@/stores/user-store'

export function SettingsRouteFrame({ children }: { children: ReactNode }) {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const userStore = useUserStore()
  const isAdmin = userStore.currentUser?.role === 'admin'
  const section = getSection(pathname, searchParams.get('section'), isAdmin)
  const navigate = (next: SettingsSection) => router.push(`/settings/${next}`)
  const availableSections = settingsSections.filter(item => !item.admin || isAdmin)
  return <div className="settings-route-frame"><div className="settings-mobile-selector"><label htmlFor="settings-section-select">Settings section</label><select id="settings-section-select" className="select-input" value={section} onChange={event => navigate(event.target.value as SettingsSection)}>{availableSections.map(item => <option key={item.id} value={item.id}>{item.label}</option>)}</select></div><SettingsNavigation selected={section} isAdmin={isAdmin} navigate={navigate} /><main className="settings-route-content">{children}</main></div>
}

function getSection(pathname: string, querySection: string | null, isAdmin: boolean): SettingsSection {
  const routeSection = pathname.split('/').pop()
  if (['profile', 'security', 'appearance', 'files', 'storage', 'about'].includes(routeSection ?? '')) return routeSection as SettingsSection
  if (isAdmin && ['admin', 'users', 'storage-management', 'system'].includes(routeSection ?? '')) return routeSection as SettingsSection
  if (querySection && ['profile', 'security', 'appearance', 'files', 'storage'].includes(querySection)) return querySection as SettingsSection
  return 'profile'
}

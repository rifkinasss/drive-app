'use client'

import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import type { ReactNode } from 'react'
import { SettingsNavigation, type SettingsSection } from '@/components/files/SettingsView'
import { useUserStore } from '@/stores/user-store'

export function SettingsRouteFrame({ children }: { children: ReactNode }) {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const userStore = useUserStore()
  const section = getSection(pathname, searchParams.get('section'))
  const navigate = (next: SettingsSection) => router.push(`/settings/${next}`)
  return <div className="settings-route-frame"><SettingsNavigation selected={section} isAdmin={userStore.currentUser?.role === 'admin'} navigate={navigate} /><main className="settings-route-content">{children}</main></div>
}

function getSection(pathname: string, querySection: string | null): SettingsSection {
  const routeSection = pathname.split('/').pop()
  if (['profile', 'security', 'appearance', 'files', 'storage', 'users', 'storage-management', 'system', 'about'].includes(routeSection ?? '')) return routeSection as SettingsSection
  if (querySection && ['profile', 'security', 'appearance', 'files', 'storage'].includes(querySection)) return querySection as SettingsSection
  return 'profile'
}

import { Suspense } from 'react'
import { SettingsRouteFrame } from '@/components/settings/SettingsRouteFrame'
import type { Metadata } from 'next'

export const metadata: Metadata = { title: 'Settings' }

export default function SettingsLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <Suspense fallback={null}><SettingsRouteFrame>{children}</SettingsRouteFrame></Suspense>
}

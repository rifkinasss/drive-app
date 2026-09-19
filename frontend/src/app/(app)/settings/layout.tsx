import { Suspense } from 'react'
import { SettingsRouteFrame } from '@/components/settings/SettingsRouteFrame'

export default function SettingsLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <Suspense fallback={null}><SettingsRouteFrame>{children}</SettingsRouteFrame></Suspense>
}

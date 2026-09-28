import { AppShell } from '@/components/layout/AppShell'
import type { Metadata } from 'next'

export const metadata: Metadata = { title: 'My Files' }

export default function AppLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <AppShell>{children}</AppShell>
}

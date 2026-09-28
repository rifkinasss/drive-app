import { AuthShell } from '@/components/auth/AuthShell'
import type { Metadata } from 'next'

export const metadata: Metadata = { title: 'Sign In' }

export default function AuthLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <AuthShell>{children}</AuthShell>
}

'use client'

import type { ReactNode } from 'react'
import { useEffect } from 'react'
import { applyTheme, initializeTheme, useThemePreference } from '@/stores/theme-store'
import { useAuthStore } from '@/stores/auth-store'

export function AppProviders({ children }: { children: ReactNode }) {
  const { theme } = useThemePreference()
  useAuthStore()
  useEffect(() => { initializeTheme(); applyTheme(theme); const media = window.matchMedia('(prefers-color-scheme: dark)'); const onSystemChange = () => { if (theme === 'system') applyTheme('system') }; media.addEventListener('change', onSystemChange); const onStorage = (event: StorageEvent) => { if (event.key === 'cloud-theme') initializeTheme() }; window.addEventListener('storage', onStorage); return () => { media.removeEventListener('change', onSystemChange); window.removeEventListener('storage', onStorage) } }, [theme])
  return <>{children}</>
}

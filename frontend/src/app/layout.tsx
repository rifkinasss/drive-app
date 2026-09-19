import type { Metadata } from 'next'
import { AppProviders } from '@/components/providers/AppProviders'
import '@/styles/globals.css'

export const metadata: Metadata = { title: 'Cloud by NasLabs', description: 'Private personal cloud storage by NasLabs.' }
const themeScript = `(() => { try { const saved = localStorage.getItem('cloud-theme'); const theme = saved === 'dark' || saved === 'light' ? saved : matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; document.documentElement.dataset.theme = theme } catch {} })()`
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="en" suppressHydrationWarning><head><script dangerouslySetInnerHTML={{ __html: themeScript }} /></head><body><AppProviders>{children}</AppProviders></body></html> }

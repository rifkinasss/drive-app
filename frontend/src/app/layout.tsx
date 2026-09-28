import type { Metadata, Viewport } from 'next'
import { AppProviders } from '@/components/providers/AppProviders'
import { appConfig } from '@/config/app'
import { env } from '@/config/env'
import '@/styles/globals.css'

export const metadata: Metadata = { metadataBase: new URL(env.appUrl), applicationName: appConfig.name, title: { default: appConfig.name, template: `%s | ${appConfig.name}` }, description: appConfig.description, manifest: '/manifest.webmanifest', icons: { icon: '/favicon.ico', shortcut: '/favicon.ico', apple: '/brand/drive-icon-192.png' }, openGraph: { title: appConfig.name, description: appConfig.description, siteName: appConfig.name, type: 'website', images: ['/brand/drive-logo-horizontal.png'] }, twitter: { card: 'summary', title: appConfig.name, description: appConfig.description, images: ['/brand/drive-logo-horizontal.png'] } }
export const viewport: Viewport = { themeColor: '#2563EB', width: 'device-width', initialScale: 1, viewportFit: 'cover' }
const themeScript = `(() => { try { const saved = localStorage.getItem('cloud-theme'); const theme = saved === 'dark' || saved === 'light' ? saved : matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; document.documentElement.dataset.theme = theme } catch {} })()`
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="en" suppressHydrationWarning><head><script dangerouslySetInnerHTML={{ __html: themeScript }} /></head><body><AppProviders>{children}</AppProviders></body></html> }

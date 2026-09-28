import type { Metadata } from 'next'

export const metadata: Metadata = { title: 'Shared File' }

export default function PublicLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <div className="public-layout">{children}</div>
}

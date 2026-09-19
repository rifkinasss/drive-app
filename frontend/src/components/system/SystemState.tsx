'use client'

import type { ReactNode } from 'react'
import type { LucideProps } from 'lucide-react'
import { CircleAlert, Cloud, FileQuestion, FolderSearch, UserX } from 'lucide-react'

type Action = { label: string; href?: string; onClick?: () => void }
export type SystemIconName = 'circle-alert' | 'file-question' | 'folder-search' | 'shield-check' | 'user-x' | 'cloud-cog'
type SystemStateProps = { icon: SystemIconName; code?: string; title: string; description: string; primaryAction?: Action; secondaryAction?: Action; inline?: boolean; children?: ReactNode }
const systemIcons: Record<SystemIconName, (props: LucideProps) => ReactNode> = { 'circle-alert': props => <CircleAlert {...props} />, 'file-question': props => <FileQuestion {...props} />, 'folder-search': props => <FolderSearch {...props} />, 'shield-check': props => <ShieldIcon {...props} />, 'user-x': props => <UserX {...props} />, 'cloud-cog': props => <CloudCogIcon {...props} /> }

export function SystemState({ inline = false, ...props }: SystemStateProps) {
  if (inline) return <SystemStateContent {...props} />
  return <main className="system-page"><div className="system-page-frame"><SystemBrand /><SystemStateContent {...props} /><SystemFooter /></div></main>
}

export function SystemStateContent({ icon, code, title, description, primaryAction, secondaryAction, children }: Omit<SystemStateProps, 'inline'>) {
  const iconElement = systemIcons[icon]({ size: 22 })
  return <section className="system-state" aria-labelledby="system-state-title"><div className="system-state-icon" aria-hidden="true">{iconElement}</div>{code && <span className="system-state-code">{code}</span>}<h1 id="system-state-title">{title}</h1><p>{description}</p>{children}<div className="system-state-actions">{primaryAction && <SystemAction action={primaryAction} primary />}{secondaryAction && <SystemAction action={secondaryAction} />}</div></section>
}

function SystemAction({ action, primary }: { action: Action; primary?: boolean }) {
  const className = `system-state-action${primary ? ' primary' : ''}`
  if (action.href) return <a className={className} href={action.href}>{action.label}</a>
  return <button className={className} type="button" onClick={action.onClick}>{action.label}</button>
}

function SystemBrand() {
  return <div className="system-page-brand"><span aria-hidden="true"><Cloud size={16} /></span><div><strong>Cloud</strong><small>by NasLabs</small></div></div>
}

function SystemFooter() {
  return <footer className="system-page-footer">Cloud by NasLabs · © 2026 NasLabs</footer>
}

export function AccessDeniedState({ adminOnly = false, onBack }: { adminOnly?: boolean; onBack?: () => void }) {
  return <SystemStateContent icon="shield-check" title="Access denied" description={adminOnly ? 'This page is only available to administrators.' : "You don't have permission to view this page."} primaryAction={onBack ? { label: 'Go back', onClick: onBack } : { label: 'Go home', href: '/home' }} secondaryAction={onBack ? { label: 'Go home', href: '/home' } : undefined} />
}

function ShieldIcon(props: LucideProps) { return <svg {...props} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3 5 6v5c0 4.7 2.9 8.3 7 10 4.1-1.7 7-5.3 7-10V6l-7-3Z" /><path d="m9.3 12 1.8 1.8 3.8-4" /></svg> }
function CloudCogIcon(props: LucideProps) { return <svg {...props} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M7.2 18.2h9.4a3.4 3.4 0 0 0 .5-6.8A5.2 5.2 0 0 0 7 9.2a4.5 4.5 0 0 0 .2 9Z" /><path d="M16.8 15.1v2.8M15.4 16.5h2.8" /></svg> }

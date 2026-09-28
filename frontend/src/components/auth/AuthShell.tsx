'use client'

import type { ReactNode } from 'react'
import { Cloud } from 'lucide-react'
import { ThemeToggle } from '@/components/ui/ThemeToggle'

export function AuthShell({ children }: { children: ReactNode }) {
  return <main className="auth-page"><div className="auth-theme-corner"><ThemeToggle /></div><div className="auth-frame"><div className="auth-brand"><span className="auth-brand-mark" aria-hidden="true"><Cloud size={17} /></span><div><strong>Drive</strong><span>by NasLabs</span></div></div>{children}<footer className="auth-footer"><span>Drive by NasLabs</span><span>Private file storage · © 2026 NasLabs</span></footer></div></main>
}

export function AuthPanel({ children, eyebrow }: { children: ReactNode; eyebrow?: string }) {
  return <>{eyebrow && <p className="auth-eyebrow">{eyebrow}</p>}<section className="auth-card">{children}</section></>
}

export function AuthField({ label, name, type = 'text', value, onChange, placeholder, autoComplete, error }: { label: string; name: string; type?: string; value: string; onChange: (value: string) => void; placeholder?: string; autoComplete?: string; error?: string }) {
  const errorId = error ? `${name}-error` : undefined
  return <label className="auth-field" htmlFor={name}><span>{label}</span><input id={name} name={name} type={type} value={value} onChange={event => onChange(event.target.value)} placeholder={placeholder} autoComplete={autoComplete} aria-invalid={Boolean(error)} aria-describedby={errorId} />{error && <span id={errorId} className="auth-field-error" role="alert">{error}</span>}</label>
}

export function PasswordField({ label, name, value, onChange, autoComplete, error }: { label: string; name: string; value: string; onChange: (value: string) => void; autoComplete?: string; error?: string }) {
  return <PasswordFieldInner label={label} name={name} value={value} onChange={onChange} autoComplete={autoComplete} error={error} />
}

function PasswordFieldInner({ label, name, value, onChange, autoComplete, error }: { label: string; name: string; value: string; onChange: (value: string) => void; autoComplete?: string; error?: string }) {
  return <label className="auth-field" htmlFor={name}><span>{label}</span><div className="auth-password-input"><input id={name} name={name} type="password" value={value} onChange={event => onChange(event.target.value)} autoComplete={autoComplete} aria-invalid={Boolean(error)} /><button type="button" className="auth-password-toggle" aria-label="Show password" onClick={event => { const input = event.currentTarget.previousElementSibling as HTMLInputElement | null; if (!input) return; input.type = input.type === 'password' ? 'text' : 'password'; event.currentTarget.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password') }}>Show</button></div>{error && <span className="auth-field-error" role="alert">{error}</span>}</label>
}

export function AuthNotice({ children, tone = 'error' }: { children: ReactNode; tone?: 'error' | 'info' }) {
  return <div className={`auth-notice ${tone}`} role={tone === 'error' ? 'alert' : 'status'} aria-live="polite">{children}</div>
}

export function AuthBackLink({ href = '/login', children = 'Back to sign in' }: { href?: string; children?: ReactNode }) {
  return <a className="auth-back-link" href={href}>← {children}</a>
}

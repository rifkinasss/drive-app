'use client'

import { useEffect, useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { AuthBackLink, AuthField, AuthNotice, AuthPanel, PasswordField } from '@/components/auth/AuthShell'
import { SystemStateContent } from '@/components/system/SystemState'
import { useAuthStore } from '@/stores/auth-store'
import { ApiError } from '@/lib/api/client'
import { authApi } from '@/features/auth/api/auth.api'
import { getSafeReturnTo } from '@/lib/auth-redirect'

export function LoginPageClient() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const auth = useAuthStore()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [rememberMe, setRememberMe] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const maintenance = searchParams.get('maintenance') === '1'
  const returnTo = getSafeReturnTo(searchParams.get('returnTo')) ?? '/home'

  const signIn = () => {
    setLoading(true)
    void auth.login(email, password, rememberMe).then((user) => {
      if (maintenance && user?.role !== 'admin') router.push('/maintenance')
      else if (user?.status === 'disabled') router.push('/account-disabled')
      else router.replace(returnTo)
    }).catch((reason: unknown) => { setError(reason instanceof Error ? reason.message : 'Invalid email or password.'); setLoading(false) })
  }

  return <AuthPanel><div className="auth-heading"><h1>Welcome back</h1><p>Sign in to your private Drive storage.</p></div>{error && <AuthNotice>{error}</AuthNotice>}<form className="auth-form" onSubmit={event => { event.preventDefault(); void signIn() }} noValidate><AuthField label="Email" name="email" type="email" value={email} onChange={value => { setEmail(value); setError('') }} placeholder="you@naslabs.my.id" autoComplete="email" /><PasswordField label="Password" name="password" value={password} onChange={setPassword} autoComplete="current-password" /><div className="auth-form-row"><label className="auth-checkbox"><input type="checkbox" checked={rememberMe} onChange={event => setRememberMe(event.target.checked)} /><span>Remember me</span></label><a href="/forgot-password">Forgot password?</a></div><button className="auth-submit" type="submit" disabled={loading}>{loading ? 'Signing in…' : 'Sign in'}</button></form></AuthPanel>
}

export function ForgotPasswordPageClient() {
  const [email, setEmail] = useState('')
  const [submitted, setSubmitted] = useState(false)
  const [error, setError] = useState('')
  return <AuthPanel eyebrow="Account recovery">{submitted ? <><div className="auth-heading"><h1>Check your email</h1><p>If an account exists for <strong>{email}</strong>, reset instructions have been prepared.</p></div><AuthNotice tone="info">If an account exists, reset instructions were sent.</AuthNotice><a className="auth-submit auth-submit-link" href="/login">Back to sign in</a></> : <><div className="auth-heading"><h1>Forgot password?</h1><p>Enter your email address and we&apos;ll send reset instructions.</p></div>{error && <AuthNotice>{error}</AuthNotice>}<form className="auth-form" onSubmit={event => { event.preventDefault(); if (!email.trim()) { setError('Enter your email address.'); return } void authApi.requestPasswordReset(email).then(() => setSubmitted(true)).catch((reason: unknown) => setError(reason instanceof ApiError ? reason.message : 'Unable to request a reset link.')) }} noValidate><AuthField label="Email" name="email" type="email" value={email} onChange={value => { setEmail(value); setError('') }} placeholder="you@naslabs.my.id" autoComplete="email" /><button className="auth-submit" type="submit">Send reset instructions</button><AuthBackLink /></form></>}</AuthPanel>
}

export function ResetPasswordPageClient() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState('')
  if (success) return <AuthPanel eyebrow="Account recovery"><div className="auth-heading"><h1>Password updated</h1><p>You can now sign in with your new password.</p></div><button className="auth-submit" type="button" onClick={() => router.push('/login')}>Back to sign in</button></AuthPanel>
  if (!token || !email) return <AuthPanel eyebrow="Account recovery"><div className="auth-heading"><h1>Reset link unavailable</h1><p>This password reset link is invalid or has expired.</p></div><a className="auth-submit auth-submit-link" href="/forgot-password">Request a new link</a></AuthPanel>
  return <AuthPanel eyebrow="Account recovery"><div className="auth-heading"><h1>Set a new password</h1><p>Choose a new password for your Drive account.</p></div>{error && <AuthNotice>{error}</AuthNotice>}<form className="auth-form" onSubmit={event => { event.preventDefault(); if (password.length < 10) { setError('Use at least 10 characters.'); return } if (password !== confirmation) { setError('Passwords do not match.'); return } void authApi.resetPassword(token, email, password, confirmation).then(() => setSuccess(true)).catch((reason: unknown) => setError(reason instanceof ApiError ? reason.message : 'Unable to reset password.')) }} noValidate><PasswordField label="New password" name="new-password" value={password} onChange={setPassword} autoComplete="new-password" /><PasswordField label="Confirm password" name="confirm-password" value={confirmation} onChange={setConfirmation} autoComplete="new-password" /><p className="auth-form-hint">Minimum 10 characters.</p><button className="auth-submit" type="submit">Reset password</button><AuthBackLink /></form></AuthPanel>
}

export function MaintenancePageClient() {
  const auth = useAuthStore()
  const router = useRouter()
  const [maintenanceMessage, setMaintenanceMessage] = useState('')
  const isAdmin = auth.currentUser?.role === 'admin'
  useEffect(() => { const timer = window.setTimeout(() => { try { const message = window.sessionStorage.getItem('drive-maintenance-message'); if (message) setMaintenanceMessage(message); window.sessionStorage.removeItem('drive-maintenance-message') } catch { /* Use the generic message when browser storage is unavailable. */ } }, 0); return () => window.clearTimeout(timer) }, [])
  return <AuthPanel eyebrow="Ketersediaan Drive"><SystemStateContent icon="cloud-cog" title="Drive sedang dalam pemeliharaan" description={maintenanceMessage || 'Drive sementara tidak tersedia selama pemeliharaan.'} primaryAction={isAdmin ? { label: 'Lanjut sebagai administrator', onClick: () => router.push('/home') } : { label: 'Kembali ke halaman masuk', href: '/login' }} /></AuthPanel>
}

export function AccountDisabledPageClient() {
  return <AuthPanel eyebrow="Account access"><SystemStateContent icon="user-x" title="Account disabled" description="Your account has been disabled by an administrator." primaryAction={{ label: 'Back to sign in', href: '/login' }}><p className="system-inline-copy">Contact the administrator if you believe this is a mistake.</p></SystemStateContent></AuthPanel>
}

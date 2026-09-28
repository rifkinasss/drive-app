'use client'

import { use } from 'react'
import { useState } from 'react'
import { useEffect } from 'react'
import { AuthNotice, AuthPanel, PasswordField } from '@/components/auth/AuthShell'
import { passwordPolicy } from '@/features/auth/api/auth.api'
import { ApiError } from '@/lib/api/client'
import { authApi } from '@/features/auth/api/auth.api'

export default function InvitationPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params); const [user, setUser] = useState<{ name: string; email: string } | null>(null); const [loading, setLoading] = useState(true); const [password, setPassword] = useState(''); const [confirm, setConfirm] = useState(''); const [error, setError] = useState(''); const [accepted, setAccepted] = useState(false)
  useEffect(() => { void authApi.getInvitation(token).then(setUser).catch((reason: unknown) => setError(reason instanceof ApiError ? reason.message : 'This invitation is not available.')).finally(() => setLoading(false)) }, [token])
  if (loading) return <AuthPanel eyebrow="Invitation"><div className="auth-heading"><h1>Checking invitation</h1><p>Please wait while we validate your invitation.</p></div></AuthPanel>
  if (!user) return <AuthPanel eyebrow="Invitation"><div className="auth-heading"><h1>Invitation unavailable</h1><p>This invitation is invalid or has already been used.</p></div><a className="auth-submit auth-submit-link" href="/login">Back to sign in</a></AuthPanel>
  if (accepted) return <AuthPanel eyebrow="Invitation accepted"><div className="auth-heading"><h1>Account activated</h1><p>Your Drive account is ready. Sign in to continue.</p></div><a className="auth-submit auth-submit-link" href="/login">Sign in</a></AuthPanel>
  const submit = (event: React.FormEvent) => { event.preventDefault(); const valid = password.length >= passwordPolicy.minimumPasswordLength && password === confirm && (!passwordPolicy.requireUppercase || /[A-Z]/.test(password)) && (!passwordPolicy.requireNumber || /\d/.test(password)) && (!passwordPolicy.requireSpecialCharacter || /[^A-Za-z0-9]/.test(password)); if (!valid) { setError(`Use ${passwordPolicy.minimumPasswordLength}+ characters with uppercase, number, and special character.`); return } void authApi.acceptInvitation(token, password, confirm).then(() => setAccepted(true)).catch((reason: unknown) => setError(reason instanceof ApiError ? reason.message : 'This invitation is no longer available.')) }
  return <AuthPanel eyebrow="You’re invited"><div className="auth-heading"><h1>Activate your account</h1><p><strong>{user.name}</strong> has been invited to Drive with {user.email}.</p></div>{error && <AuthNotice>{error}</AuthNotice>}<form className="auth-form" onSubmit={submit}><PasswordField label="Create password" name="password" value={password} onChange={setPassword} autoComplete="new-password" /><PasswordField label="Confirm password" name="confirm" value={confirm} onChange={setConfirm} autoComplete="new-password" /><p className="auth-form-hint">Minimum {passwordPolicy.minimumPasswordLength} characters, including uppercase, number, and special character.</p><button className="auth-submit">Activate account</button></form></AuthPanel>
}

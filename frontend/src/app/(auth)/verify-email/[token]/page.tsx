'use client'

import { use } from 'react'
import { useState } from 'react'
import { useEffect } from 'react'
import { AuthPanel } from '@/components/auth/AuthShell'
import { ApiError } from '@/lib/api/client'
import { authApi } from '@/features/auth/api/auth.api'

export default function VerifyEmailPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params); const [user, setUser] = useState<{ email: string } | null>(null); const [loading, setLoading] = useState(true); const [error, setError] = useState(''); const [verified, setVerified] = useState(false)
  useEffect(() => { void authApi.getVerification(token).then(setUser).catch((reason: unknown) => setError(reason instanceof ApiError ? reason.message : 'This verification link is not available.')).finally(() => setLoading(false)) }, [token])
  if (loading) return <AuthPanel eyebrow="Email verification"><div className="auth-heading"><h1>Checking verification link</h1><p>Please wait while we validate the link.</p></div></AuthPanel>
  if (!user) return <AuthPanel eyebrow="Email verification"><div className="auth-heading"><h1>Verification unavailable</h1><p>{error || 'This verification link is invalid or has already been used.'}</p></div><a className="auth-submit auth-submit-link" href="/login">Back to sign in</a></AuthPanel>
  if (verified) return <AuthPanel eyebrow="Email verified"><div className="auth-heading"><h1>Email confirmed</h1><p>{user.email} is now verified.</p></div><a className="auth-submit auth-submit-link" href="/login">Sign in</a></AuthPanel>
  return <AuthPanel eyebrow="Email verification"><div className="auth-heading"><h1>Verify your email</h1><p>Confirm {user.email} to finish setting up your Drive account.</p></div><button className="auth-submit" onClick={() => { void authApi.verifyEmail(token).then(() => setVerified(true)).catch((reason: unknown) => setError(reason instanceof ApiError ? reason.message : 'This verification link is no longer available.')) }}>{error && <span>{error}</span>}Verify email</button></AuthPanel>
}

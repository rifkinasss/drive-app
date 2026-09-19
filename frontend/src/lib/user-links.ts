function appBaseUrl(): string {
  if (typeof window !== 'undefined') return window.location.origin
  return process.env.NEXT_PUBLIC_APP_URL ?? ''
}

export function buildInvitationUrl(token: string): string {
  return `${appBaseUrl()}/invite/${encodeURIComponent(token)}`
}

export function buildVerificationUrl(token: string): string {
  return `${appBaseUrl()}/verify-email/${encodeURIComponent(token)}`
}

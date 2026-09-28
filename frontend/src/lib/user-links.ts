import { env } from '@/config/env'

function appBaseUrl(): string {
  if (typeof window !== 'undefined') return window.location.origin
  return env.appUrl
}

export function buildInvitationUrl(token: string): string {
  return `${appBaseUrl()}/invite/${encodeURIComponent(token)}`
}

export function buildVerificationUrl(token: string): string {
  return `${appBaseUrl()}/verify-email/${encodeURIComponent(token)}`
}

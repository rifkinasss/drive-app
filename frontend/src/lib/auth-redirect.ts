export function getSafeReturnTo(value: string | null | undefined): string | null {
  if (!value || !value.startsWith('/') || value.startsWith('//') || value.includes('\\')) return null
  if (/^\/(?:login|forgot-password|reset-password|maintenance|account-disabled)(?:[/?]|$)/.test(value)) return null
  return value
}

export function getReturnPath(pathname: string, search: string): string {
  return `${pathname}${search ? `?${search}` : ''}`
}

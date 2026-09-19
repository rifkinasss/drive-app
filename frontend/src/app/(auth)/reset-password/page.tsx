import { Suspense } from 'react'
import { ResetPasswordPageClient } from '@/components/auth/AuthPages'

export default function ResetPasswordPage() {
  return <Suspense fallback={null}><ResetPasswordPageClient /></Suspense>
}

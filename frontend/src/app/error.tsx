'use client'

import { useEffect } from 'react'
import { SystemState } from '@/components/system/SystemState'

export default function Error({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => { if (process.env.NODE_ENV === 'development') console.error('Drive route error', error) }, [error])
  return <SystemState icon="circle-alert" title="Something went wrong" description="An unexpected error occurred while loading Drive." primaryAction={{ label: 'Try again', onClick: reset }} secondaryAction={{ label: 'Go home', href: '/home' }} />
}

'use client'

import { SystemState } from '@/components/system/SystemState'

export default function GlobalError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return <html lang="en"><body><SystemState icon="circle-alert" title="Something went wrong" description="Cloud couldn't complete this request." primaryAction={{ label: 'Reload application', onClick: reset }} /></body></html>
}

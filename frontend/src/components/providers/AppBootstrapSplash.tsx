'use client'

import { useEffect, useState } from 'react'
import Image from 'next/image'
import { useAuthStore } from '@/stores/auth-store'

export function AppBootstrapSplash() {
  const { authInitialized } = useAuthStore()
  const [visible, setVisible] = useState(true)

  useEffect(() => {
    if (!authInitialized) return

    const timeout = window.setTimeout(() => setVisible(false), 280)

    return () => window.clearTimeout(timeout)
  }, [authInitialized])

  if (!visible) return null

  return (
    <div className={`app-bootstrap-splash${authInitialized ? ' is-exiting' : ''}`} role="status" aria-live="polite">
      <div className="app-bootstrap-splash-content">
        <div className="app-bootstrap-mark" aria-hidden="true">
          <Image className="app-bootstrap-mark-light" src="/brand/drive-mark-light.png" alt="" width={62} height={62} priority />
          <Image className="app-bootstrap-mark-dark" src="/brand/drive-mark-dark.png" alt="" width={62} height={62} priority />
        </div>
        <strong>Drive</strong>
        <span>by NasLabs</span>
        <small>Preparing your Drive...</small>
      </div>
    </div>
  )
}

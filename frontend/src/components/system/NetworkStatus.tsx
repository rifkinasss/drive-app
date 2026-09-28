'use client'

import { useEffect, useState } from 'react'

export function NetworkStatus() {
  const [online, setOnline] = useState(() => typeof navigator === 'undefined' ? true : navigator.onLine)
  const [recovered, setRecovered] = useState(false)

  useEffect(() => {
    let recoveryTimer: number | undefined
    let latestRequestId = 0

    const markOnline = (showRecovery = true) => {
      setOnline(previous => {
        if (!previous && showRecovery) {
          setRecovered(true)
          if (recoveryTimer !== undefined) window.clearTimeout(recoveryTimer)
          recoveryTimer = window.setTimeout(() => setRecovered(false), 2400)
        }
        return true
      })
    }
    const handleBrowserOnline = () => {
      markOnline(true)
      window.dispatchEvent(new Event('drive:online'))
    }
    const handleBrowserOffline = () => setOnline(false)
    const acceptNetworkEvent = (event: Event) => {
      const requestId = event instanceof CustomEvent ? Number(event.detail?.requestId) : 0
      if (requestId > 0 && requestId < latestRequestId) return false
      if (requestId > 0) latestRequestId = requestId
      return true
    }
    const handleNetworkFailure = (event: Event) => { if (acceptNetworkEvent(event)) setOnline(false) }
    const handleNetworkRecovered = (event: Event) => { if (acceptNetworkEvent(event)) markOnline(true) }
    const handleManualOnline = () => markOnline(false)

    window.addEventListener('online', handleBrowserOnline)
    window.addEventListener('offline', handleBrowserOffline)
    window.addEventListener('drive:network-failure', handleNetworkFailure)
    window.addEventListener('drive:network-recovered', handleNetworkRecovered)
    window.addEventListener('drive:online', handleManualOnline)

    return () => {
      window.removeEventListener('online', handleBrowserOnline)
      window.removeEventListener('offline', handleBrowserOffline)
      window.removeEventListener('drive:network-failure', handleNetworkFailure)
      window.removeEventListener('drive:network-recovered', handleNetworkRecovered)
      window.removeEventListener('drive:online', handleManualOnline)
      if (recoveryTimer !== undefined) window.clearTimeout(recoveryTimer)
    }
  }, [])

  if (recovered) return <aside className="network-status recovered" role="status" aria-live="polite"><div><strong>You&apos;re back online</strong><span>Drive can refresh your latest data.</span></div></aside>
  if (online) return null
  return <aside className="network-status" role="alert" aria-live="assertive"><div><strong>You&apos;re offline</strong><span>Drive requires an internet connection to access your files.</span></div><button type="button" onClick={() => { if (navigator.onLine) window.dispatchEvent(new Event('drive:online')) }}>Retry</button></aside>
}

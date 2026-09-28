'use client'

import { useEffect, useState } from 'react'

export function NetworkStatus() {
  const [online, setOnline] = useState(() => typeof navigator === 'undefined' ? true : navigator.onLine)
  const [recovered, setRecovered] = useState(false)

  useEffect(() => {
    const update = () => { const next = navigator.onLine; setOnline(previous => { if (!previous && next) { setRecovered(true); window.setTimeout(() => setRecovered(false), 2400); window.dispatchEvent(new Event('drive:online')) } return next }) }
    update()
    window.addEventListener('online', update)
    window.addEventListener('offline', update)
    return () => {
      window.removeEventListener('online', update)
      window.removeEventListener('offline', update)
    }
  }, [])

  if (recovered) return <aside className="network-status recovered" role="status" aria-live="polite"><div><strong>You&apos;re back online</strong><span>Drive can refresh your latest data.</span></div></aside>
  if (online) return null
  return <aside className="network-status" role="alert" aria-live="assertive"><div><strong>You&apos;re offline</strong><span>Drive requires an internet connection to access your files.</span></div><button type="button" onClick={() => { if (navigator.onLine) window.dispatchEvent(new Event('drive:online')); else setOnline(false) }}>Retry</button></aside>
}

'use client'

import { useEffect, useState } from 'react'
import { Download } from 'lucide-react'

interface InstallPromptEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

export function InstallDrive() {
  const [promptEvent, setPromptEvent] = useState<InstallPromptEvent | null>(null)
  const [installed, setInstalled] = useState(() => typeof window !== 'undefined' && (window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true))
  const [ios] = useState(() => typeof navigator !== 'undefined' && /iphone|ipad|ipod/i.test(navigator.userAgent) && /safari/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent))
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    const onPrompt = (event: Event) => { event.preventDefault(); setPromptEvent(event as InstallPromptEvent) }
    const onInstalled = () => { setInstalled(true); setPromptEvent(null) }
    window.addEventListener('beforeinstallprompt', onPrompt)
    window.addEventListener('appinstalled', onInstalled)
    return () => { window.removeEventListener('beforeinstallprompt', onPrompt); window.removeEventListener('appinstalled', onInstalled) }
  }, [])

  const install = async () => {
    if (!promptEvent) return
    setBusy(true)
    await promptEvent.prompt()
    const choice = await promptEvent.userChoice
    if (choice.outcome === 'accepted') setInstalled(true)
    setPromptEvent(null)
    setBusy(false)
  }

  return <div className="settings-form install-drive"><div><h3>Install Drive</h3><p>Keep Drive one tap away on this device. Drive stays online-only and always uses the latest account data.</p></div>{installed ? <p className="form-hint" role="status">Drive is installed on this device.</p> : promptEvent ? <button className="button secondary" type="button" onClick={() => void install()} disabled={busy}><Download size={15} />{busy ? 'Waiting…' : 'Install Drive'}</button> : ios ? <p className="form-hint">In Safari, tap Share, then choose <strong>Add to Home Screen</strong>.</p> : <p className="form-hint">Install is available from your browser menu on supported devices.</p>}</div>
}

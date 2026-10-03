'use client'

import { useEffect, useState } from 'react'
import { AppWindow, BellRing, CircleAlert, CircleCheck, CircleHelp, Database, HardDrive, RefreshCw, Server, ShieldCheck, Workflow } from 'lucide-react'
import { api, ApiError } from '@/lib/api/client'
import { env } from '@/config/env'
import { pushService, type PushState } from '@/services/push-service'

type ServiceState = 'connected' | 'active' | 'degraded' | 'unavailable' | 'not_configured' | 'unknown'
type HealthResponse = {
  status: string
  version: string
  environment: string
  services: {
    database: { status: ServiceState; driver?: string }
    storage: { status: ServiceState }
    queue: { status: ServiceState; driver?: string | null }
  }
}

const stateLabels: Record<ServiceState, string> = {
  connected: 'Terhubung', active: 'Aktif', degraded: 'Terganggu', unavailable: 'Tidak tersedia', not_configured: 'Tidak dikonfigurasi', unknown: 'Belum diperiksa',
}

export function SystemConnectionStatus({ isAdmin }: { isAdmin: boolean }) {
  const [health, setHealth] = useState<HealthResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [pushState] = useState<PushState>(() => pushService.permission())
  const [serviceWorkerState, setServiceWorkerState] = useState<ServiceState>('unknown')

  const loadHealth = async () => {
    setLoading(true)
    setError('')
    try { setHealth(await api.get<HealthResponse>('/api/health')) } catch (reason) { setHealth(null); setError(reason instanceof ApiError && reason.status === 0 ? 'API tidak dapat dijangkau.' : 'Status layanan belum dapat diperiksa.') } finally { setLoading(false) }
  }

  useEffect(() => {
    const healthTimer = window.setTimeout(() => void loadHealth(), 0)
    if (!('serviceWorker' in navigator)) {
      const unsupportedTimer = window.setTimeout(() => setServiceWorkerState('unavailable'), 0)
      return () => { window.clearTimeout(healthTimer); window.clearTimeout(unsupportedTimer) }
    }
    void navigator.serviceWorker.getRegistration('/').then(registration => setServiceWorkerState(registration ? 'active' : 'degraded')).catch(() => setServiceWorkerState('unavailable'))
    return () => window.clearTimeout(healthTimer)
  }, [])

  const serviceRows = health ? [
    { icon: Server, label: 'API', state: 'connected' as ServiceState, detail: 'Drive API' },
    { icon: Database, label: 'Database', state: health.services.database.status, detail: isAdmin ? `${health.services.database.driver ?? 'Database'} · ${stateLabels[health.services.database.status]}` : undefined },
    { icon: HardDrive, label: 'Storage', state: health.services.storage.status, detail: isAdmin ? `Private storage · ${stateLabels[health.services.storage.status]}` : undefined },
    { icon: Workflow, label: 'Queue', state: health.services.queue.status, detail: isAdmin ? `${health.services.queue.driver === 'database' ? 'Database queue' : 'Queue'} · Tersedia` : undefined },
    { icon: BellRing, label: 'Push notification', state: pushStateToServiceState(pushState), detail: pushState === 'granted' ? 'Izin browser aktif' : undefined },
    { icon: AppWindow, label: 'Service Worker / PWA', state: serviceWorkerState, detail: isAdmin && serviceWorkerState === 'active' ? 'Terdaftar di browser' : undefined },
  ] : [
    { icon: Server, label: 'API', state: 'unavailable' as ServiceState, detail: 'API tidak dapat dijangkau' },
    { icon: Database, label: 'Database', state: 'unknown' as ServiceState },
    { icon: HardDrive, label: 'Storage', state: 'unknown' as ServiceState },
    { icon: Workflow, label: 'Queue', state: 'unknown' as ServiceState },
    { icon: BellRing, label: 'Push notification', state: pushStateToServiceState(pushState), detail: pushState === 'granted' ? 'Izin browser aktif' : undefined },
    { icon: AppWindow, label: 'Service Worker / PWA', state: serviceWorkerState },
  ]

  return <section className="about-status-section" aria-labelledby="connection-status-title"><div className="about-section-heading"><div><span className="eyebrow">Sistem</span><h3 id="connection-status-title">Status koneksi</h3><p>Status ringkas layanan Drive di perangkat dan backend.</p></div><button type="button" className="button secondary about-status-refresh" onClick={() => void loadHealth()} disabled={loading}><RefreshCw size={14} className={loading ? 'spin' : undefined} />{loading ? 'Memeriksa…' : 'Periksa ulang'}</button></div>{error && <p className="about-status-error" role="status">{error} Layanan lain ditampilkan sebagai belum diperiksa.</p>}<ul className="about-status-list">{serviceRows.map(row => <li key={row.label}><row.icon className="about-service-icon" size={17} aria-hidden="true" /><span>{row.label}</span><small>{row.detail ?? stateLabels[row.state]}</small><StatusIcon state={row.state} /></li>)}</ul></section>
}

function pushStateToServiceState(state: PushState): ServiceState {
  if (state === 'granted') return 'active'
  if (state === 'default') return env.vapidPublicKey ? 'degraded' : 'not_configured'
  if (state === 'denied') return 'degraded'
  return 'unavailable'
}

function StatusIcon({ state }: { state: ServiceState }) {
  const Icon = state === 'connected' || state === 'active' ? CircleCheck : state === 'degraded' ? CircleAlert : state === 'unknown' || state === 'not_configured' ? CircleHelp : ShieldCheck
  return <Icon className={`about-status-icon ${state}`} size={15} aria-hidden="true" />
}

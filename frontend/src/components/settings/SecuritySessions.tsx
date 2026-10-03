'use client'

import { useEffect, useState } from 'react'
import { sessionsApi as securityService } from '@/features/security/api/sessions.api'
import type { SecuritySession } from '@/features/security/types/session.types'

function formatLastActive(value: string): string {
  return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

export function SecuritySessions({ onNotice }: { onNotice: (message: string) => void }) {
  const [sessions, setSessions] = useState<SecuritySession[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [busyId, setBusyId] = useState<string | null>(null)
  const [revokingOthers, setRevokingOthers] = useState(false)

  const loadSessions = async () => {
    setLoading(true)
    setError('')
    try {
      setSessions(await securityService.getSessions())
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Sesi aktif tidak dapat dimuat.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    let cancelled = false
    void securityService.getSessions().then(value => {
      if (!cancelled) setSessions(value)
    }).catch(reason => {
      if (!cancelled) setError(reason instanceof Error ? reason.message : 'Sesi aktif tidak dapat dimuat.')
    }).finally(() => {
      if (!cancelled) setLoading(false)
    })
    return () => { cancelled = true }
  }, [])

  const revokeSession = async (session: SecuritySession) => {
    if (session.isCurrent || !window.confirm(`Keluar dari ${session.deviceLabel}?`)) return
    setBusyId(session.id)
    setError('')
    try {
      await securityService.revokeSession(session.id)
      setSessions(current => current.filter(item => item.id !== session.id))
      onNotice('Perangkat berhasil dikeluarkan dari akun.')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Perangkat tidak dapat dikeluarkan.')
    } finally {
      setBusyId(null)
    }
  }

  const revokeOthers = async () => {
    if (!window.confirm('Keluar dari semua perangkat lain? Sesi pada perangkat ini akan tetap aktif.')) return
    setRevokingOthers(true)
    setError('')
    try {
      const result = await securityService.revokeOtherSessions()
      setSessions(current => current.filter(session => session.isCurrent))
      onNotice(result.revokedCount > 0 ? `${result.revokedCount} perangkat lain berhasil dikeluarkan.` : 'Tidak ada perangkat lain yang perlu dikeluarkan.')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Perangkat lain tidak dapat dikeluarkan.')
    } finally {
      setRevokingOthers(false)
    }
  }

  return <div className="session-list">
    <div className="session-list-heading">
      <div>
        <h3>Sesi aktif</h3>
        <p>Kelola perangkat yang sedang masuk ke akun Anda.</p>
      </div>
      {!loading && sessions.some(session => !session.isCurrent) && <button type="button" className="button secondary" onClick={() => void revokeOthers()} disabled={revokingOthers}>{revokingOthers ? 'Mengeluarkan…' : 'Keluar dari semua perangkat lain'}</button>}
    </div>
    {loading && <p className="form-hint" role="status" aria-live="polite">Memuat sesi aktif…</p>}
    {!loading && error && <div className="session-feedback error" role="alert"><span>{error}</span><button type="button" className="text-action" onClick={() => void loadSessions()}>Coba lagi</button></div>}
    {!loading && !error && sessions.length === 0 && <p className="form-hint">Tidak ada sesi aktif.</p>}
    {!loading && !error && sessions.length > 0 && <div className="security-session-list">{sessions.map(session => <div className="security-session" key={session.id}>
      <div className="security-session-copy"><strong>{session.deviceLabel}</strong><span>{session.browser} · {session.os}</span><span>{session.ipAddress ?? 'Alamat IP tidak tersedia'} · Terakhir aktif {formatLastActive(session.lastActiveAt)}</span></div>
      <div className="security-session-actions">{session.isCurrent ? <small className="session-current">Perangkat ini</small> : <><small className={session.approximateStatus === 'active' ? 'session-current' : ''}>{session.approximateStatus === 'active' ? 'Aktif' : 'Baru-baru ini'}</small><button type="button" className="button secondary" onClick={() => void revokeSession(session)} disabled={busyId === session.id}>{busyId === session.id ? 'Mengeluarkan…' : 'Keluar dari perangkat ini'}</button></>}</div>
    </div>)}</div>}
  </div>
}

'use client'

import { useEffect, useState } from 'react'
import { sessionsApi as securityService } from '@/features/security/api/sessions.api'
import type { SecuritySession } from '@/features/security/types/session.types'

export function SecuritySessions({ onNotice }: { onNotice: (message: string) => void }) {
  const [sessions, setSessions] = useState<SecuritySession[]>([])
  const [loading, setLoading] = useState(true)
  useEffect(() => { void securityService.getSessions().then(setSessions).catch(error => onNotice(error instanceof Error ? error.message : 'Unable to load recent devices.')).finally(() => setLoading(false)) }, [onNotice])
  return <div className="session-list"><h3>Recent devices</h3>{loading ? <p className="form-hint" role="status">Loading recent devices…</p> : sessions.length ? <div className="security-session-list">{sessions.map(session => <div className="security-session" key={session.id}><div><strong>{session.device}</strong><span>{session.ipAddress ?? 'IP unavailable'} · {new Date(session.lastActiveAt).toLocaleString()}</span></div>{session.isCurrent && <small>Current</small>}</div>)}</div> : <p className="form-hint">No recent sessions were found.</p>}</div>
}

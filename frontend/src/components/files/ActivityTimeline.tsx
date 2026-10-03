'use client'

import { useEffect, useMemo, useState } from 'react'
import { RefreshCw } from 'lucide-react'
import { activityApi } from '@/features/activity/api/activity.api'
import { formatDate, formatRelative } from '@/lib/format'
import type { CloudActivity, CloudItemKind } from '@/types/cloud'

type ActivityTimelineProps = { resourceId: string; resourceType: CloudItemKind; compact?: boolean }

export function ActivityTimeline({ resourceId, resourceType, compact = false }: ActivityTimelineProps) {
  const [activities, setActivities] = useState<CloudActivity[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    let active = true
    void activityApi.list(50, { type: resourceType, id: resourceId })
      .then(value => { if (active) { setActivities(value); setError('') } })
      .catch(() => { if (active) setError('Gagal memuat aktivitas.') })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [attempt, resourceId, resourceType])

  const groups = useMemo(() => groupActivities(activities), [activities])
  const retry = () => { setLoading(true); setError(''); setAttempt(value => value + 1) }

  return <section className={`resource-activity${compact ? ' resource-activity-compact' : ''}`} aria-labelledby="resource-activity-title">
    <div className="resource-activity-heading"><div><span className="eyebrow">Riwayat resource</span><h3 id="resource-activity-title">Aktivitas</h3></div></div>
    {loading ? <div className="resource-activity-state" role="status"><RefreshCw className="spin" size={16} /><span>Memuat aktivitas…</span></div> : error ? <div className="resource-activity-state" role="alert"><span>{error}</span><button className="text-action" type="button" onClick={retry}>Coba lagi</button></div> : groups.length === 0 ? <p className="resource-activity-empty">Belum ada aktivitas.</p> : <div className="resource-activity-groups">{groups.map(group => <section key={group.label}><h4>{group.label}</h4><ol>{group.items.map(activity => <ActivityEntry key={activity.id} activity={activity} />)}</ol></section>)}</div>}
  </section>
}

function ActivityEntry({ activity }: { activity: CloudActivity }) {
  return <li><span className="resource-activity-dot" aria-hidden="true" /><div><p>{describeActivity(activity)}</p><span>{activity.actorName ? `oleh ${activity.actorName} · ` : ''}<time dateTime={activity.timestamp} title={formatDate(activity.timestamp)}>{formatRelative(activity.timestamp)}</time></span></div></li>
}

function describeActivity(activity: CloudActivity): string {
  const metadata = parseMetadata(activity.metadata)
  if (activity.action === 'renamed') return metadata.from && metadata.to ? `Nama diubah dari “${metadata.from}” menjadi “${metadata.to}”` : 'Nama diubah'
  if (activity.action === 'moved') return metadata.to ? `Dipindahkan ke ${metadata.to}` : 'Dipindahkan'
  if (activity.action === 'shared') return metadata.recipientName && metadata.permission ? `Dibagikan kepada ${metadata.recipientName} sebagai ${metadata.permission}` : 'Dibagikan'
  if (activity.action === 'sharing-stopped') return metadata.recipientName ? `Akses ${metadata.recipientName} dicabut` : 'Akses berbagi dicabut'
  if (activity.action === 'deleted') return 'Dipindahkan ke Sampah'
  if (activity.action === 'restored') return 'Dipulihkan dari Sampah'
  if (activity.action === 'uploaded') return 'Diunggah'
  if (activity.action === 'downloaded') return 'Diunduh'
  if (activity.action === 'starred') return 'Ditambahkan ke Berbintang'
  if (activity.action === 'unstarred') return 'Dihapus dari Berbintang'
  if (activity.action === 'created') return 'Folder dibuat'
  if (activity.action === 'permanently-deleted') return 'Dihapus permanen'
  return 'Aktivitas diperbarui'
}

function parseMetadata(value?: string): Record<string, string> {
  if (!value) return {}
  try {
    const parsed: unknown = JSON.parse(value)
    if (!parsed || typeof parsed !== 'object') return {}
    return Object.fromEntries(Object.entries(parsed).filter(([, item]) => typeof item === 'string'))
  } catch {
    return {}
  }
}

function groupActivities(items: CloudActivity[]) {
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1)
  const groups: Array<{ label: string; items: CloudActivity[] }> = []
  items.forEach(activity => {
    const date = new Date(activity.timestamp); const label = date >= today ? 'Hari ini' : date >= yesterday ? 'Kemarin' : 'Sebelumnya'
    const group = groups.find(entry => entry.label === label)
    if (group) group.items.push(activity); else groups.push({ label, items: [activity] })
  })
  return groups
}

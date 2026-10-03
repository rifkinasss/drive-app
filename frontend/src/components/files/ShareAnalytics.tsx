'use client'

import { Download, Eye, RefreshCw } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { publicLinksApi } from '@/features/sharing/api/public-links.api'
import type { PublicShareLink } from '@/types/cloud'

type Analytics = { views: number; downloads: number; lastAccessedAt: string | null; createdAt: string | null; expiresAt: string | null; status: 'active' | 'expired' | 'revoked' }

export function ShareAnalytics({ link }: { link: PublicShareLink }) {
  const analyticsId = link.linkId ?? link.id
  const fallback: Analytics = { views: link.views ?? 0, downloads: link.downloads ?? 0, lastAccessedAt: link.lastAccessedAt ?? null, createdAt: link.createdAt || null, expiresAt: link.expiresAt ?? null, status: link.status ?? (link.enabled ? 'active' : 'revoked') }
  const [data, setData] = useState<Analytics>(fallback)
  const [loading, setLoading] = useState(Boolean(analyticsId))
  const [error, setError] = useState('')
  const load = useCallback(async () => {
    if (!analyticsId) return
    setLoading(true)
    setError('')
    try { setData(await publicLinksApi.getAnalytics(analyticsId)) } catch (reason) { setError(reason instanceof Error ? reason.message : 'Analitik tidak dapat dimuat.') } finally { setLoading(false) }
  }, [analyticsId])
  // Analytics is loaded when the owner opens the existing share details surface.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load() }, [load])

  return <section className="share-analytics" aria-labelledby={`share-analytics-${link.id}`}>
    <div className="share-section-heading"><strong id={`share-analytics-${link.id}`}>Analitik berbagi</strong>{loading && <RefreshCw className="spin" size={14} aria-label="Memuat analitik" />}</div>
    {error ? <div className="share-analytics-error" role="alert"><span>{error}</span><button className="text-action" type="button" onClick={() => void load()}>Coba lagi</button></div> : <dl className="share-analytics-grid">
      <div><dt><Eye size={13} /> Views</dt><dd>{data.views}</dd></div>
      <div><dt><Download size={13} /> Downloads</dt><dd>{data.downloads}</dd></div>
      <div><dt>Terakhir diakses</dt><dd>{data.lastAccessedAt ? relativeId(data.lastAccessedAt) : 'Belum ada akses'}</dd></div>
      <div><dt>Status</dt><dd><span className={`share-status ${data.status}`}>{statusLabel(data.status)}</span></dd></div>
      <div><dt>Dibuat</dt><dd>{data.createdAt ? dateId(data.createdAt) : '—'}</dd></div>
      <div><dt>Berakhir</dt><dd>{expiryLabel(data.expiresAt, data.status)}</dd></div>
    </dl>}
  </section>
}

function statusLabel(status: Analytics['status']) { return status === 'active' ? 'Aktif' : status === 'expired' ? 'Berakhir' : 'Dicabut' }
function dateId(value: string) { return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(value)) }
function relativeId(value: string) { const seconds = (Date.now() - new Date(value).getTime()) / 1000; const ranges: Array<[number, Intl.RelativeTimeFormatUnit]> = [[31536000, 'year'], [2592000, 'month'], [86400, 'day'], [3600, 'hour'], [60, 'minute']]; const range = ranges.find(([size]) => Math.abs(seconds) >= size); if (!range) return 'Baru saja'; return new Intl.RelativeTimeFormat('id-ID', { numeric: 'auto' }).format(-Math.round(seconds / range[0]), range[1]) }
function expiryLabel(value: string | null, status: Analytics['status']) { if (!value) return 'Tidak diatur'; const date = new Date(value); if (status === 'expired') return `Berakhir ${dateId(value)}`; const days = Math.ceil((date.getTime() - Date.now()) / 86400000); return days <= 0 ? 'Berakhir hari ini' : `Berakhir dalam ${days} hari` }

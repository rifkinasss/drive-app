'use client'

import { useCallback, useEffect, useState } from 'react'
import { Info, RefreshCw, Share2, Star, Trash2, X } from 'lucide-react'
import { filesApi as cloudService } from '@/features/files/api/files.api'
import { publicLinksApi } from '@/features/sharing/api/public-links.api'
import { ActivityTimeline } from '@/components/files/ActivityTimeline'
import { ShareAnalytics } from '@/components/files/ShareAnalytics'
import type { CloudDetails } from '@/features/files/types/file.types'
import { formatBytes, formatDate } from '@/lib/format'
import type { CloudItem, PublicShareLink } from '@/types/cloud'

export function FileDetailsPanel({ item, onClose }: { item?: CloudItem | null; onClose: () => void }) {
  const [details, setDetails] = useState<CloudDetails | null>(null)
  const [publicLink, setPublicLink] = useState<PublicShareLink | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const load = useCallback(async () => {
    if (!item) return
    setLoading(true)
    setError('')
    try {
      const next = await cloudService.getDetails(item)
      setDetails(next)
      setPublicLink(next.publicLinkEnabled ? await publicLinksApi.get(item) : null)
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Unable to load file details.') } finally { setLoading(false) }
  }, [item])
  // The effect synchronizes the panel with the selected server resource.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load() }, [load])
  useEffect(() => { const close = (event: KeyboardEvent) => { if (event.key === 'Escape') onClose() }; document.addEventListener('keydown', close); return () => document.removeEventListener('keydown', close) }, [onClose])

  return <aside className="file-details-panel" aria-label="File details" aria-busy={loading}><div className="file-details-panel-head"><div><span className="eyebrow">Details</span><h2>{item?.name ?? 'No item selected'}</h2></div><button className="icon-button" type="button" onClick={onClose} aria-label="Close details"><X size={18} /></button></div>{!item ? <div className="file-details-empty"><Info size={22} /><p>Select a file or folder to see its details.</p></div> : loading ? <div className="file-details-state" role="status"><RefreshCw className="spin" size={18} /><span>Loading details…</span></div> : error ? <div className="file-details-state error" role="alert"><Info size={18} /><p>{error}</p><button className="button secondary" type="button" onClick={() => void load()}>Try again</button></div> : details ? <div className="file-details-content"><div className="file-details-summary"><strong>{details.name}</strong><span>{details.type === 'folder' ? 'Folder' : details.extension?.toUpperCase() || details.mimeType}</span></div><dl>{[['Type', details.type === 'folder' ? 'Folder' : details.mimeType], ['Size', details.type === 'folder' ? 'Folder' : formatBytes(details.sizeBytes ?? 0)], ['Owner', details.owner.name], ['Location', details.location?.name ?? 'My Files'], ['Created', formatDate(details.createdAt)], ['Updated', formatDate(details.updatedAt)], ['Starred', details.starred ? 'Yes' : 'No'], ['Shared', details.shared || details.publicLinkEnabled ? 'Yes' : 'No'], ...(details.trashedAt ? [['Trash', `Moved to Trash ${formatDate(details.trashedAt)}`]] : [])].map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl><div className="file-details-statuses">{details.starred && <span><Star size={13} fill="currentColor" /> Starred</span>}{(details.shared || details.publicLinkEnabled) && <span><Share2 size={13} /> Shared</span>}{details.trashedAt && <span><Trash2 size={13} /> In Trash</span>}</div>{publicLink && <ShareAnalytics link={publicLink} />}<ActivityTimeline resourceId={item.id} resourceType={item.kind} compact /></div> : null}</aside>
}

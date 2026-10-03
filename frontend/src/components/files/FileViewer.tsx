'use client'
/* eslint-disable @next/next/no-img-element -- API previews are blob URLs. */

import { useCallback, useEffect, useState } from 'react'
import { Archive, Download, FileCode2, FileImage, FileText, FolderOpen, Info, Maximize2, Minus, MoreHorizontal, Plus, Share2, Star, Trash2, X } from 'lucide-react'
import type { CloudItem } from '@/types/cloud'
import { formatBytes, formatDate } from '@/lib/format'
import { ApiError } from '@/lib/api/api-error'
import { filesApi as cloudService } from '@/features/files/api/files.api'
import { publicLinksApi as shareService } from '@/features/sharing/api/public-links.api'
import { ActivityTimeline } from '@/components/files/ActivityTimeline'

type ViewerProps = { item: CloudItem; onClose: () => void; onDownload?: () => void; onStar?: () => void; onShare?: () => void; onShowInFolder?: () => void; onTrash?: () => void; publicMode?: boolean; publicToken?: string; publicPassword?: string }
type PreviewComponentProps = { item: CloudItem; zoom: number; setZoom: (zoom: number) => void }
const TEXT_PREVIEW_LIMIT_BYTES = 2 * 1024 * 1024

export function FileViewer({ item, onClose, onDownload, onStar, onShare, onShowInFolder, onTrash, publicMode = false, publicToken, publicPassword }: ViewerProps) {
  const [detailsOpen, setDetailsOpen] = useState(true)
  const [menuOpen, setMenuOpen] = useState(false)
  const [zoom, setZoom] = useState(100)
  const [error, setError] = useState<string | null>(null)
  const [previewAttempt, setPreviewAttempt] = useState(0)
  const handlePreviewError = useCallback((previewError: unknown) => {
    if (previewError instanceof ApiError) {
      if (previewError.status === 401 || previewError.status === 403) return setError('You do not have permission to preview this file.')
      if (previewError.status === 404) return setError('This file is no longer available.')
      if (previewError.status === 0) return setError('The preview could not be loaded. Check your connection and try again.')
      if (previewError.status >= 500) return setError('Drive could not load this preview right now.')
    }
    setError('We could not render this file preview.')
  }, [])
  const setClampedZoom = (value: number) => setZoom(Math.min(180, Math.max(60, value)))
  const toggleMenu = () => setMenuOpen(value => !value)
  const retryPreview = () => { setError(null); setPreviewAttempt(value => value + 1) }

  useEffect(() => {
    const listener = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
      if (event.key === '+' || event.key === '=') setClampedZoom(zoom + 10)
      if (event.key === '-') setClampedZoom(zoom - 10)
    }
    document.addEventListener('keydown', listener)
    return () => document.removeEventListener('keydown', listener)
  }, [onClose, zoom])

  return <section className="file-viewer" aria-label={`Preview ${item.name}`}>
    <header className="file-viewer-header">
      <div className="file-viewer-title"><span className="file-viewer-icon" aria-hidden="true"><FileIcon item={item} /></span><div><strong title={item.name}>{item.name}</strong><span title={publicMode ? 'Shared via Drive by NasLabs' : item.path}>{publicMode ? 'Shared via Drive by NasLabs' : item.path}</span></div></div>
      <div className="file-viewer-actions">
        <button className="viewer-action viewer-desktop-action" onClick={onDownload} aria-label="Download file"><Download size={15} /><span>Download</span></button>
        {!publicMode && onShare && <button className="viewer-action viewer-desktop-action" onClick={onShare} aria-label="Share file"><Share2 size={15} /><span>Share</span></button>}
        {!publicMode && <button className={`viewer-action viewer-desktop-action ${item.starred ? 'active' : ''}`} onClick={onStar} aria-label={item.starred ? 'Unstar file' : 'Star file'}><Star size={15} fill={item.starred ? 'currentColor' : 'none'} /></button>}
        {!publicMode && <div className="viewer-menu-wrap"><button className="viewer-action" onClick={toggleMenu} aria-label="More file actions" aria-expanded={menuOpen} aria-controls="file-viewer-menu"><MoreHorizontal size={17} /></button>{menuOpen && <ViewerMenu id="file-viewer-menu" detailsOpen={detailsOpen} onDetails={() => { setDetailsOpen(value => !value); setMenuOpen(false) }} onShowInFolder={() => { onShowInFolder?.(); setMenuOpen(false) }} onTrash={() => { onTrash?.(); setMenuOpen(false) }} />}</div>}
        <button className="viewer-close" onClick={onClose} aria-label="Close file viewer"><X size={18} /></button>
      </div>
    </header>
    <div className={`file-viewer-body ${detailsOpen ? '' : 'details-hidden'}`}>
      <div className="file-viewer-preview"><div className="file-viewer-canvas">{error ? <PreviewError message={error} onRetry={retryPreview} onDownload={onDownload} /> : <PreviewResolver key={`${item.id}-${previewAttempt}`} item={item} zoom={zoom} setZoom={setClampedZoom} publicMode={publicMode} publicToken={publicToken} publicPassword={publicPassword} onError={handlePreviewError} onDownload={onDownload} />}</div><ViewerToolbar item={item} zoom={zoom} setZoom={setClampedZoom} detailsOpen={detailsOpen} onDetails={() => setDetailsOpen(value => !value)} /></div>
      {detailsOpen && <FileViewerDetails item={item} publicMode={publicMode} />}
    </div>
    <div className="file-viewer-mobile-actions" aria-label="File actions">{onShare && <button className="viewer-mobile-action" onClick={onShare} aria-label="Share file"><Share2 size={17} /><span>Share</span></button>}<button className="viewer-mobile-action" onClick={onDownload} aria-label="Download file"><Download size={17} /><span>Download</span></button>{!publicMode && <button className="viewer-mobile-action" onClick={toggleMenu} aria-label="More file actions" aria-expanded={menuOpen} aria-controls="file-viewer-menu"><MoreHorizontal size={17} /><span>More</span></button>}</div>
  </section>
}

function PreviewResolver({ item, zoom, publicMode, publicToken, publicPassword, onError, onDownload }: PreviewComponentProps & { publicMode: boolean; publicToken?: string; publicPassword?: string; onError: (error: unknown) => void; onDownload?: () => void }) {
  const [status, setStatus] = useState<'loading' | 'loaded' | 'too-large'>('loading')
  const [url, setUrl] = useState('')
  const [text, setText] = useState('')
  const textPreview = ['text/plain', 'text/markdown', 'application/json', 'text/csv', 'application/xml'].includes(item.mimeType) || /\.(txt|md|markdown|json|csv|log|ya?ml|sql|[cm]?[jt]sx?|css)$/i.test(item.name)
  const supported = textPreview || item.fileType === 'image' || item.extension === 'pdf' || item.fileType === 'video' || item.mimeType.startsWith('audio/')
  const textPreviewTooLarge = textPreview && item.size > TEXT_PREVIEW_LIMIT_BYTES

  useEffect(() => {
    let objectUrl = ''
    let alive = true
    if (!supported || textPreviewTooLarge) return () => { alive = false }
    const request = publicMode && publicToken ? shareService.publicPreview(publicToken, item.id, publicPassword) : cloudService.preview(item)
    void request.then(async blob => {
      if (!alive) return
      if (textPreview) {
        const content = await blob.text()
        if (alive) { setText(content); setStatus('loaded') }
        return
      }
      objectUrl = URL.createObjectURL(blob)
      setUrl(objectUrl)
      setStatus('loaded')
    }).catch(error => { if (alive) onError(error) })
    return () => { alive = false; if (objectUrl) URL.revokeObjectURL(objectUrl) }
  }, [item, publicMode, publicPassword, publicToken, onError, supported, textPreviewTooLarge, textPreview])

  if (!supported) return <div className="unsupported-viewer"><FileText size={36} aria-hidden="true" /><h2>Preview unavailable</h2><p>This file type cannot be previewed.</p><small>{item.extension ? `.${item.extension}` : 'Unknown file type'} · {formatBytes(item.size)}</small>{onDownload && <button className="viewer-link" onClick={onDownload}>Download file</button>}</div>
  if (status === 'loading') return <p role="status">Loading preview…</p>
  if (textPreviewTooLarge || status === 'too-large') return <div className="unsupported-viewer"><FileText size={36} aria-hidden="true" /><h2>Preview unavailable</h2><p>This text file is too large to open safely in the browser.</p><small>{item.mimeType} · {formatBytes(item.size)}</small>{onDownload && <button className="viewer-link" onClick={onDownload}>Download file</button>}</div>
  if (textPreview) return <pre className="source-viewer">{text}</pre>
  if (item.fileType === 'image') return <img src={url} alt={item.name} style={{ maxWidth: '100%', maxHeight: '70vh', objectFit: 'contain', transform: `scale(${zoom / 100})` }} />
  if (item.extension === 'pdf') return <iframe title={item.name} src={url} style={{ width: '100%', height: '70vh', border: 0 }} />
  if (item.fileType === 'video') return <video controls playsInline preload="metadata" src={url} style={{ maxWidth: '100%', maxHeight: '70vh', objectFit: 'contain', transform: `scale(${zoom / 100})` }} />
  if (item.mimeType.startsWith('audio/')) return <audio controls preload="metadata" src={url} />
  return null
}

function ViewerMenu({ id, detailsOpen, onDetails, onShowInFolder, onTrash }: { id: string; detailsOpen: boolean; onDetails: () => void; onShowInFolder: () => void; onTrash: () => void }) { return <div id={id} className="viewer-menu"><button onClick={onDetails}><Info size={14} />{detailsOpen ? 'Hide details' : 'Show details'}</button><button onClick={onShowInFolder}><FolderOpen size={14} />Show in folder</button><button className="danger-action" onClick={onTrash}><Trash2 size={14} />Move to Trash</button></div> }

function FileViewerDetails({ item, publicMode }: { item: CloudItem; publicMode: boolean }) {
  const rows = publicMode ? [['Name', item.name], ['Type', item.mimeType], ['Extension', item.extension ? `.${item.extension}` : '—'], ['Size', formatBytes(item.size)], ['Modified', formatDate(item.updatedAt)]] : [['Name', item.name], ['Type', item.mimeType], ['Extension', item.extension ? `.${item.extension}` : '—'], ['Size', formatBytes(item.size)], ['Location', item.path], ['Owner', 'Private to this account'], ['Created', formatDate(item.createdAt)], ['Modified', formatDate(item.updatedAt)], ['Status', item.deletedAt ? 'In Trash' : 'Available']]
  return <aside className="file-viewer-details" aria-label="File details"><div className="details-heading"><div><span>File details</span><strong>Properties</strong></div><Info size={16} aria-hidden="true" /></div><dl>{rows.map(([label, value]) => <div key={label}><dt>{label}</dt><dd title={value}>{value}</dd></div>)}</dl>{!publicMode && <ActivityTimeline resourceId={item.id} resourceType={item.kind} compact />}</aside>
}

function ViewerToolbar({ item, zoom, setZoom, detailsOpen, onDetails }: { item: CloudItem; zoom: number; setZoom: (zoom: number) => void; detailsOpen: boolean; onDetails: () => void }) {
  const supportsZoom = ['image', 'pdf', 'video'].includes(item.fileType) || item.extension === 'pdf'
  return <div className="viewer-toolbar"><div className="viewer-toolbar-left"><button className="viewer-tool" onClick={() => setZoom(zoom - 10)} disabled={!supportsZoom || zoom <= 60} aria-label="Zoom out"><Minus size={14} /></button><span>{supportsZoom ? `${zoom}%` : 'Preview'}</span><button className="viewer-tool" onClick={() => setZoom(zoom + 10)} disabled={!supportsZoom || zoom >= 180} aria-label="Zoom in"><Plus size={14} /></button>{supportsZoom && <button className="viewer-tool viewer-fit" onClick={() => setZoom(100)}><Maximize2 size={13} />Fit</button>}</div><div className="viewer-toolbar-right"><span>{item.extension.toUpperCase() || 'FILE'}</span><button className="viewer-tool details-toggle" onClick={onDetails} aria-label={detailsOpen ? 'Hide details' : 'Show details'} aria-pressed={detailsOpen}><Info size={14} />{detailsOpen ? 'Hide details' : 'Show details'}</button></div></div>
}

function PreviewError({ message, onRetry, onDownload }: { message: string; onRetry: () => void; onDownload?: () => void }) { return <div className="unsupported-viewer"><FileText size={34} aria-hidden="true" /><h2>Preview unavailable</h2><p>{message}</p><div className="preview-error-actions"><button className="viewer-link" onClick={onRetry}>Try again</button>{onDownload && <button className="viewer-link" onClick={onDownload}>Download file</button>}</div></div> }

function FileIcon({ item }: { item: CloudItem }) { return item.fileType === 'image' ? <FileImage size={18} /> : item.fileType === 'archive' ? <Archive size={18} /> : item.fileType === 'code' ? <FileCode2 size={18} /> : <FileText size={18} /> }

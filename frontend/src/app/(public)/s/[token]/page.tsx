'use client'

import { use, useEffect, useState } from 'react'
import { Download, Eye, EyeOff, File, FileImage, FileText, Folder, Link2, X } from 'lucide-react'
import { FileViewer } from '@/components/files/FileViewer'
import type { CloudItem, PublicSharedItem } from '@/types/cloud'
import { formatBytes, formatDate } from '@/lib/format'
import { publicLinksApi as shareService } from '@/features/sharing/api/public-links.api'
import { useAuthStore } from '@/stores/auth-store'
import { ApiError } from '@/lib/api/client'

export default function PublicSharePage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params)
  const [resolution, setResolution] = useState<{ status: 'active'; ownerDisplayName: string; item: PublicSharedItem } | { status: 'unavailable' } | null>(null)
  const [current, setCurrent] = useState<PublicSharedItem | null>(null)
  const [breadcrumbs, setBreadcrumbs] = useState<PublicSharedItem[]>([])
  const [preview, setPreview] = useState<PublicSharedItem | null>(null)
  const [passwordRequired, setPasswordRequired] = useState(false)
  const [password, setPassword] = useState('')
  const [passwordError, setPasswordError] = useState('')
  const [browseLoading, setBrowseLoading] = useState(false)
  const [browseError, setBrowseError] = useState('')
  const [pendingFolder, setPendingFolder] = useState<PublicSharedItem | null>(null)

  const resolve = async (secret?: string) => { setPasswordError(''); try { const data = await shareService.resolvePublicShare(token, secret); const root = { id: '', name: data.name, kind: data.type, fileType: data.mimeType?.startsWith('image/') ? 'image' : data.mimeType?.startsWith('video/') ? 'video' : data.mimeType?.startsWith('text/') ? 'document' : 'other', mimeType: data.mimeType ?? '', extension: data.name.includes('.') ? data.name.split('.').pop()!.toLowerCase() : '', size: data.sizeBytes ?? 0, modifiedAt: data.modifiedAt, allowDownload: data.allowDownload, children: [] } as PublicSharedItem; if (root.kind === 'folder') { const listing = await shareService.browsePublicShare(token, undefined, secret); root.id = listing.sharedRoot.id; root.children = publicChildren(listing) } setPasswordRequired(false); setResolution({ status: 'active', ownerDisplayName: data.ownerDisplayName, item: root }); setCurrent(root) } catch (reason) { if (reason instanceof ApiError && reason.status === 401) { setPasswordRequired(true); setPasswordError(secret ? 'That password is incorrect.' : '') } else setResolution({ status: 'unavailable' }) } }
  // The initial public-share lookup is intentionally tied to the route token.
  // eslint-disable-next-line react-hooks/set-state-in-effect, react-hooks/exhaustive-deps
  useEffect(() => { void resolve() }, [token])

  if (passwordRequired) return <PasswordState password={password} error={passwordError} onChange={setPassword} onSubmit={() => void resolve(password)} />
  if (!resolution) return <PublicState title="Loading shared item" description="Please wait while the link is checked." />
  if (resolution.status !== 'active') return <PublicState title="Link unavailable" description="This shared link is invalid or no longer available." />
  const activeItem = current ?? resolution.item
  const openFolder = (item: PublicSharedItem) => { setBrowseLoading(true); setBrowseError(''); setPendingFolder(item); void shareService.browsePublicShare(token, item.id, password).then(listing => { setBreadcrumbs((currentTrail) => [...currentTrail, item]); setCurrent({ ...item, children: publicChildren(listing) }); setPendingFolder(null) }).catch((reason) => { setBrowseError(reason instanceof Error ? reason.message : 'Folder contents could not be loaded.') }).finally(() => setBrowseLoading(false)) }
  const goTo = (index: number) => { setBrowseError(''); setPendingFolder(null); setBreadcrumbs((trail) => trail.slice(0, index)); setCurrent(index === 0 ? resolution.item : breadcrumbs[index - 1]) }
  return <div className="public-page"><PublicHeader /><main className="public-content"><div className="public-share-heading"><div><p className="public-kicker">Shared by {resolution.ownerDisplayName}</p><h1>{activeItem.name}</h1><p>{activeItem.kind === 'folder' ? `${activeItem.children.length} item${activeItem.children.length === 1 ? '' : 's'}` : `${activeItem.extension.toUpperCase() || 'FILE'} · ${formatBytes(activeItem.size)}`}</p></div><span className="public-viewer-label"><Link2 size={14} /> Viewer</span></div>{activeItem.kind === 'folder' ? <><nav className="public-breadcrumbs" aria-label="Shared folder breadcrumb"><button onClick={() => { setCurrent(resolution.item); setBreadcrumbs([]); setBrowseError(''); setPendingFolder(null) }}>{resolution.item.name}</button>{breadcrumbs.slice(1).map((item, index) => <span key={`${item.name}-${index}`}><span aria-hidden="true">/</span><button onClick={() => goTo(index + 1)}>{item.name}</button></span>)}</nav><div className="public-folder-list">{browseLoading ? <p className="public-empty" role="status">Loading folder contents…</p> : browseError ? <div className="public-empty" role="alert"><p>{browseError}</p><button className="button secondary" type="button" onClick={() => pendingFolder && openFolder(pendingFolder)}>Try again</button></div> : activeItem.children.length ? activeItem.children.map((child) => <button key={child.id ?? child.name} className="public-folder-item" onClick={() => child.kind === 'folder' ? openFolder(child) : setPreview(child)}><span className="public-item-icon"><PublicItemIcon item={child} /></span><span><strong>{child.name}</strong><small>{child.kind === 'folder' ? `${child.children.length} item${child.children.length === 1 ? '' : 's'}` : `${child.extension.toUpperCase() || 'FILE'} · ${formatBytes(child.size)}`}</small></span></button>) : <p className="public-empty">This folder is empty.</p>}</div></> : <PublicFileCard item={activeItem} onPreview={() => setPreview(activeItem)} onDownload={() => downloadPublicItem(token, activeItem, password)} />}</main><PublicFooter />{preview && <PublicPreview token={token} item={preview} password={password} onClose={() => setPreview(null)} onDownload={() => downloadPublicItem(token, preview, password)} />}</div>
}

function PublicHeader() { const currentUser = useAuthStore().currentUser; const [copyState, setCopyState] = useState<'idle' | 'success' | 'error'>('idle'); const copy = async () => { try { await navigator.clipboard.writeText(window.location.href); setCopyState('success'); window.setTimeout(() => setCopyState('idle'), 2200) } catch { setCopyState('error'); window.setTimeout(() => setCopyState('idle'), 2200) } }; return <header className="public-header"><div className="public-brand"><span aria-hidden="true"><Link2 size={15} /></span><div><strong>Drive</strong><small>by NasLabs</small></div></div><div className="public-header-actions"><button className="button secondary" type="button" onClick={() => void copy()} aria-live="polite">{copyState === 'success' ? 'Link copied' : copyState === 'error' ? "Couldn't copy link." : 'Copy link'}</button><a className="button secondary" href={currentUser ? '/home' : `/login?returnTo=${encodeURIComponent(typeof window === 'undefined' ? '/home' : window.location.pathname)}`}>Open in Drive</a></div></header> }
function PublicFooter() { return <footer className="public-footer"><span>Drive by NasLabs</span><span>© 2026 NasLabs</span></footer> }
function PublicState({ title, description }: { title: string; description: string }) { return <div className="public-page"><PublicHeader /><main className="public-state"><span className="public-state-icon"><Link2 size={20} /></span><h1>{title}</h1><p>{description}</p></main><PublicFooter /></div> }
function PasswordState({ password, error, onChange, onSubmit }: { password: string; error: string; onChange: (value: string) => void; onSubmit: () => void }) {
  const [show, setShow] = useState(false);
  return (
    <div className="public-page">
      <PublicHeader />
      <main className="public-state">
        <span className="public-state-icon"><Link2 size={20} /></span>
        <h1>Tautan Dilindungi Kata Sandi</h1>
        <p>Masukkan kata sandi yang diberikan pemilik berkas untuk mengakses tautan ini.</p>
        <form onSubmit={event => { event.preventDefault(); onSubmit(); }}>
          <div style={{ position: "relative", width: "100%", maxWidth: "320px", margin: "0 auto 12px" }}>
            <input className="text-input" type={show ? "text" : "password"} autoFocus value={password} onChange={event => onChange(event.target.value)} placeholder="Kata sandi tautan" aria-label="Share password" style={{ width: "100%", paddingRight: "40px" }} />
            <button type="button" onClick={() => setShow(!show)} aria-label={show ? "Sembunyikan kata sandi" : "Tampilkan kata sandi"} style={{ position: "absolute", right: "10px", top: "50%", transform: "translateY(-50%)", background: "none", border: "none", cursor: "pointer", color: "var(--muted, #666)" }}>
              {show ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          </div>
          {error && <p className="form-hint error" role="alert" style={{ marginBottom: "12px" }}>{error}</p>}
          <button className="button primary" type="submit" disabled={!password}>Buka Tautan</button>
        </form>
      </main>
      <PublicFooter />
    </div>
  );
}
function PublicFileCard({ item, onPreview, onDownload }: { item: PublicSharedItem; onPreview: () => void; onDownload: () => void }) { return <section className="public-file-card"><div className="public-file-preview"><PublicItemIcon item={item} /><span>{item.extension.toUpperCase() || 'FILE'}</span></div><div className="public-file-meta"><p>{item.name}</p><span>{item.mimeType} · {formatBytes(item.size)}</span><span>Modified {formatDate(item.modifiedAt)}</span></div><div className="public-actions"><button className="button secondary" onClick={onPreview}>Preview</button>{item.allowDownload !== false && <button className="button primary" onClick={onDownload}><Download size={15} />Download</button>}</div></section> }
function PublicPreview({ token, item, password, onClose, onDownload }: { token: string; item: PublicSharedItem; password?: string; onClose: () => void; onDownload: () => void }) { const adapter: CloudItem = { id: item.id ?? '', ownerId: 'public', name: item.name, kind: item.kind, fileType: item.fileType, mimeType: item.mimeType, extension: item.extension, size: item.size, parentId: null, path: '', createdAt: item.modifiedAt, updatedAt: item.modifiedAt, accessedAt: item.modifiedAt, starred: false, deletedAt: null, originalParentId: null, thumbnail: item.thumbnail }; return <div className="public-preview-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}><div className="public-preview-dialog"><button className="public-preview-close" onClick={onClose} aria-label="Close preview"><X size={18} /></button><FileViewer item={adapter} publicMode publicToken={token} publicPassword={password} onClose={onClose} onDownload={onDownload} /></div></div> }
function PublicItemIcon({ item }: { item: PublicSharedItem }) { if (item.kind === 'folder') return <Folder size={22} />; if (item.fileType === 'image') return <FileImage size={22} />; if (item.fileType === 'document') return <FileText size={22} />; return <File size={22} /> }
function publicChildren(listing: Awaited<ReturnType<typeof shareService.browsePublicShare>>): PublicSharedItem[] { return [...listing.folders.map(folder => ({ id: folder.id, name: folder.name, kind: 'folder' as const, fileType: 'other' as const, mimeType: '', extension: '', size: 0, modifiedAt: folder.updatedAt, allowDownload: listing.allowDownload, children: [] })), ...listing.files.map(file => ({ id: file.id, name: file.name, kind: 'file' as const, fileType: file.mimeType.startsWith('image/') ? 'image' as const : file.mimeType.startsWith('video/') ? 'video' as const : file.mimeType.startsWith('text/') ? 'document' as const : 'other' as const, mimeType: file.mimeType, extension: file.name.includes('.') ? file.name.split('.').pop()!.toLowerCase() : '', size: file.sizeBytes, modifiedAt: file.updatedAt, allowDownload: listing.allowDownload, children: [] }))] }
function downloadPublicItem(token: string, item: PublicSharedItem, password?: string) { void shareService.publicDownload(token, item.name, item.id || undefined, password).catch(() => undefined) }

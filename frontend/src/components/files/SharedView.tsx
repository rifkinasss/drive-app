'use client'

import { useEffect, useRef, useState, type MouseEvent } from 'react'
import { useSearchParams } from 'next/navigation'
import { Copy, FileText, FolderOpen, Grid2X2, Link2, List, LoaderCircle, MoreHorizontal, Search, Share2 } from 'lucide-react'
import { FileContextMenu, type FileMenuItem } from '@/components/files/FileContextMenu'
import { FilePreview } from '@/components/files/FilePreview'
import { FileViewer } from '@/components/files/FileViewer'
import { Modal } from '@/components/ui/Modal'
import { ShareDialog } from '@/components/files/ShareDialog'
import { formatDate, formatRelative } from '@/lib/format'
import { useAuthStore } from '@/stores/auth-store'
import { useShareStore } from '@/stores/share-store'
import { useUserStore } from '@/stores/user-store'
import type { CloudItem, InternalShare, PublicShareLink, SharePermission } from '@/types/cloud'
import { filesApi as cloudService } from '@/features/files/api/files.api'
import { SharedFolderBrowser } from '@/components/files/SharedFolderBrowser'
import { ShareAnalytics } from '@/components/files/ShareAnalytics'

type SharedTab = 'with-me' | 'by-me' | 'links'
type SharedViewMode = 'list' | 'grid'

export function SharedView({ items }: { items: CloudItem[] }) {
  const search = useSearchParams()
  const { currentUserId } = useAuthStore()
  const { users } = useUserStore()
  const shareStore = useShareStore()
  const [tab, setTab] = useState<SharedTab>('with-me')
  const [view, setView] = useState<SharedViewMode>('list')
  const [query, setQuery] = useState('')
  const [permission, setPermission] = useState<'all' | SharePermission>('all')
  const [includeDisabled, setIncludeDisabled] = useState(false)
  const [selectedShare, setSelectedShare] = useState<InternalShare | null>(null)
  const [selectedLink, setSelectedLink] = useState<(PublicShareLink & { url: string | null }) | null>(null)
  const [manageLink, setManageLink] = useState<(PublicShareLink & { url: string | null }) | null>(null)
  const [preview, setPreview] = useState<CloudItem | null>(null)
  const [sharedFolderId, setSharedFolderId] = useState<string | null>(null)
  const [menu, setMenu] = useState<{ item: CloudItem; groups: FileMenuItem[][]; x: number; y: number } | null>(null)
  const [notice, setNotice] = useState('')
  const [sharedWithMe, setSharedWithMe] = useState<InternalShare[]>([])
  const [sharedByMe, setSharedByMe] = useState<InternalShare[]>([])
  const [allLinks, setAllLinks] = useState<Array<PublicShareLink & { url: string | null }>>([])
  const [loadError, setLoadError] = useState('')
  const [shareLoading, setShareLoading] = useState(true)
  const deepLinkHandled = useRef(false)
  const resourceId = search.get('resourceId')
  const resourceType = search.get('resourceType')
  useEffect(() => { let active = true; Promise.all([shareStore.getSharedWithMe(), shareStore.getSharedByMe(), shareStore.getPublicLinks(currentUserId)]).then(([withMe, byMe, publicLinks]) => { if (!active) return; setSharedWithMe(withMe); setSharedByMe(byMe); setAllLinks(publicLinks); setLoadError('') }).catch(error => { if (active) setLoadError(error instanceof Error ? error.message : 'Unable to load shared items.') }).finally(() => { if (active) setShareLoading(false) }); return () => { active = false } }, [currentUserId, shareStore])
  // Resolve a notification resource once after the shared lists are available.
  useEffect(() => {
    if (deepLinkHandled.current || shareLoading || !resourceId || (resourceType !== 'file' && resourceType !== 'folder')) return
    const item = [...sharedWithMe, ...sharedByMe].find(entry => entry.itemId === resourceId && entry.itemType === resourceType)?.sharedItem
    deepLinkHandled.current = true
    if (!item) {
      const timer = window.setTimeout(() => setNotice('Resource tidak tersedia atau aksesnya sudah dicabut.'), 0)
      return () => window.clearTimeout(timer)
    }
    const timer = window.setTimeout(() => {
      if (item.kind === 'folder' && !sharedFolderId) setSharedFolderId(item.id)
      if (item.kind === 'file' && !preview) setPreview(item)
    }, 0)
    return () => window.clearTimeout(timer)
  }, [preview, resourceId, resourceType, shareLoading, sharedFolderId, sharedByMe, sharedWithMe])
  const shares = tab === 'with-me' ? sharedWithMe : sharedByMe
  const links = allLinks.filter(link => includeDisabled || link.enabled)
  const itemFor = (id: string) => items.find(item => item.id === id) ?? sharedWithMe.find(share => share.itemId === id)?.sharedItem ?? sharedByMe.find(share => share.itemId === id)?.sharedItem ?? allLinks.find(link => link.itemId === id)?.sharedItem
  const personFor = (id: string) => users.find(user => user.id === id)
  const showNotice = (message: string) => { setNotice(message); window.setTimeout(() => setNotice(''), 2400) }
  const visibleShares = shares.filter(share => { const item = itemFor(share.itemId); return item && `${item.name} ${item.extension}`.toLowerCase().includes(query.toLowerCase()) && (permission === 'all' || share.permission === permission) })
  const visibleLinks = links.filter(link => itemFor(link.itemId)?.name.toLowerCase().includes(query.toLowerCase()))
  const download = (item: CloudItem) => { void cloudService.download(item).catch(error => showNotice(error instanceof Error ? error.message : 'Download failed.')) }
  const copyLink = async (link: PublicShareLink & { url?: string | null }) => { if (!link.url) { showNotice('This public link is disabled.'); return } try { await navigator.clipboard.writeText(link.url); showNotice('Public link copied') } catch { showNotice('Unable to copy the public link.') } }
  const stopSharing = (share: InternalShare) => setSelectedShare(share)
  const openMenu = (event: MouseEvent<HTMLButtonElement>, item: CloudItem, groups: FileMenuItem[][]) => { event.stopPropagation(); setMenu({ item, groups, x: event.clientX, y: event.clientY }) }
  const openItem = (item: CloudItem) => item.kind === 'folder' ? setSharedFolderId(item.id) : setPreview(item)
  const shareActions = (share: InternalShare): FileMenuItem[][] => { const item = itemFor(share.itemId); if (!item) return []; const common: FileMenuItem[] = [{ id: 'open', label: item.kind === 'folder' ? 'Open folder' : 'Open / Preview', onSelect: () => openItem(item) }, { id: 'download', label: 'Download', onSelect: () => item.kind === 'file' ? download(item) : showNotice('Folders cannot be downloaded here.') }, { id: 'show-folder', label: 'Show in folder', onSelect: () => showNotice(`Location: ${item.path || 'My Files'}`) }, { id: 'properties', label: 'Properties', onSelect: () => setSelectedShare(share) }]; return tab === 'by-me' ? [[...common.slice(0, 3), { id: 'share', label: 'Manage access', onSelect: () => setSelectedShare(share) }], [{ id: 'share', label: 'Manage access', onSelect: () => stopSharing(share) }]] : [common] }
  const linkActions = (link: PublicShareLink & { url: string | null }): FileMenuItem[][] => { const item = itemFor(link.itemId); if (!item) return []; if (!link.enabled) return [[{ id: 'share', label: 'Manage access', onSelect: () => setManageLink(link) }, { id: 'properties', label: 'Properties', onSelect: () => setSelectedLink(link) }], [{ id: 'copy', label: 'Regenerate / Enable link', onSelect: () => { shareStore.regeneratePublicLink(item).then(async () => { setAllLinks(await shareStore.getPublicLinks(currentUserId)); showNotice('Public link enabled') }).catch(error => showNotice(error instanceof Error ? error.message : 'Unable to enable link.')) } }]]; return [[{ id: 'open', label: 'Open / Preview', onSelect: () => setPreview(item) }, { id: 'copy', label: 'Copy link', onSelect: () => void copyLink(link) }, { id: 'share', label: 'Manage access', onSelect: () => setManageLink(link) }, { id: 'analytics', label: 'Lihat analitik', onSelect: () => setSelectedLink(link) }], [{ id: 'trash', label: 'Disable link', onSelect: () => { shareStore.disablePublicLink(item).then(async () => { setAllLinks(await shareStore.getPublicLinks(currentUserId)); showNotice('Public link disabled') }).catch(error => showNotice(error instanceof Error ? error.message : 'Unable to disable link.')) }, destructive: true }]] }
  const count = tab === 'links' ? visibleLinks.length : visibleShares.length

  if (sharedFolderId) return <SharedFolderBrowser folderId={sharedFolderId} onBack={() => setSharedFolderId(null)} onOpenFile={setPreview} onOpenFolder={(item) => setSharedFolderId(item.id)} />

  return <div className="shared-workspace"><header className="shared-heading"><div><p className="section-kicker">Drive by NasLabs</p><h1>Shared</h1><p>Everything shared with you, by you, and through public links.</p></div><div className="view-toggle" aria-label="Choose view"><button className={view === 'list' ? 'selected' : ''} onClick={() => setView('list')} aria-label="List view"><List size={16} /></button><button className={view === 'grid' ? 'selected' : ''} onClick={() => setView('grid')} aria-label="Grid view"><Grid2X2 size={16} /></button></div></header><div className="shared-tabs" role="tablist" aria-label="Shared views"><button className={tab === 'with-me' ? 'active' : ''} onClick={() => setTab('with-me')} role="tab" aria-selected={tab === 'with-me'}>Shared with me</button><button className={tab === 'by-me' ? 'active' : ''} onClick={() => setTab('by-me')} role="tab" aria-selected={tab === 'by-me'}>Shared by me</button><button className={tab === 'links' ? 'active' : ''} onClick={() => setTab('links')} role="tab" aria-selected={tab === 'links'}>Links</button></div><div className="shared-toolbar"><label className="folder-search"><Search size={15} /><input value={query} onChange={event => setQuery(event.target.value)} placeholder={tab === 'links' ? 'Search public links' : 'Search shared'} aria-label="Search shared" /></label>{tab !== 'links' && <label className="filter-control"><span>Permission</span><select value={permission} onChange={event => setPermission(event.target.value as 'all' | SharePermission)}><option value="all">All</option><option value="viewer">Viewer</option><option value="editor">Editor</option></select></label>}{tab === 'links' && <label className="shared-checkbox"><input type="checkbox" checked={includeDisabled} onChange={event => setIncludeDisabled(event.target.checked)} /> Show disabled</label>}</div>{loadError && <p role="alert">{loadError}</p>}{shareLoading ? <p className="loading-state" role="status" aria-live="polite"><LoaderCircle className="spin" size={18} aria-hidden="true" /><span>Loading shared items…</span></p> : count === 0 ? <div className="empty-state compact"><div className="empty-mark"><Share2 size={20} /></div><h2>{tab === 'with-me' ? 'Nothing shared with you yet.' : tab === 'by-me' ? "You haven't shared anything yet." : 'No public links yet.'}</h2><p>{query ? 'Try a different search.' : tab === 'links' ? 'Public links you create will appear here.' : 'Shared items will appear here.'}</p></div> : view === 'grid' && tab !== 'links' ? <div className="shared-grid">{visibleShares.map(share => { const item = itemFor(share.itemId); if (!item) return null; return <article className="shared-card" key={share.id}><FilePreview item={item} /><div><strong title={item.name}>{item.name}</strong><span>{tab === 'with-me' ? `From ${share.ownerName ?? personFor(share.ownerId)?.name ?? 'Drive user'}` : `With ${share.recipientName ?? personFor(share.recipientUserId)?.name ?? 'Drive user'}`}</span><small>{share.permission} · {formatRelative(share.createdAt)}</small></div><button className="icon-button" onClick={event => openMenu(event, item, shareActions(share))} aria-label={`More actions for ${item.name}`}><MoreHorizontal size={17} /></button></article> })}</div> : <div className="file-table-wrap shared-table-wrap"><table className="file-table"><thead><tr><th>Name</th><th>{tab === 'links' ? 'Access' : tab === 'with-me' ? 'Owner' : 'Shared with'}</th><th>{tab === 'links' ? 'Status' : 'Permission'}</th><th>{tab === 'links' ? 'Created' : 'Shared date'}</th><th><span className="sr-only">Actions</span></th></tr></thead><tbody>{tab === 'links' ? visibleLinks.map(link => { const item = itemFor(link.itemId); if (!item) return null; return <LinkRow key={link.id} item={item} link={link} onMenu={event => openMenu(event, item, linkActions(link))} /> }) : visibleShares.map(share => { const item = itemFor(share.itemId); if (!item) return null; return <ShareRow key={share.id} item={item} share={share} person={tab === 'with-me' ? share.ownerName ?? personFor(share.ownerId)?.name ?? 'Drive user' : share.recipientName ?? personFor(share.recipientUserId)?.name ?? 'Drive user'} onMenu={event => openMenu(event, item, shareActions(share))} /> })}</tbody></table></div>}{menu && <FileContextMenu item={menu.item} x={menu.x} y={menu.y} onClose={() => setMenu(null)} groups={menu.groups} />}{selectedShare && <ShareDialog item={itemFor(selectedShare.itemId)!} ownerId={currentUserId} users={users} onClose={() => setSelectedShare(null)} onNotice={showNotice} />}{manageLink && <ShareDialog item={itemFor(manageLink.itemId)!} ownerId={currentUserId} users={users} onClose={() => setManageLink(null)} onNotice={showNotice} />}{selectedLink && <LinkDetails link={selectedLink} item={itemFor(selectedLink.itemId)} onClose={() => setSelectedLink(null)} onCopy={() => void copyLink(selectedLink)} />}{preview && <Modal onClose={() => setPreview(null)}><FileViewer item={preview} onClose={() => setPreview(null)} onDownload={() => download(preview)} onShowInFolder={() => showNotice(`Location: ${preview.path || 'My Files'}`)} /></Modal>}{notice && <div className="settings-toast" role="status">{notice}</div>}</div>
}

function ShareRow({ item, share, person, onMenu }: { item: CloudItem; share: InternalShare; person: string; onMenu: (event: MouseEvent<HTMLButtonElement>) => void }) { return <tr><td><div className="name-cell">{item.kind === 'folder' ? <FolderOpen size={17} /> : <FileText size={17} />}<span>{item.name}</span><small className="shared-item-type">{item.kind === 'folder' ? 'Folder' : 'File'}</small></div></td><td className="muted-cell">{person}</td><td><span className="permission-label">{share.permission}</span></td><td className="muted-cell">{formatRelative(share.createdAt)}</td><td><button className="icon-button" onClick={onMenu} aria-label={`More actions for ${item.name}`}><MoreHorizontal size={17} /></button></td></tr> }
function LinkRow({ item, link, onMenu }: { item: CloudItem; link: PublicShareLink & { url: string | null }; onMenu: (event: MouseEvent<HTMLButtonElement>) => void }) { const status = link.status ?? (link.enabled ? 'active' : 'revoked'); return <tr><td><div className="name-cell"><Link2 size={17} /><span>{item.name}</span></div></td><td className="muted-cell"><span>Anyone with the link</span><small className="link-permission">Viewer</small></td><td><span className={`link-status ${status}`}>{status === 'active' ? 'Active' : status === 'expired' ? 'Expired' : 'Revoked'}</span></td><td className="muted-cell">{formatDate(link.createdAt)}</td><td><button className="icon-button" type="button" onClick={onMenu} aria-label={`More actions for ${item.name}`}><MoreHorizontal size={17} /></button></td></tr> }
function LinkDetails({ link, item, onClose, onCopy }: { link: PublicShareLink & { url: string | null }; item?: CloudItem; onClose: () => void; onCopy: () => void }) { return <Modal title={item?.name ?? 'Public link'} onClose={onClose}><div className="link-details"><div className="shared-person"><div className="general-access-icon"><Link2 size={16} /></div><div><strong>Anyone with the link</strong><span>Viewer access · {link.enabled ? 'Aktif' : 'Dicabut'}</span></div></div><ShareAnalytics link={link} /><dl className="details-grid"><dt>Dibuat</dt><dd>{formatDate(link.createdAt)}</dd><dt>Permission</dt><dd>Viewer</dd><dt>URL</dt><dd>{link.url ?? 'Disabled'}</dd></dl><div className="modal-actions">{link.enabled && <button className="button secondary" type="button" onClick={onCopy}><Copy size={14} /> Copy link</button>}<button className="button primary" type="button" onClick={onClose}>Done</button></div></div></Modal> }

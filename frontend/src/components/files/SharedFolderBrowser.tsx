'use client'

import { ArrowLeft, FolderOpen, RefreshCw } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { FilePreview } from '@/components/files/FilePreview'
import { ItemIcon } from '@/components/ui/Icon'
import { formatBytes, formatDate } from '@/lib/format'
import { internalSharesApi } from '@/features/sharing/api/internal-shares.api'
import type { CloudItem, SharePermission } from '@/types/cloud'

type SharedFolderBrowserProps = { folderId: string; onBack: () => void; onOpenFile: (item: CloudItem) => void; onOpenFolder: (item: CloudItem) => void }

export function SharedFolderBrowser({ folderId, onBack, onOpenFile, onOpenFolder }: SharedFolderBrowserProps) {
  const [folder, setFolder] = useState<{ id: string; name: string } | null>(null)
  const [breadcrumb, setBreadcrumb] = useState<Array<{ id: string; name: string }>>([])
  const [folders, setFolders] = useState<CloudItem[]>([])
  const [files, setFiles] = useState<CloudItem[]>([])
  const [permission, setPermission] = useState<SharePermission>('viewer')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const result = await internalSharesApi.browseSharedFolder(folderId)
      setFolder(result.data.currentFolder)
      setBreadcrumb(result.data.breadcrumb)
      setFolders(result.folders)
      setFiles(result.files)
      setPermission(result.data.permission)
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Unable to open this shared folder.')
    } finally {
      setLoading(false)
    }
  }, [folderId])

  // The browser fetch synchronizes this route with the protected API.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load() }, [load])

  return <section className="shared-folder-browser" aria-label={`Shared folder ${folder?.name ?? ''}`}>
    <header className="shared-folder-header">
      <button className="button secondary" type="button" onClick={onBack}><ArrowLeft size={15} />Back to Shared</button>
      <div><p className="section-kicker">Shared folder</p><h1>{folder?.name ?? 'Folder'}</h1><p>{permission === 'editor' ? 'Editor access' : 'Viewer access'}</p></div>
    </header>
    <nav className="shared-folder-breadcrumb" aria-label="Shared folder location">{breadcrumb.map((part, index) => <span key={part.id}>{index > 0 && ' / '}{part.name}</span>)}</nav>
    {loading ? <p className="loading-state" role="status"><RefreshCw className="spin" size={18} />Loading shared folder…</p> : error ? <div className="empty-state compact"><h2>Unable to open folder</h2><p>{error}</p><button className="button secondary" type="button" onClick={() => void load()}>Try again</button></div> : folders.length === 0 && files.length === 0 ? <div className="empty-state compact"><FolderOpen size={24} /><h2>This folder is empty</h2><p>There are no shared items here yet.</p></div> : <div className="shared-folder-content">
      {folders.length > 0 && <section><div className="browser-section-title"><h2>Folders</h2><span>{folders.length}</span></div><div className="folder-browser-grid">{folders.map(item => <button className="folder-browser-card" type="button" key={item.id} onClick={() => onOpenFolder(item)}><ItemIcon item={item} open /><span className="browser-card-copy"><strong>{item.name}</strong><span>Folder</span></span></button>)}</div></section>}
      {files.length > 0 && <section><div className="browser-section-title"><h2>Files</h2><span>{files.length}</span></div><div className="shared-folder-files">{files.map(item => <article className="shared-folder-file" key={item.id}><button type="button" className="shared-folder-file-open" onClick={() => onOpenFile(item)}><FilePreview item={item} /><strong title={item.name}>{item.name}</strong><span>{item.extension.toUpperCase() || 'FILE'} · {formatBytes(item.size)}</span><small>Updated {formatDate(item.updatedAt)}</small></button></article>)}</div></section>}
    </div>}
  </section>
}

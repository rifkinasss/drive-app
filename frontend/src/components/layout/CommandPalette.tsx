'use client'

import { useEffect, useRef, useState } from 'react'
import { FolderPlus, Search, Settings, Star, Trash2, Upload, X } from 'lucide-react'
import { useRouter } from 'next/navigation'
import type { useCloudStore } from '@/stores/cloud-store'

type Store = ReturnType<typeof useCloudStore>
type PaletteCommand = { id: string; label: string; hint: string; icon: typeof Search; run: () => void }

export function CommandPalette({ store }: { store: Store }) {
  const router = useRouter()
  const [open, setOpen] = useState(false)
  const [uploadPending, setUploadPending] = useState(false)
  const [query, setQuery] = useState('')
  const input = useRef<HTMLInputElement>(null)
  const uploadInput = useRef<HTMLInputElement>(null)
  const close = () => { setOpen(false); setQuery('') }
  const commands: PaletteCommand[] = [
    { id: 'search', label: 'Search Drive', hint: 'Find files and folders', icon: Search, run: () => { close(); router.push('/files') } },
    { id: 'upload', label: 'Upload File', hint: 'Add a file to My Files', icon: Upload, run: () => { close(); setUploadPending(true) } },
    { id: 'folder', label: 'Create Folder', hint: 'Create a folder in My Files', icon: FolderPlus, run: () => { close(); router.push('/files?action=new-folder') } },
    { id: 'files', label: 'My Files', hint: 'Browse your files', icon: FolderPlus, run: () => { close(); router.push('/files') } },
    { id: 'recent', label: 'Recent', hint: 'Recently changed files', icon: Search, run: () => { close(); router.push('/recent') } },
    { id: 'shared', label: 'Shared', hint: 'Items shared with you', icon: Search, run: () => { close(); router.push('/shared') } },
    { id: 'requests', label: 'File Requests', hint: 'Collect files from anyone', icon: Upload, run: () => { close(); router.push('/file-requests') } },
    { id: 'starred', label: 'Starred', hint: 'Your important items', icon: Star, run: () => { close(); router.push('/starred') } },
    { id: 'trash', label: 'Trash', hint: 'Restore or remove items', icon: Trash2, run: () => { close(); router.push('/trash') } },
    { id: 'storage', label: 'Storage', hint: 'Review storage usage', icon: Search, run: () => { close(); router.push('/storage') } },
    { id: 'settings', label: 'Settings', hint: 'Manage your Drive', icon: Settings, run: () => { close(); router.push('/settings') } },
  ]
  const filtered = commands.filter(command => `${command.label} ${command.hint}`.toLowerCase().includes(query.toLowerCase())).slice(0, 8)
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); setOpen(true); window.setTimeout(() => input.current?.focus(), 0) }
      if (event.key === 'Escape') close()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [])
  useEffect(() => { if (open) input.current?.focus() }, [open])
  // Opening the native picker is an imperative browser synchronization.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { if (uploadPending) { uploadInput.current?.click(); setUploadPending(false) } }, [uploadPending])
  return <>
    <input ref={uploadInput} hidden type="file" onChange={event => { const file = event.target.files?.[0]; if (file) store.upload(file, null); event.target.value = ''; close() }} />
    {open && <div className="command-palette-backdrop" role="presentation" onMouseDown={event => { if (event.target === event.currentTarget) close() }}><section className="command-palette" role="dialog" aria-modal="true" aria-label="Drive command palette"><div className="command-palette-search"><Search size={17} /><input ref={input} value={query} onChange={event => setQuery(event.target.value)} placeholder="Search commands…" aria-label="Search commands" /><kbd>Esc</kbd><button className="icon-button" onClick={close} aria-label="Close command palette"><X size={16} /></button></div><div className="command-palette-list">{filtered.length ? filtered.map(command => { const Icon = command.icon; return <button className="command-palette-item" key={command.id} onClick={command.run}><Icon size={17} /><span><strong>{command.label}</strong><small>{command.hint}</small></span><kbd>↵</kbd></button> }) : <p className="command-palette-empty">No commands found.</p>}</div></section></div>}
  </>
}

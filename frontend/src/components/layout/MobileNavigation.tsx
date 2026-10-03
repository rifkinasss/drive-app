'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import type { LucideIcon } from 'lucide-react'
import { Activity, Clock3, Files, FolderPlus, FolderUp, HardDrive, Home, MoreHorizontal, Plus, Settings2, Share2, Star, Trash2, Upload, X } from 'lucide-react'
import { settingsSections } from '@/components/files/SettingsView'
import { useAuthStore } from '@/stores/auth-store'
import { useCloudStore } from '@/stores/cloud-store'

type Sheet = 'quick' | 'more' | null
type NavigationItem = { href: string; label: string; icon: LucideIcon; admin?: boolean }
type CloudStore = ReturnType<typeof useCloudStore>

const primaryItems: NavigationItem[] = [
  { href: '/home', label: 'Home', icon: Home },
  { href: '/files', label: 'Files', icon: Files },
  { href: '/shared', label: 'Shared', icon: Share2 },
]

const secondaryItems: NavigationItem[] = [
  { href: '/recent', label: 'Recent', icon: Clock3 },
  { href: '/starred', label: 'Starred', icon: Star },
  { href: '/trash', label: 'Trash', icon: Trash2 },
  { href: '/storage', label: 'Storage', icon: HardDrive },
  { href: '/activity', label: 'Activity', icon: Activity },
  { href: '/settings', label: 'Settings', icon: Settings2 },
]

export function MobileNavigation({ store }: { store: CloudStore }) {
  const pathname = usePathname()
  const search = useSearchParams()
  const router = useRouter()
  const auth = useAuthStore()
  const [sheet, setSheet] = useState<Sheet>(null)
  const [folderName, setFolderName] = useState('')
  const [creatingFolder, setCreatingFolder] = useState(false)
  const closeButtonRef = useRef<HTMLButtonElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const targetFolderId = pathname === '/files' ? search.get('folder') : null
  const adminItems: NavigationItem[] = settingsSections.filter(item => item.admin).map(item => ({ href: `/settings/${item.id}`, label: item.label, icon: item.icon, admin: true }))

  const closeSheet = useCallback(() => {
    setSheet(null)
    window.setTimeout(() => triggerRef.current?.focus(), 0)
  }, [])

  useEffect(() => {
    if (!sheet) return

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault()
        closeSheet()
        return
      }
      if (event.key !== 'Tab') return
      const panel = document.querySelector<HTMLElement>('[data-mobile-sheet]')
      if (!panel) return
      const focusable = Array.from(panel.querySelectorAll<HTMLElement>('button, a, input, [tabindex]:not([tabindex="-1"])')).filter(element => !element.hasAttribute('disabled'))
      if (!focusable.length) return
      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    }

    document.addEventListener('keydown', handleKeyDown)
    window.setTimeout(() => closeButtonRef.current?.focus(), 0)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [sheet, closeSheet])

  const navigate = (href: string) => {
    closeSheet()
    router.push(href)
  }

  const submitFolder = async () => {
    const name = folderName.trim()
    if (!name || creatingFolder) return
    setCreatingFolder(true)
    const created = await store.createFolder(name, targetFolderId)
    setCreatingFolder(false)
    if (created) { setFolderName(''); closeSheet() }
  }

  const uploadFiles = (files: FileList | null) => {
    Array.from(files ?? []).forEach(file => store.upload(file, targetFolderId))
    closeSheet()
  }

  const isActive = (href: string) => pathname === href || (href === '/files' && pathname.startsWith('/files')) || (href === '/settings' && pathname.startsWith('/settings'))
  const visibleAdminItems = auth.currentUser?.role === 'admin' ? adminItems : []
  const visibleMoreItems = [...secondaryItems, ...visibleAdminItems]
  const moreActive = visibleMoreItems.some(item => isActive(item.href))

  return <>
    <nav className="mobile-navigation" aria-label="Primary navigation">
      <div className="mobile-navigation-dock">
        {primaryItems.slice(0, 2).map(item => <MobileNavLink key={item.href} item={item} active={isActive(item.href)} onNavigate={navigate} />)}
        <button ref={triggerRef} className="mobile-navigation-action" type="button" onClick={event => { triggerRef.current = event.currentTarget; setSheet('quick') }} aria-label="Create or upload" aria-haspopup="dialog" aria-expanded={sheet === 'quick'}><Plus size={22} strokeWidth={1.9} /></button>
        <MobileNavLink item={primaryItems[2]} active={isActive(primaryItems[2].href)} onNavigate={navigate} />
        <button className={`mobile-navigation-item ${sheet === 'more' || moreActive ? 'active' : ''}`} type="button" onClick={event => { triggerRef.current = event.currentTarget; setSheet('more') }} aria-label="More navigation" aria-haspopup="dialog" aria-expanded={sheet === 'more'} aria-current={moreActive ? 'page' : undefined}><MoreHorizontal size={18} strokeWidth={1.8} /><span>More</span></button>
      </div>
    </nav>
    {sheet && <div className="mobile-sheet-layer" role="presentation" onMouseDown={event => { if (event.target === event.currentTarget) closeSheet() }}>
      <section className="mobile-sheet" data-mobile-sheet role="dialog" aria-modal="true" aria-labelledby="mobile-sheet-title">
        <div className="mobile-sheet-handle" aria-hidden="true" />
        <div className="mobile-sheet-header"><h2 id="mobile-sheet-title">{sheet === 'quick' ? 'Create or upload' : 'More in Drive'}</h2><button ref={closeButtonRef} className="icon-button" type="button" onClick={closeSheet} aria-label="Close sheet"><X size={18} /></button></div>
        {sheet === 'quick' ? <QuickActions folderName={folderName} setFolderName={setFolderName} creatingFolder={creatingFolder} onCreateFolder={() => void submitFolder()} onUpload={uploadFiles} /> : <div className="mobile-sheet-links">
          {secondaryItems.map(item => <MobileSheetLink key={item.href} item={item} active={isActive(item.href)} onNavigate={navigate} />)}
          {visibleAdminItems.length > 0 && <><div className="mobile-sheet-divider" role="separator" /><p className="mobile-sheet-section-label">Admin</p>{visibleAdminItems.map(item => <MobileSheetLink key={item.href} item={item} active={isActive(item.href)} onNavigate={navigate} />)}</>}
        </div>}
      </section>
    </div>}
  </>
}

function MobileNavLink({ item, active, onNavigate }: { item: NavigationItem; active: boolean; onNavigate: (href: string) => void }) {
  const Icon = item.icon
  return <button className={`mobile-navigation-item ${active ? 'active' : ''}`} type="button" onClick={() => onNavigate(item.href)} aria-current={active ? 'page' : undefined}><Icon size={18} strokeWidth={1.8} /><span>{item.label}</span></button>
}

function MobileSheetLink({ item, active, onNavigate }: { item: NavigationItem; active: boolean; onNavigate: (href: string) => void }) {
  const Icon = item.icon
  return <button className={`mobile-sheet-link ${active ? 'active' : ''}`} type="button" onClick={() => onNavigate(item.href)} aria-current={active ? 'page' : undefined}><span><Icon size={20} strokeWidth={1.8} />{item.label}</span></button>
}

function QuickActions({ folderName, setFolderName, creatingFolder, onCreateFolder, onUpload }: { folderName: string; setFolderName: (value: string) => void; creatingFolder: boolean; onCreateFolder: () => void; onUpload: (files: FileList | null) => void }) {
  return <div className="mobile-quick-actions">
    <label className="mobile-sheet-action"><span><Upload size={18} /><strong>Upload file</strong></span><input type="file" hidden multiple onChange={event => { onUpload(event.target.files); event.target.value = '' }} /></label>
    <label className="mobile-sheet-action"><span><FolderUp size={18} /><strong>Upload folder</strong></span><input type="file" hidden multiple {...{ webkitdirectory: '' }} onChange={event => { onUpload(event.target.files); event.target.value = '' }} /></label>
    <div className="mobile-new-folder"><div className="mobile-sheet-action"><span><FolderPlus size={18} /><strong>New folder</strong></span></div><div className="mobile-new-folder-form"><input className="text-input" value={folderName} onChange={event => setFolderName(event.target.value)} placeholder="Folder name" aria-label="New folder name" /><button className="button primary" type="button" onClick={onCreateFolder} disabled={creatingFolder || !folderName.trim()}>{creatingFolder ? 'Creating…' : 'Create'}</button></div></div>
  </div>
}

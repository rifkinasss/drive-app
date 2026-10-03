'use client'

import { BarChart3, Copy, Download, Eye, FolderInput, FolderOpen, Info, Pencil, RotateCcw, Share2, Star, StarOff, Trash2 } from 'lucide-react'
import type { ReactNode } from 'react'
import { useEffect, useRef } from 'react'
import type { CloudItem } from '@/types/cloud'

export type FileMenuAction = 'open' | 'show-folder' | 'download' | 'share' | 'analytics' | 'star' | 'rename' | 'move' | 'copy' | 'properties' | 'restore' | 'trash' | 'delete'
export interface FileMenuItem { id: FileMenuAction; label: string; onSelect: () => void; destructive?: boolean; icon?: ReactNode }

export function FileContextMenu({ item, x, y, groups, onClose }: { item: CloudItem; x: number; y: number; groups: FileMenuItem[][]; onClose: () => void }) {
  const menuRef = useRef<HTMLDivElement>(null)
  useEffect(() => { const onKeyDown = (event: KeyboardEvent) => { if (event.key === 'Escape') { event.preventDefault(); onClose() } }; document.addEventListener('keydown', onKeyDown); menuRef.current?.focus(); return () => document.removeEventListener('keydown', onKeyDown) }, [onClose])
  return <div ref={menuRef} className="file-context-menu" style={{ left: x, top: y }} role="menu" tabIndex={-1} aria-label={`Actions for ${item.name}`} onClick={event => event.stopPropagation()}>{groups.map((group, groupIndex) => <div className="file-menu-group" key={`group-${groupIndex}`}>{group.map(action => <button key={action.id} className={action.destructive ? 'context-danger' : ''} role="menuitem" onClick={() => { onClose(); action.onSelect() }}>{action.icon ?? iconFor(action.id, action.label)}<span>{action.label}</span></button>)}</div>)}</div>
}

function iconFor(action: FileMenuAction, label: string) {
  const props = { size: 16, strokeWidth: 1.8 }
  if (action === 'open') return <Eye {...props} />
  if (action === 'show-folder') return <FolderOpen {...props} />
  if (action === 'download') return <Download {...props} />
  if (action === 'share') return <Share2 {...props} />
  if (action === 'analytics') return <BarChart3 {...props} />
  if (action === 'star') return label.toLowerCase().includes('unstar') ? <StarOff {...props} /> : <Star {...props} />
  if (action === 'rename') return <Pencil {...props} />
  if (action === 'move') return <FolderInput {...props} />
  if (action === 'copy') return <Copy {...props} />
  if (action === 'properties') return <Info {...props} />
  if (action === 'restore') return <RotateCcw {...props} />
  if (action === 'delete' || action === 'trash') return <Trash2 {...props} />
  return <StarOff {...props} />
}

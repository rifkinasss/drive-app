 'use client'
import { Archive, Code2, FileText, Image as ImageIcon, Play, Table2 } from 'lucide-react'
import type { CloudItem } from '@/types/cloud'

export function FilePreview({ item }: { item: CloudItem }) {
  if (item.kind === 'folder') return <div className="preview preview-folder"><div className="preview-folder-tab" /><div className="preview-folder-body" /></div>
  if (item.fileType === 'image') return <div className="preview preview-image"><ImageIcon size={28} /><span>{item.extension ? item.extension.toUpperCase() : 'Image'}</span></div>
  if (item.fileType === 'video') return <div className="preview preview-video"><Play size={22} fill="currentColor" /><span>{item.name}</span></div>
  if (item.fileType === 'archive') return <div className="preview preview-archive"><Archive size={31} /><span>archive</span></div>
  if (item.extension === 'csv' || item.extension === 'xlsx') return <div className="preview preview-sheet"><Table2 size={21} /><div className="sheet-lines"><i /><i /><i /><i /></div></div>
  if (item.fileType === 'code') return <div className="preview preview-code"><Code2 size={19} /><span>{item.name}</span><span>{item.extension.toUpperCase()}</span></div>
  if (item.extension === 'pdf') return <div className="preview preview-pdf"><FileText size={25} /><span>PDF</span><i /></div>
  return <div className="preview preview-generic"><FileText size={28} /><span>{item.extension || 'file'}</span></div>
}

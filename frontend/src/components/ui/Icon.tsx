import { Archive, Code2, File, FileImage, FileText, Folder, FolderOpen, Image, LayoutDashboard, MoreHorizontal, PlaySquare, Presentation, type LucideIcon } from 'lucide-react'
import type { CloudItem } from '@/types/cloud'

export function ItemIcon({ item, open = false }: { item: CloudItem; open?: boolean }) {
  if (item.kind === 'folder') return open ? <FolderOpen className="item-icon folder" /> : <Folder className="item-icon folder" />
  const icons: Record<string, LucideIcon> = { image: Image, document: FileText, video: PlaySquare, archive: Archive, code: Code2, design: Presentation, other: File }
  const Icon = icons[item.fileType] ?? File
  return <Icon className={`item-icon ${item.fileType}`} />
}
export { Archive, Code2, FileImage, FileText, Folder, Image, LayoutDashboard, MoreHorizontal, Presentation }

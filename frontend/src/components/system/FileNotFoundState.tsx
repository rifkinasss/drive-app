import { SystemStateContent } from '@/components/system/SystemState'

export function FileNotFoundState({ kind = 'file' }: { kind?: 'file' | 'folder' }) {
  const isFolder = kind === 'folder'
  return <section className="domain-system-state"><SystemStateContent icon={isFolder ? 'folder-search' : 'file-question'} title={isFolder ? 'Folder not found' : 'File not found'} description={isFolder ? 'This folder may have been moved or deleted.' : 'The file may have been moved, deleted, or is no longer available.'} primaryAction={{ label: 'Go to My Files', href: '/files' }} secondaryAction={isFolder ? undefined : { label: 'Back', href: '/files' }} /></section>
}

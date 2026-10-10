'use client'

import { useEffect, useRef, useState } from 'react'
import { FolderPlus, FolderUp, Plus, Upload } from 'lucide-react'

interface NewMenuProps {
  onNewFolder: () => void
  onUploadFiles: (files: File[]) => void
  onUploadFolder?: (files: File[]) => void
  align?: 'left' | 'right'
  className?: string
}

export function NewMenu({
  onNewFolder,
  onUploadFiles,
  onUploadFolder,
  align = 'left',
  className = '',
}: NewMenuProps) {
  const [open, setOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const folderInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!open) return

    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setOpen(false)
      }
    }

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault()
        setOpen(false)
        triggerRef.current?.focus()
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('mousedown', handleClickOutside)
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [open])

  const handleFilesChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(event.target.files ?? [])
    if (files.length > 0) {
      onUploadFiles(files)
    }
    event.target.value = ''
    setOpen(false)
  }

  const handleFolderChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(event.target.files ?? [])
    if (files.length > 0) {
      if (onUploadFolder) {
        onUploadFolder(files)
      } else {
        onUploadFiles(files)
      }
    }
    event.target.value = ''
    setOpen(false)
  }

  return (
    <div ref={menuRef} className={`new-menu-wrap ${className}`.trim()}>
      <button
        className="button primary new-button"
        type="button"
        ref={triggerRef}
        onClick={() => setOpen((prev) => !prev)}
        aria-haspopup="menu"
        aria-expanded={open}
      >
        <Plus size={16} strokeWidth={2.2} />
        <span>New</span>
      </button>

      {open && (
        <div
          className={`new-menu ${align === 'right' ? 'align-right' : ''}`}
          role="menu"
          aria-orientation="vertical"
        >
          <button
            type="button"
            className="new-menu-item"
            role="menuitem"
            onClick={() => {
              onNewFolder()
              setOpen(false)
            }}
          >
            <FolderPlus size={16} />
            <span>New folder</span>
          </button>

          <div className="new-menu-divider" role="separator" />

          <button
            type="button"
            className="new-menu-item"
            role="menuitem"
            onClick={() => fileInputRef.current?.click()}
          >
            <Upload size={16} />
            <span>Upload file</span>
          </button>
          <input
            ref={fileInputRef}
            type="file"
            hidden
            multiple
            onChange={handleFilesChange}
          />

          <button
            type="button"
            className="new-menu-item"
            role="menuitem"
            onClick={() => folderInputRef.current?.click()}
          >
            <FolderUp size={16} />
            <span>Upload folder</span>
          </button>
          <input
            ref={folderInputRef}
            type="file"
            hidden
            multiple
            {...({ webkitdirectory: '', directory: '' } as Record<string, string>)}
            onChange={handleFolderChange}
          />
        </div>
      )}
    </div>
  )
}

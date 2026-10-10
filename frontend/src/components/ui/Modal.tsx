'use client'

import { useEffect, useRef, type ReactNode } from 'react'
import { X } from 'lucide-react'

export function Modal({
  title,
  children,
  onClose,
  size = 'default',
}: {
  title?: string
  children: ReactNode
  onClose: () => void
  size?: 'default' | 'wide'
}) {
  const modalRef = useRef<HTMLElement>(null)
  const onCloseRef = useRef(onClose)

  useEffect(() => {
    onCloseRef.current = onClose
  })

  useEffect(() => {
    const listener = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onCloseRef.current()
        return
      }
      if (event.key !== 'Tab' || !modalRef.current) return
      const focusable = Array.from(
        modalRef.current.querySelectorAll<HTMLElement>(
          'button, a, input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )
      ).filter((element) => !element.hasAttribute('disabled'))
      if (!focusable.length) return
      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }

    document.addEventListener('keydown', listener)

    const timer = window.setTimeout(() => {
      if (!modalRef.current) return
      const targetElement =
        modalRef.current.querySelector<HTMLElement>('[autofocus], input, select, textarea') ??
        modalRef.current.querySelector<HTMLElement>(
          'button, a, input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )
      targetElement?.focus()
    }, 0)

    return () => {
      document.removeEventListener('keydown', listener)
      window.clearTimeout(timer)
    }
  }, [])

  return (
    <div
      className="modal-backdrop"
      role="presentation"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onCloseRef.current()
      }}
    >
      <section
        ref={modalRef}
        className={`modal${size === 'wide' ? ' modal-wide' : ''}`}
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? 'modal-title' : undefined}
        aria-label={title ? undefined : 'File viewer'}
      >
        {title && (
          <div className="modal-head">
            <h2 id="modal-title">{title}</h2>
            <button
              className="icon-button"
              type="button"
              onClick={() => onCloseRef.current()}
              aria-label="Close dialog"
            >
              <X size={18} />
            </button>
          </div>
        )}
        {children}
      </section>
    </div>
  )
}

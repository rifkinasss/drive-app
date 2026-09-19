'use client'

import { Monitor, Moon, Sun } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useThemePreference, type ThemePreference } from '@/stores/theme-store'

const themes = ['light', 'dark', 'system'] as const
type Theme = typeof themes[number]

export function ThemeToggle() {
  const { theme, setTheme } = useThemePreference()
  const [open, setOpen] = useState(false)
  const wrapperRef = useRef<HTMLDivElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const current: ThemePreference = themes.includes(theme as Theme) ? theme as Theme : 'system'
  const Icon = current === 'dark' ? Moon : current === 'light' ? Sun : Monitor
  useEffect(() => {
    const onPointerDown = (event: PointerEvent) => { if (!wrapperRef.current?.contains(event.target as Node)) setOpen(false) }
    const onKeyDown = (event: KeyboardEvent) => { if (event.key === 'Escape' && open) { setOpen(false); triggerRef.current?.focus() } }
    document.addEventListener('pointerdown', onPointerDown); document.addEventListener('keydown', onKeyDown)
    return () => { document.removeEventListener('pointerdown', onPointerDown); document.removeEventListener('keydown', onKeyDown) }
  }, [open])
  return <div className="theme-menu-wrap" ref={wrapperRef}><button ref={triggerRef} className="theme-toggle" type="button" onClick={() => setOpen(value => !value)} aria-label={`Theme: ${current}`} title={`Theme: ${current}`} aria-expanded={open} aria-haspopup="menu"><Icon size={16} aria-hidden="true" /></button>{open && <div className="theme-menu" role="menu" aria-label="Theme selection">{themes.map(option => <button key={option} className={current === option ? 'active' : ''} role="menuitemradio" aria-checked={current === option} onClick={() => { setTheme(option); setOpen(false); triggerRef.current?.focus() }}>{option === 'system' ? <Monitor size={15} /> : option === 'dark' ? <Moon size={15} /> : <Sun size={15} />}<span>{option[0].toUpperCase() + option.slice(1)}</span></button>)}</div>}</div>
}

'use client'

export type SettingsSegmentOption<T extends string> = { value: T; label: string }

export function SettingsSegmented<T extends string>({ value, options, onChange, ariaLabel }: { value: T; options: Array<SettingsSegmentOption<T>>; onChange: (value: T) => void; ariaLabel: string }) {
  return <div className="settings-segmented" role="group" aria-label={ariaLabel}>{options.map(option => <button key={option.value} type="button" aria-pressed={value === option.value} className={value === option.value ? 'active' : ''} onClick={() => onChange(option.value)}>{option.label}</button>)}</div>
}

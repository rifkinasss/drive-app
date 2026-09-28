import Link from 'next/link'
import { AlertTriangle } from 'lucide-react'

export function StorageWarningBanner({ percentage }: { percentage: number }) {
  const numericPercentage = Number(percentage)
  const usage = Number.isFinite(numericPercentage) ? Math.min(100, Math.max(0, numericPercentage)) : 0
  if (usage < 80) return null
  const critical = usage >= 100
  return <aside className={`storage-warning-banner ${critical ? 'critical' : usage >= 90 ? 'strong' : 'warning'}`} role={critical ? 'alert' : 'status'}><AlertTriangle size={18} aria-hidden="true" /><div><strong>{critical ? 'Storage is full' : usage >= 90 ? 'Storage is almost full' : 'Storage is getting full'}</strong><span>{critical ? 'Uploads are paused until you free up space.' : `You are using ${usage.toFixed(0)}% of your Drive storage.`}</span></div><Link href={critical ? '/trash' : '/storage'} className="text-action">{critical ? 'Review Trash' : 'Manage storage'}</Link></aside>
}

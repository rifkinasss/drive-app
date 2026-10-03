'use client'

import { useEffect, useState } from 'react'
import { Activity, Database, File, HardDrive, Link2, RefreshCw, ShieldCheck, Trash2, UsersRound } from 'lucide-react'
import { adminOverviewApi } from '@/features/admin/api/overview.api'
import type { AdminOverview as AdminOverviewData } from '@/features/admin/types/overview.types'
import { formatBytes, percentageOf } from '@/lib/format'

export function AdminOverview() {
  const [data, setData] = useState<AdminOverviewData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const load = async () => {
    setLoading(true); setError('')
    try { setData(await adminOverviewApi.get()) } catch (reason) { setError(reason instanceof Error ? reason.message : 'Ringkasan admin tidak dapat dimuat.') } finally { setLoading(false) }
  }
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load() }, [])
  if (loading) return <section className="settings-card admin-overview-state" role="status" aria-live="polite"><RefreshCw className="spin" size={18} aria-hidden="true" /><span>Memuat ringkasan admin…</span></section>
  if (error || !data) return <section className="settings-card admin-overview-state admin-overview-error" role="alert"><ShieldCheck size={18} aria-hidden="true" /><div><strong>Ringkasan belum tersedia</strong><span>{error || 'Data aggregate belum dapat dimuat.'}</span></div><button type="button" className="button secondary" onClick={() => void load()}>Coba lagi</button></section>

  const usage = percentageOf(data.storage.usedBytes, data.storage.quotaBytes)
  const metrics = [
    { label: 'Total pengguna', value: data.users.total.toLocaleString('id-ID'), note: `${data.users.active.toLocaleString('id-ID')} aktif`, icon: UsersRound },
    { label: 'Penyimpanan digunakan', value: formatBytes(data.storage.usedBytes), note: `dari ${formatBytes(data.storage.quotaBytes)}`, icon: HardDrive },
    { label: 'File dan folder', value: (data.files.files + data.files.folders).toLocaleString('id-ID'), note: `${data.files.files.toLocaleString('id-ID')} file · ${data.files.folders.toLocaleString('id-ID')} folder`, icon: Database },
    { label: 'Berbagi aktif', value: (data.sharing.internalShares + data.sharing.publicLinks).toLocaleString('id-ID'), note: `${data.sharing.internalShares.toLocaleString('id-ID')} internal · ${data.sharing.publicLinks.toLocaleString('id-ID')} publik`, icon: Link2 },
  ]
  return <section className="settings-card admin-overview"><div className="settings-section-header"><div><span className="eyebrow">Administrasi</span><h2>Ringkasan</h2><p>Aggregate workspace tanpa membuka isi file pribadi pengguna.</p></div></div><div className="admin-overview-grid">{metrics.map(metric => <article className="admin-overview-metric" key={metric.label}><metric.icon size={17} aria-hidden="true" /><div><span>{metric.label}</span><strong>{metric.value}</strong><small>{metric.note}</small></div></article>)}</div><section className="admin-overview-storage" aria-labelledby="admin-storage-title"><div><span className="eyebrow">Penyimpanan</span><h3 id="admin-storage-title">Kapasitas workspace</h3><p>{formatBytes(data.storage.availableBytes)} tersedia</p></div><div className="admin-overview-meter" aria-label={`${usage.toFixed(1)} persen penyimpanan digunakan`}><i style={{ width: `${usage}%` }} /></div><strong>{usage.toFixed(1)}% digunakan</strong></section><div className="admin-overview-secondary"><OverviewDetail icon={Activity} label="Aktivitas terbaru" value={`${data.activity.recentCount.toLocaleString('id-ID')} dalam 30 hari`} /><OverviewDetail icon={Trash2} label="Sampah" value={`${data.trash.items.toLocaleString('id-ID')} item · ${formatBytes(data.trash.sizeBytes)}`} /><OverviewDetail icon={ShieldCheck} label="Versi aplikasi" value={`v${data.system.applicationVersion}`} /></div><p className="admin-overview-note"><File size={14} aria-hidden="true" /> Hanya metadata aggregate yang ditampilkan. File private tetap tunduk pada policy pemiliknya.</p></section>
}

function OverviewDetail({ icon: Icon, label, value }: { icon: typeof Activity; label: string; value: string }) {
  return <div className="admin-overview-detail"><Icon size={16} aria-hidden="true" /><div><span>{label}</span><strong>{value}</strong></div></div>
}

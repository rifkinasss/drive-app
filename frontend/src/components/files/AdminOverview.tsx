'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { Activity, ArrowUpRight, Crown, Database, File, HardDrive, Link2, LoaderCircle, RefreshCw, ShieldCheck, Trash2, UsersRound } from 'lucide-react'
import { adminOverviewApi } from '@/features/admin/api/overview.api'
import type { AdminOverview as AdminOverviewData } from '@/features/admin/types/overview.types'
import { formatBytes, percentageOf } from '@/lib/format'
import { useUserStore } from '@/stores/user-store'

export function AdminOverview() {
  const router = useRouter()
  const userStore = useUserStore()
  const [data, setData] = useState<AdminOverviewData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const load = async () => {
    setLoading(true); setError('')
    try { setData(await adminOverviewApi.get()) } catch (reason) { setError(reason instanceof Error ? reason.message : 'Ringkasan admin tidak dapat dimuat.') } finally { setLoading(false) }
  }
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load() }, [])
  if (loading) return <section className="settings-card admin-overview-state" role="status" aria-live="polite"><LoaderCircle className="spin" size={18} aria-hidden="true" /><span>Memuat ringkasan admin…</span></section>
  if (error || !data) return <section className="settings-card admin-overview-state admin-overview-error" role="alert"><ShieldCheck size={18} aria-hidden="true" /><div><strong>Ringkasan belum tersedia</strong><span>{error || 'Data aggregate belum dapat dimuat.'}</span></div><button type="button" className="button secondary" onClick={() => void load()}><RefreshCw size={14} aria-hidden="true" /> Coba lagi</button></section>

  const usage = percentageOf(data.storage.usedBytes, data.storage.quotaBytes)
  const topUsers = [...userStore.users].sort((a, b) => b.usedBytes - a.usedBytes).slice(0, 3)
  const unverified = userStore.users.filter(user => !user.emailVerifiedAt && user.status !== 'disabled').length
  const pending = userStore.users.filter(user => user.status === 'pending').length
  const metrics = [
    { label: 'Total pengguna', value: data.users.total.toLocaleString('id-ID'), note: `${data.users.active.toLocaleString('id-ID')} aktif`, icon: UsersRound, href: '/settings/users' },
    { label: 'Penyimpanan digunakan', value: formatBytes(data.storage.usedBytes), note: `dari ${formatBytes(data.storage.quotaBytes)}`, icon: HardDrive, href: '/settings/storage-management' },
    { label: 'File dan folder', value: (data.files.files + data.files.folders).toLocaleString('id-ID'), note: `${data.files.files.toLocaleString('id-ID')} file · ${data.files.folders.toLocaleString('id-ID')} folder`, icon: Database, href: '/files' },
    { label: 'Berbagi aktif', value: (data.sharing.internalShares + data.sharing.publicLinks).toLocaleString('id-ID'), note: `${data.sharing.internalShares.toLocaleString('id-ID')} internal · ${data.sharing.publicLinks.toLocaleString('id-ID')} publik`, icon: Link2, href: '/shared' },
  ]
  return <section className="settings-card admin-overview"><div className="settings-section-header"><div><span className="eyebrow">Administrasi</span><h2>Ringkasan</h2><p>Aggregate workspace tanpa membuka isi file pribadi pengguna.</p></div><button type="button" className="button secondary" onClick={() => { void load(); void userStore.refresh() }} disabled={loading}><RefreshCw size={14} aria-hidden="true" /> Muat ulang</button></div><div className="admin-overview-grid">{metrics.map(metric => <button type="button" className="admin-overview-metric admin-overview-link" key={metric.label} onClick={() => router.push(metric.href)} aria-label={`${metric.label}: ${metric.value}. Buka ${metric.href}`}><metric.icon size={17} aria-hidden="true" /><div><span>{metric.label}</span><strong>{metric.value}</strong><small>{metric.note}</small></div><ArrowUpRight size={14} className="admin-overview-goto" aria-hidden="true" /></button>)}</div>{(pending > 0 || unverified > 0) && <div className="admin-overview-alerts" role="status">{pending > 0 && <button type="button" onClick={() => router.push('/settings/users')}><ShieldCheck size={14} aria-hidden="true" />{pending} undangan menunggu tindakan</button>}{unverified > 0 && <button type="button" onClick={() => router.push('/settings/users')}><ShieldCheck size={14} aria-hidden="true" />{unverified} pengguna belum verifikasi email</button>}</div>}<section className="admin-overview-storage" aria-labelledby="admin-storage-title"><div><span className="eyebrow">Penyimpanan</span><h3 id="admin-storage-title">Kapasitas workspace</h3><p>{formatBytes(data.storage.availableBytes)} tersedia</p></div><div className="admin-overview-meter" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(usage)} aria-label={`${usage.toFixed(1)} persen penyimpanan digunakan`}><i style={{ width: `${usage}%` }} /></div><strong>{usage.toFixed(1)}% digunakan</strong></section>{topUsers.length > 0 && <section className="admin-overview-top" aria-labelledby="admin-top-title"><h3 id="admin-top-title">Pemakaian terbesar</h3><ol>{topUsers.map((user, index) => <li key={user.id}><button type="button" onClick={() => router.push('/settings/storage-management')}><span className="admin-overview-rank">{index + 1}</span><span className="avatar" aria-hidden="true">{user.initials}</span><span><strong>{user.name}</strong><small>{formatBytes(user.usedBytes)} dari {formatBytes(user.quotaBytes)}</small></span>{index === 0 && <Crown size={14} aria-label="Pemakaian terbesar" />}</button><i className="admin-overview-userbar"><em style={{ width: `${Math.min(percentageOf(user.usedBytes, user.quotaBytes), 100)}%` }} /></i></li>)}</ol></section>}<div className="admin-overview-secondary"><OverviewDetail icon={Activity} label="Aktivitas terbaru" value={`${data.activity.recentCount.toLocaleString('id-ID')} dalam 30 hari`} href="/activity" onOpen={() => router.push('/activity')} /><OverviewDetail icon={Trash2} label="Sampah" value={`${data.trash.items.toLocaleString('id-ID')} item · ${formatBytes(data.trash.sizeBytes)}`} href="/trash" onOpen={() => router.push('/trash')} /><OverviewDetail icon={ShieldCheck} label="Versi aplikasi" value={`v${data.system.applicationVersion}`} /></div><p className="admin-overview-note"><File size={14} aria-hidden="true" /> Hanya metadata aggregate yang ditampilkan. File private tetap tunduk pada policy pemiliknya.</p></section>
}

function OverviewDetail({ icon: Icon, label, value, href, onOpen }: { icon: typeof Activity; label: string; value: string; href?: string; onOpen?: () => void }) {
  if (!onOpen) return <div className="admin-overview-detail"><Icon size={16} aria-hidden="true" /><div><span>{label}</span><strong>{value}</strong></div></div>
  return <button type="button" className="admin-overview-detail admin-overview-link" onClick={onOpen} aria-label={`${label}: ${value}. Buka ${href}`}><Icon size={16} aria-hidden="true" /><div><span>{label}</span><strong>{value}</strong></div><ArrowUpRight size={14} className="admin-overview-goto" aria-hidden="true" /></button>
}

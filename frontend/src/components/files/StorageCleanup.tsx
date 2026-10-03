'use client'

import { useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import { Copy, FileWarning, Trash2, X } from 'lucide-react'
import { FileViewer } from '@/components/files/FileViewer'
import { Modal } from '@/components/ui/Modal'
import { formatBytes, formatDate } from '@/lib/format'
import { filesApi as cloudService } from '@/features/files/api/files.api'
import type { CloudItem } from '@/types/cloud'
import type { useCloudStore } from '@/stores/cloud-store'

type Store = ReturnType<typeof useCloudStore>

export function StorageCleanup({ store }: { store: Store }) {
  const router = useRouter()
  const cleanup = store.storage.cleanup
  const allFiles = useMemo(() => unique([...cleanup.largeFiles, ...cleanup.oldFiles, ...cleanup.duplicateGroups.flatMap(group => group.files)]), [cleanup])
  const [selected, setSelected] = useState<string[]>([])
  const [preview, setPreview] = useState<CloudItem | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const fileFor = (id: string) => allFiles.find(item => item.id === id)
  const toggle = (id: string) => setSelected(current => current.includes(id) ? current.filter(value => value !== id) : [...current, id])
  const trashFiles = async (ids: string[]) => {
    const items = ids.map(fileFor).filter((item): item is CloudItem => Boolean(item))
    if (!items.length) return
    setBusy(true); setError('')
    try { await Promise.all(items.map(item => cloudService.trash(item))); setSelected([]); await store.refresh(['browser', 'storage', 'trash', 'recent', 'starred', 'activity']) }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Gagal memindahkan file ke Sampah.') }
    finally { setBusy(false) }
  }
  const openPreview = (item: CloudItem) => setPreview(item)
  return <section className="smart-storage-cleanup" id="smart-cleanup" aria-labelledby="smart-cleanup-title">
    <div className="storage-section-heading"><div><span className="eyebrow">Smart cleanup</span><h2 id="smart-cleanup-title">Bersihkan penyimpanan</h2><p>Tinjau file yang mungkin sudah tidak diperlukan. Tidak ada file yang dihapus permanen.</p></div></div>
    {store.error && <div className="cleanup-load-error" role="alert"><span>Tidak dapat memuat rekomendasi penyimpanan.</span><button className="text-action" type="button" onClick={() => void store.refresh(['storage'])}>Coba lagi</button></div>}
    <div className="cleanup-summary-grid"><CleanupSummary label="File besar" count={cleanup.largeCount} size={cleanup.largeBytes} icon={FileWarning} /><CleanupSummary label="File lama" count={cleanup.oldCount} size={cleanup.oldBytes} icon={FileWarning} /><CleanupSummary label="Sampah" count={store.storage.trashCount} size={store.storage.trash} icon={Trash2} onClick={() => router.push('/trash')} /><CleanupSummary label="Duplikat" count={cleanup.duplicateGroups.length} size={cleanup.duplicateGroups.reduce((sum, group) => sum + group.reclaimableBytes, 0)} icon={Copy} /></div>
    {selected.length > 0 && <div className="cleanup-selection" role="status"><span>{selected.length} dipilih</span><button className="button danger" type="button" disabled={busy} onClick={() => void trashFiles(selected)}><Trash2 size={15} />Pindahkan ke Sampah</button><button className="icon-button" type="button" onClick={() => setSelected([])} aria-label="Batalkan pilihan"><X size={16} /></button></div>}
    {error && <p className="storage-cleanup-error" role="alert">{error}</p>}
    <CleanupSection title="File besar" description={`File aktif berukuran minimal ${formatBytes(cleanup.largeMinBytes)}.`} items={cleanup.largeFiles} selected={selected} onToggle={toggle} onPreview={openPreview} onTrash={id => void trashFiles([id])} empty="Tidak ada file besar yang perlu ditinjau." busy={busy} />
    <CleanupSection title="File lama" description={`File yang tidak diperbarui selama ${cleanup.oldDays} hari.`} items={cleanup.oldFiles} selected={selected} onToggle={toggle} onPreview={openPreview} onTrash={id => void trashFiles([id])} empty="Tidak ada file lama yang perlu ditinjau." busy={busy} />
    <section className="cleanup-category"><div className="cleanup-category-heading"><div><h3>Kandidat duplikat</h3><p>Berdasarkan checksum dan ukuran file yang sama.</p></div></div>{cleanup.duplicateGroups.length === 0 ? <p className="storage-muted">Tidak ditemukan kandidat duplikat.</p> : cleanup.duplicateGroups.map(group => <div className="duplicate-group" key={group.checksum}><div className="duplicate-group-heading"><strong>{group.fileCount} salinan · {formatBytes(group.reclaimableBytes)} dapat ditinjau</strong><span>Ukuran tiap file {formatBytes(group.sizeBytes)}</span></div>{group.files.map(item => <CleanupFileRow key={item.id} item={item} selected={selected.includes(item.id)} onToggle={toggle} onPreview={openPreview} onTrash={id => void trashFiles([id])} busy={busy} />)}</div>)}</section>
    {store.storage.trashCount > 0 && <div className="cleanup-trash-cta"><div><strong>Sampah</strong><span>{store.storage.trashCount} item · {formatBytes(store.storage.trash)}. File tetap dapat dipulihkan dari halaman Sampah.</span></div><button className="button secondary" type="button" onClick={() => router.push('/trash')}>Tinjau sampah</button></div>}
    {preview && <Modal onClose={() => setPreview(null)}><FileViewer item={preview} onClose={() => setPreview(null)} onDownload={() => void cloudService.download(preview)} onTrash={() => { setPreview(null); void trashFiles([preview.id]) }} /></Modal>}
  </section>
}

function CleanupSummary({ label, count, size, icon: Icon, onClick }: { label: string; count: number; size: number; icon: typeof Trash2; onClick?: () => void }) { const content = <><Icon size={17} aria-hidden="true" /><div><strong>{label}</strong><span>{formatBytes(size)} · {count} item</span></div></>; return onClick ? <button className="cleanup-summary" type="button" onClick={onClick}>{content}</button> : <div className="cleanup-summary">{content}</div> }
function CleanupSection({ title, description, items, selected, onToggle, onPreview, onTrash, empty, busy }: { title: string; description: string; items: CloudItem[]; selected: string[]; onToggle: (id: string) => void; onPreview: (item: CloudItem) => void; onTrash: (id: string) => void; empty: string; busy: boolean }) { return <section className="cleanup-category"><div className="cleanup-category-heading"><div><h3>{title}</h3><p>{description}</p></div></div>{items.length === 0 ? <p className="storage-muted">{empty}</p> : <div className="cleanup-file-list">{items.map(item => <CleanupFileRow key={item.id} item={item} selected={selected.includes(item.id)} onToggle={onToggle} onPreview={onPreview} onTrash={onTrash} busy={busy} />)}</div>}</section> }
function CleanupFileRow({ item, selected, onToggle, onPreview, onTrash, busy }: { item: CloudItem; selected: boolean; onToggle: (id: string) => void; onPreview: (item: CloudItem) => void; onTrash: (id: string) => void; busy: boolean }) { return <div className={`cleanup-file-row${selected ? ' selected' : ''}`}><label><input type="checkbox" checked={selected} onChange={() => onToggle(item.id)} aria-label={`Pilih ${item.name}`} /><span><strong>{item.name}</strong><small>{item.path} · {formatDate(item.updatedAt)}</small></span></label><b>{formatBytes(item.size)}</b><div><button className="text-action" type="button" onClick={() => onPreview(item)}>Preview</button><button className="icon-button danger-icon" type="button" disabled={busy} onClick={() => onTrash(item.id)} aria-label={`Pindahkan ${item.name} ke Sampah`}><Trash2 size={15} /></button></div></div> }
function unique(items: CloudItem[]) { return Array.from(new Map(items.map(item => [item.id, item])).values()) }

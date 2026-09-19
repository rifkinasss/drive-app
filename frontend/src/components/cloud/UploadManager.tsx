'use client'

import { useState } from 'react'
import { Check, CircleAlert, ChevronDown, ChevronUp, LoaderCircle, X } from 'lucide-react'
import type { useCloudStore } from '@/stores/cloud-store'
import { formatBytes } from '@/lib/format'

type Store = ReturnType<typeof useCloudStore>
export function UploadManager({ store }: { store: Store }) {
  const tasks = store.uploadTasks.filter(task => task.status !== 'cancelled')
  const [minimized, setMinimized] = useState(false)
  if (!tasks.length) return null
  const completed = tasks.filter(task => task.status === 'completed').length
  const active = tasks.filter(task => task.status === 'queued' || task.status === 'uploading').length
  const failed = tasks.filter(task => task.status === 'failed').length
  return <aside className={`upload-manager ${minimized ? 'is-minimized' : ''}`} aria-live="polite"><div className="upload-manager-head"><button className="upload-manager-title" onClick={() => setMinimized(value => !value)}><div><strong>{minimized ? `Uploads · ${active} remaining` : 'Uploads'}</strong><span>{completed} completed · {active} active{failed ? ` · ${failed} failed` : ''}</span></div>{minimized ? <ChevronUp size={15} /> : <ChevronDown size={15} />}</button><button className="icon-button" onClick={() => tasks.filter(task => task.status === 'completed' || task.status === 'cancelled').forEach(task => store.dismissUpload(task.id))} aria-label="Dismiss completed uploads"><X size={15} /></button></div>{!minimized && <div className="upload-task-list">{tasks.map(task => <div className="upload-task" key={task.id}><div className="upload-task-icon">{task.status === 'completed' ? <Check size={14} /> : task.status === 'failed' ? <CircleAlert size={14} /> : <LoaderCircle className="spin" size={14} />}</div><div className="upload-task-copy"><strong title={task.name}>{task.name}</strong><span>{task.status === 'failed' ? task.error : task.status === 'completed' ? 'Completed' : task.status === 'queued' ? 'Queued' : `${task.progress}% · Uploading`} · {formatBytes(task.size)}</span>{task.status !== 'failed' && task.status !== 'completed' && <i><em style={{ width: `${task.progress}%` }} /></i>}</div>{task.status === 'failed' && task.conflict ? <div className="upload-conflict-actions"><button className="text-action" onClick={() => store.resolveUpload(task.id, 'keep')}>Keep both</button><button className="text-action" onClick={() => store.resolveUpload(task.id, 'replace')}>Replace</button><button className="text-action destructive-text" onClick={() => store.resolveUpload(task.id, 'cancel')}>Cancel</button></div> : task.status === 'failed' ? <><button className="text-action" onClick={() => store.retryUpload(task.id)}>Retry</button><button className="icon-button" onClick={() => store.dismissUpload(task.id)} aria-label={`Remove ${task.name}`}><X size={14} /></button></> : task.status !== 'completed' && <button className="icon-button" onClick={() => store.cancelUpload(task.id)} aria-label={`Cancel ${task.name}`}><X size={14} /></button>}</div>)}</div>}</aside>
}

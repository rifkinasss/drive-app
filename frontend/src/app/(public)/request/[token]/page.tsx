'use client'

import { use, useEffect, useState } from 'react'
import { Check, Link2, Upload } from 'lucide-react'
import { publicFileRequestsApi } from '@/features/file-requests/api/public-file-requests.api'

export default function FileRequestPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params)
  const [request, setRequest] = useState<{ title: string; ownerDisplayName: string } | null>(null)
  const [error, setError] = useState('')
  const [uploaded, setUploaded] = useState(false)
  const [busy, setBusy] = useState(false)
  useEffect(() => { void publicFileRequestsApi.get(token).then(setRequest).catch(reason => setError(reason instanceof Error ? reason.message : 'This upload request is unavailable.')) }, [token])
  const upload = async (file: File) => { setBusy(true); setError(''); try { await publicFileRequestsApi.upload(token, file); setUploaded(true) } catch (reason) { setError(reason instanceof Error ? reason.message : 'Upload failed.') } finally { setBusy(false) } }
  return <div className="public-page"><header className="public-header"><div className="public-brand"><span aria-hidden="true"><Link2 size={15} /></span><div><strong>Drive</strong><small>by NasLabs</small></div></div></header><main className="public-state">{error ? <><span className="public-state-icon"><Link2 size={20} /></span><h1>Upload unavailable</h1><p>{error}</p></> : !request ? <p role="status">Loading upload request…</p> : uploaded ? <><span className="public-state-icon"><Check size={20} /></span><h1>Upload received</h1><p>Your file was sent to {request.ownerDisplayName}.</p><label className="button secondary">Send another file<input hidden type="file" onChange={event => { const file = event.target.files?.[0]; if (file) { setUploaded(false); void upload(file) } }} /></label></> : <><span className="public-state-icon"><Upload size={20} /></span><h1>{request.title}</h1><p>Upload a file for {request.ownerDisplayName}. Existing files stay private.</p><label className={`button primary ${busy ? 'disabled' : ''}`}>{busy ? 'Uploading…' : 'Choose file'}<input hidden disabled={busy} type="file" onChange={event => { const file = event.target.files?.[0]; if (file) void upload(file) }} /></label></>}</main><footer className="public-footer"><span>Drive by NasLabs</span><span>© 2026 NasLabs</span></footer></div>
}

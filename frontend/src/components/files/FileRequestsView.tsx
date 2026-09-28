'use client'

import { useState } from 'react'
import { FileUp } from 'lucide-react'
import { FileRequestDialog } from '@/components/files/FileRequestDialog'
import { useCloudStore } from '@/stores/cloud-store'

export function FileRequestsView() {
  const store = useCloudStore(); const [open, setOpen] = useState(true); const folders = store.activeItems.filter(item => item.kind === 'folder')
  return <div className="content-wrap"><div className="page-heading"><div><p className="section-kicker">Drive by NasLabs</p><h1>File requests</h1><p className="page-description">Collect files into a folder without exposing what is already there.</p></div><button className="button primary" onClick={() => setOpen(true)}><FileUp size={15} />New request</button></div>{open && <FileRequestDialog folders={folders} onClose={() => setOpen(false)} />}</div>
}

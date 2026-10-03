'use client'

import { BadgeCheck, GitCommitHorizontal, Info, PanelsTopLeft, Server, Wrench } from 'lucide-react'
import { InstallDrive } from './InstallDrive'
import { SystemConnectionStatus } from './SystemConnectionStatus'
import { appConfig } from '@/config/app'
import { env } from '@/config/env'
import { useUserStore } from '@/stores/user-store'

export function AboutView() {
  const userStore = useUserStore()
  const environment = process.env.NODE_ENV === 'production' ? 'Production' : 'Development'
  return <section className="settings-card about-card"><div className="about-identity"><div className="about-mark"><Info size={18} /></div><div><span className="eyebrow">Informasi</span><h2>{appConfig.name}</h2><p>Penyimpanan file pribadi</p></div></div><dl className="about-details"><AboutDetail icon={BadgeCheck} label="Versi" value={appConfig.version} /><AboutDetail icon={PanelsTopLeft} label="Frontend" value="Next.js" /><AboutDetail icon={Server} label="Backend" value="Drive API" /><AboutDetail icon={Wrench} label="Environment" value={environment} /><AboutDetail icon={GitCommitHorizontal} label="Build" value={env.buildId || '—'} /></dl><SystemConnectionStatus isAdmin={userStore.currentUser?.role === 'admin'} /><InstallDrive /><p className="about-copyright">© 2026 NasLabs</p></section>
}

function AboutDetail({ icon: Icon, label, value }: { icon: typeof BadgeCheck; label: string; value: string }) { return <div><dt><Icon className="about-detail-icon" size={17} aria-hidden="true" />{label}</dt><dd>{value}</dd></div> }

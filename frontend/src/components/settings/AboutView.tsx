import { Info } from 'lucide-react'
import { InstallDrive } from './InstallDrive'
import { appConfig } from '@/config/app'
import { env } from '@/config/env'

export function AboutView() {
  const environment = process.env.NODE_ENV === 'production' ? 'Production' : 'Development'
  return <section className="settings-card about-card"><div className="about-identity"><div className="about-mark"><Info size={18} /></div><div><span className="eyebrow">Information</span><h2>{appConfig.name}</h2><p>Private file storage</p></div></div><dl className="about-details"><div><dt>Version</dt><dd>{appConfig.version}</dd></div><div><dt>Frontend</dt><dd>Next.js</dd></div><div><dt>Environment</dt><dd>{environment}</dd></div><div><dt>Build</dt><dd>{env.buildId || '—'}</dd></div></dl><InstallDrive /><p className="about-copyright">© 2026 NasLabs</p></section>
}

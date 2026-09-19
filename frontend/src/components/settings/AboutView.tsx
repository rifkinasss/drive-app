import { Info } from 'lucide-react'

export function AboutView() {
  const environment = process.env.NODE_ENV === 'production' ? 'Production' : 'Development'
  return <section className="settings-card about-card"><div className="about-identity"><div className="about-mark"><Info size={18} /></div><div><span className="eyebrow">Information</span><h2>Cloud by NasLabs</h2><p>Private cloud storage</p></div></div><dl className="about-details"><div><dt>Version</dt><dd>{process.env.NEXT_PUBLIC_APP_VERSION ?? '—'}</dd></div><div><dt>Frontend</dt><dd>Next.js</dd></div><div><dt>Environment</dt><dd>{environment}</dd></div><div><dt>Build</dt><dd>{process.env.NEXT_PUBLIC_APP_BUILD_ID ?? '—'}</dd></div></dl><p className="about-copyright">© 2026 NasLabs</p></section>
}

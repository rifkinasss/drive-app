'use client'

import { useEffect, useState } from 'react'
import { sessionsApi as securityService } from '@/features/security/api/sessions.api'
import { pushSubscriptionsApi as pushService } from '@/features/push/api/push-subscriptions.api'
import type { NotificationPreferences as Preferences } from '@/features/security/types/notification.types'
import type { PushState } from '@/features/push/types/push.types'

const labels: Array<{ key: keyof Preferences; title: string; description: string }> = [
  { key: 'shares', title: 'Berbagi', description: 'Saat seseorang membagikan item atau mengubah akses.' },
  { key: 'quota', title: 'Kuota', description: 'Saat penggunaan penyimpanan mencapai ambang peringatan.' },
  { key: 'accountSecurity', title: 'Akun & keamanan', description: 'Peringatan aktivitas akun opsional. Email autentikasi wajib tetap dikirim.' },
]

export function NotificationPreferences({ onNotice }: { onNotice: (message: string) => void }) {
  const [preferences, setPreferences] = useState<Preferences | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState<keyof Preferences | null>(null)
  const [pushState, setPushState] = useState<PushState>(() => pushService.permission())
  const [pushSaving, setPushSaving] = useState(false)

  useEffect(() => { void securityService.getNotificationPreferences().then(setPreferences).catch(error => onNotice(error instanceof Error ? error.message : 'Unable to load notification preferences.')).finally(() => setLoading(false)) }, [onNotice])
  useEffect(() => { void pushService.state().then(setPushState) }, [])
  const toggle = async (key: keyof Preferences) => { if (!preferences) return; const next = !preferences[key]; setPreferences({ ...preferences, [key]: next }); setSaving(key); try { const saved = await securityService.updateNotificationPreferences({ [key]: next }); setPreferences(saved); onNotice('Notification preferences saved') } catch (error) { setPreferences({ ...preferences, [key]: !next }); onNotice(error instanceof Error ? error.message : 'Unable to save notification preferences.') } finally { setSaving(null) } }
  const enablePush = async () => { setPushSaving(true); try { await pushService.subscribe(); setPushState('granted'); onNotice('Push notifications enabled') } catch (error) { setPushState(pushService.permission()); onNotice(error instanceof Error ? error.message : 'Unable to enable push notifications.') } finally { setPushSaving(false) } }
  const disablePush = async () => { setPushSaving(true); try { await pushService.unsubscribe(); setPushState('default'); onNotice('Push notifications disabled') } catch (error) { onNotice(error instanceof Error ? error.message : 'Unable to disable push notifications.') } finally { setPushSaving(false) } }

  return <section className="settings-form notification-preferences"><div><h3>Preferensi notifikasi</h3><p>Pilih notifikasi opsional dalam aplikasi dan push yang tampil di Drive.</p></div><div className="push-preference"><div><strong>Notifikasi push</strong><small>{pushState === 'granted' ? 'Aktif di browser ini.' : pushState === 'denied' ? 'Diblokir di pengaturan browser.' : pushState === 'unsupported' ? 'Tidak didukung oleh browser atau lingkungan ini.' : 'Terima peringatan yang didukung saat Drive berjalan di latar belakang.'}</small></div>{pushState === 'granted' ? <button type="button" className="button secondary" disabled={pushSaving} onClick={() => void disablePush()}>{pushSaving ? 'Menyimpan…' : 'Nonaktifkan'}</button> : pushState === 'default' ? <button type="button" className="button secondary" disabled={pushSaving} onClick={() => void enablePush()}>{pushSaving ? 'Mengaktifkan…' : 'Aktifkan notifikasi push'}</button> : null}</div>{loading ? <p className="form-hint" role="status">Memuat preferensi…</p> : preferences ? <div className="preference-list">{labels.map(item => <label className="preference-row" key={item.key}><span><strong>{item.title}</strong><small>{item.description}</small></span><input type="checkbox" checked={preferences[item.key]} disabled={saving !== null} onChange={() => void toggle(item.key)} aria-label={`Aktifkan notifikasi ${item.title}`} /></label>)}</div> : null}</section>
}

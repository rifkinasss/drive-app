'use client'

import { useEffect, useState } from 'react'
import { sessionsApi as securityService } from '@/features/security/api/sessions.api'
import { pushSubscriptionsApi as pushService } from '@/features/push/api/push-subscriptions.api'
import type { NotificationPreferences as Preferences } from '@/features/security/types/notification.types'
import type { PushState } from '@/features/push/types/push.types'

const labels: Array<{ key: keyof Preferences; title: string; description: string }> = [
  { key: 'shares', title: 'Shares', description: 'When someone shares an item or changes access.' },
  { key: 'quota', title: 'Quota', description: 'When storage usage reaches a warning threshold.' },
  { key: 'accountSecurity', title: 'Account & security', description: 'Optional account activity alerts. Required auth emails are always sent.' },
]

export function NotificationPreferences({ onNotice }: { onNotice: (message: string) => void }) {
  const [preferences, setPreferences] = useState<Preferences | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState<keyof Preferences | null>(null)
  const [pushState, setPushState] = useState<PushState>(() => pushService.permission())

  useEffect(() => { void securityService.getNotificationPreferences().then(setPreferences).catch(error => onNotice(error instanceof Error ? error.message : 'Unable to load notification preferences.')).finally(() => setLoading(false)) }, [onNotice])
  const toggle = async (key: keyof Preferences) => { if (!preferences) return; const next = !preferences[key]; setPreferences({ ...preferences, [key]: next }); setSaving(key); try { const saved = await securityService.updateNotificationPreferences({ [key]: next }); setPreferences(saved); onNotice('Notification preferences saved') } catch (error) { setPreferences({ ...preferences, [key]: !next }); onNotice(error instanceof Error ? error.message : 'Unable to save notification preferences.') } finally { setSaving(null) } }
  const enablePush = async () => { try { await pushService.subscribe(); setPushState('granted'); onNotice('Push notifications enabled') } catch (error) { setPushState(pushService.permission()); onNotice(error instanceof Error ? error.message : 'Unable to enable push notifications.') } }
  const disablePush = async () => { try { await pushService.unsubscribe(); setPushState('default'); onNotice('Push notifications disabled') } catch (error) { onNotice(error instanceof Error ? error.message : 'Unable to disable push notifications.') } }

  return <section className="settings-form notification-preferences"><div><h3>Notification preferences</h3><p>Choose which optional in-app and push notifications appear in Drive.</p></div><div className="push-preference"><div><strong>Push notifications</strong><small>{pushState === 'granted' ? 'Enabled on this browser.' : pushState === 'denied' ? 'Blocked in browser settings.' : pushState === 'unsupported' ? 'Not supported by this browser or environment.' : 'Receive supported alerts when Drive is in the background.'}</small></div>{pushState === 'granted' ? <button type="button" className="button secondary" onClick={() => void disablePush()}>Disable</button> : pushState === 'default' ? <button type="button" className="button secondary" onClick={() => void enablePush()}>Enable push notifications</button> : null}</div>{loading ? <p className="form-hint" role="status">Loading preferences…</p> : preferences ? <div className="preference-list">{labels.map(item => <label className="preference-row" key={item.key}><span><strong>{item.title}</strong><small>{item.description}</small></span><input type="checkbox" checked={preferences[item.key]} disabled={saving !== null} onChange={() => void toggle(item.key)} aria-label={`Enable ${item.title} notifications`} /></label>)}</div> : null}</section>
}

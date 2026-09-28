'use client'

import { Check, Copy, Link2, Search, Share2, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Modal } from '@/components/ui/Modal'
import { buildPublicShareUrl } from '@/lib/public-share'
import { useShareStore } from '@/stores/share-store'
import { useAuthStore } from '@/stores/auth-store'
import type { CloudItem, InternalShare, PublicShareLink, SharePermission } from '@/types/cloud'
import type { CloudUser } from '@/types/user'

export function ShareDialog({ item, ownerId, users, onClose, onNotice }: { item: CloudItem; ownerId: string; users: CloudUser[]; onClose: () => void; onNotice?: (message: string) => void }) {
  const service = useShareStore(); const auth = useAuthStore()
  const [query, setQuery] = useState(''); const [newPermission, setNewPermission] = useState<SharePermission>('viewer')
  const [access, setAccess] = useState<'private' | 'link'>('private'); const [copied, setCopied] = useState(false)
  const [expiration, setExpiration] = useState<'never' | '24h' | '7d' | '30d' | 'custom'>('never'); const [expiresAt, setExpiresAt] = useState(''); const [sharePassword, setSharePassword] = useState(''); const [allowDownload, setAllowDownload] = useState(true)
  const [existing, setExisting] = useState<InternalShare[]>([]); const [link, setLink] = useState<(PublicShareLink & { url: string | null }) | null>(null)
  const [results, setResults] = useState<Array<{ id: string; name: string; email: string }>>([]); const [loading, setLoading] = useState(true); const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const refresh = async () => {
    setError('')
    try { const [shares, publicLink] = await Promise.all([service.getItemShares(item, ownerId), service.getPublicLink(item)]); setExisting(shares); setLink(publicLink); setAccess(publicLink.enabled ? 'link' : 'private'); setAllowDownload(publicLink.allowDownload ?? true); setExpiration(publicLink.expiresAt ? 'custom' : 'never'); setExpiresAt(publicLink.expiresAt ? publicLink.expiresAt.slice(0, 16) : '') }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Unable to load sharing details.') }
    finally { setLoading(false) }
  }
  // Refreshing an external API-backed dialog is intentionally triggered by the item owner.
  // eslint-disable-next-line react-hooks/set-state-in-effect, react-hooks/exhaustive-deps
  useEffect(() => { void refresh() }, [item.id, ownerId])
  // The query reset keeps the controlled search result state synchronized with the input.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (query.trim().length < 2) { setResults([]); return }
    let current = true
    void service.searchUsers(query).then((usersFound) => { if (current) setResults(usersFound) }).catch((reason: unknown) => { if (current) setError(reason instanceof Error ? reason.message : 'Unable to search users.') })
    return () => { current = false }
  }, [query, service])
  const owner = auth.currentUser ?? users.find(user => user.id === ownerId)
  const run = async (action: () => Promise<void>) => { setBusy(true); setError(''); try { await action(); await refresh(); return true } catch (reason) { setError(reason instanceof Error ? reason.message : 'The sharing update failed.'); return false } finally { setBusy(false) } }
  const addUser = async (userId: string) => { if (await run(() => service.shareWithUser(item, userId, newPermission))) { setQuery(''); setResults([]); onNotice?.('Access added') } }
  const copyLink = async () => {
    if (!link?.url) return
    try { await navigator.clipboard.writeText(link.url); setCopied(true); onNotice?.('Link copied'); window.setTimeout(() => setCopied(false), 1800) }
    catch { onNotice?.('Copy failed. Select the link manually') }
  }
  const setGeneralAccess = async (next: 'private' | 'link') => {
    const ok = next === 'link'
      ? await run(async () => { setLink(await service.enablePublicLink(item)) })
      : await run(() => service.disablePublicLink(item))
    if (ok) { setAccess(next); onNotice?.(next === 'link' ? 'Public link enabled' : 'Link disabled') }
  }
  const saveLinkSettings = async () => { if (!link?.enabled) return; if (await run(async () => { const updated = await service.updatePublicLink(item, { expiration, expiresAt: expiration === 'custom' ? new Date(expiresAt).toISOString() : undefined, ...(sharePassword ? { password: sharePassword } : {}), allowDownload }); setLink(updated) })) { setSharePassword(''); onNotice?.('Public link settings updated') } }
  const changePermission = async (share: InternalShare, permission: SharePermission) => { if (await run(() => service.updatePermission(share.id, permission))) onNotice?.('Permission updated') }
  const remove = async (share: InternalShare) => { if (await run(() => service.removeRecipient(share.id))) onNotice?.('Access removed') }
  const url = link?.url ? buildPublicShareUrl(link.url.split('/s/').at(-1) ?? '') : ''

  return <Modal title={`Share “${item.name}”`} onClose={onClose}><div className="share-dialog">{error && <p className="form-hint error" role="alert">{error}</p>}<section><div className="share-section-heading"><strong>People with access</strong><span>{existing.length + 1}</span></div><div className="share-person owner"><div className="avatar">{owner?.initials ?? '?'}</div><div><strong>{owner?.name ?? 'Owner'}</strong><span>Owner</span></div><Check size={15} /></div>{loading ? <p>Loading access…</p> : existing.map(share => <SharedPerson key={share.id} share={share} users={users} onPermission={permission => void changePermission(share, permission)} onRemove={() => void remove(share)} disabled={busy} />)}</section><section><div className="share-section-heading"><strong>Add people</strong></div><div className="share-add-row"><div className="share-user-search"><Search size={15} /><input value={query} onChange={event => setQuery(event.target.value)} placeholder="Search internal Drive users" aria-label="Search internal Drive users" /></div><select className="select-input" value={newPermission} onChange={event => setNewPermission(event.target.value as SharePermission)} aria-label="New user permission"><option value="viewer">Viewer</option><option value="editor">Editor</option></select></div>{query.trim().length >= 2 && <div className="share-user-results">{results.filter(user => user.id !== ownerId && !existing.some(share => share.recipientUserId === user.id)).length ? results.filter(user => user.id !== ownerId && !existing.some(share => share.recipientUserId === user.id)).map(user => <button key={user.id} disabled={busy} onClick={() => void addUser(user.id)}><span className="avatar">{user.name.split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase()}</span><span><strong>{user.name}</strong><small>{user.email}</small></span><Share2 size={15} /></button>) : <p>No internal users found.</p>}</div>}</section><section><div className="share-section-heading"><strong>General access</strong></div><div className="general-access-row"><span className="general-access-icon"><Link2 size={16} /></span><div><strong>{access === 'private' ? 'Private' : 'Anyone with the link'}</strong><small>{access === 'private' ? 'Only added Drive users can access.' : 'Anyone who has this link can view this item.'}</small></div><select className="select-input" value={access} disabled={busy || loading} onChange={event => void setGeneralAccess(event.target.value as 'private' | 'link')} aria-label="General access"><option value="private">Private</option><option value="link">Anyone with the link</option></select></div>{access === 'link' && link?.enabled && url && <><div className="share-link-row"><input readOnly value={url} aria-label="Public share link" /><button className="button secondary" onClick={() => void copyLink()}>{copied ? <Check size={14} /> : <Copy size={14} />}{copied ? 'Copied' : 'Copy link'}</button><button className="text-action" disabled={busy} onClick={() => void setGeneralAccess('private')}><X size={14} />Disable link</button></div><div className="public-link-settings"><label>Expiration<select className="select-input" value={expiration} onChange={event => setExpiration(event.target.value as typeof expiration)}><option value="never">Never</option><option value="24h">24 hours</option><option value="7d">7 days</option><option value="30d">30 days</option><option value="custom">Custom</option></select></label>{expiration === 'custom' && <label>Expires at<input className="text-input" type="datetime-local" value={expiresAt} onChange={event => setExpiresAt(event.target.value)} /></label>}<label>Password protection<input className="text-input" type="password" value={sharePassword} onChange={event => setSharePassword(event.target.value)} placeholder={link.passwordProtected ? 'Set a new password' : 'Optional password'} /></label><label className="filter-check"><input type="checkbox" checked={allowDownload} onChange={event => setAllowDownload(event.target.checked)} /> Allow download</label><button className="button secondary" disabled={busy} onClick={() => void saveLinkSettings()}>Save link settings</button></div></>}</section><div className="modal-actions"><button className="button primary" onClick={onClose}>Done</button></div></div></Modal>
}

function SharedPerson({ share, users, onPermission, onRemove, disabled }: { share: InternalShare; users: CloudUser[]; onPermission: (permission: SharePermission) => void; onRemove: () => void; disabled: boolean }) { const user = users.find(entry => entry.id === share.recipientUserId); return <div className="share-person"><div className="avatar">{user?.initials ?? '?'}</div><div><strong>{user?.name ?? 'Drive user'}</strong><span>{user?.email ?? 'Internal user'}</span></div><select className="select-input" disabled={disabled} value={share.permission} onChange={event => onPermission(event.target.value as SharePermission)} aria-label={`Permission for ${user?.name ?? 'user'}`}><option value="viewer">Viewer</option><option value="editor">Editor</option></select><button className="icon-button" disabled={disabled} onClick={onRemove} aria-label={`Remove access for ${user?.name ?? 'user'}`}><X size={15} /></button></div> }

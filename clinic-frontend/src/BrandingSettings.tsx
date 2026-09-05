import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { api, useClinic } from './lib'
import { ErrorBox } from './components'

export function BrandingSettings() {
  const clinic=useClinic()
  const cache=useQueryClient()
  const [error,setError]=useState<unknown>()
  const [busy,setBusy]=useState(false)
  const [message,setMessage]=useState('')
  async function refresh(){await cache.invalidateQueries({queryKey:['clinic']});await cache.invalidateQueries({queryKey:['admin','settings']})}
  return <section className="card branding-settings"><div><h2>Clinic logo</h2><p>Change the logo used in the header, footer, homepage, doctor page, and staff workspace.</p><img className="branding-preview" src={clinic.data?.branding?.logo_url||'/brand-logo.jpg'} alt="Current clinic logo" width={140} height={140}/></div><div><ErrorBox error={error}/><form onSubmit={async e=>{e.preventDefault();const form=e.currentTarget;setBusy(true);setError(null);setMessage('');try{await api('/admin/branding/logo','POST',new FormData(form));await refresh();form.reset();setMessage('Logo updated across the website.')}catch(err){setError(err)}finally{setBusy(false)}}}><label>Upload a new logo<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required/><small>JPG, PNG or WebP, up to 4 MB and 4000 × 4000 pixels. A square image works best.</small></label><button className="button" disabled={busy}>{busy?'Saving…':'Save logo'}</button></form>{clinic.data?.branding?.custom&&<button className="text-link" disabled={busy} onClick={async()=>{setBusy(true);setError(null);try{await api('/admin/branding/logo','DELETE');await refresh();setMessage('Original clinic logo restored.')}catch(err){setError(err)}finally{setBusy(false)}}}>Restore original logo</button>}{message&&<p className="success" role="status">{message}</p>}</div></section>
}

import { useEffect, useState, useRef } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, ImagePlus, X } from 'lucide-react'
import { api } from './lib'
import { Badge, Empty, ErrorBox, Loading } from './components'
import { BannerCarousel } from './BannerCarousel'
import type { ClinicBanner } from './BannerCarousel'

type BannerDraft=ClinicBanner & {published:boolean}
const fresh=():BannerDraft=>({id:0,title:'',body:'',link_label:'',link_path:'',published:false,kind:'text',image_url:'',image_alt:''})
export function BannerManager(){
  const cache=useQueryClient()
  const query=useQuery({queryKey:['admin','banners'],queryFn:()=>api<BannerDraft[]>('/admin/banners')})
  const [draft,setDraft]=useState<BannerDraft|null>(null)
  const [file,setFile]=useState<File|null>(null)
  const [localImage,setLocalImage]=useState('')
  const [error,setError]=useState<unknown>()
  const [busy,setBusy]=useState(false)
  const [preview,setPreview]=useState(false)
  const [remove,setRemove]=useState<number|null>(null)
  const previewUrl=useRef('')
  function chooseFile(next:File|null){if(previewUrl.current)URL.revokeObjectURL(previewUrl.current);previewUrl.current=next?URL.createObjectURL(next):'';setLocalImage(previewUrl.current);setFile(next)}
  useEffect(()=>()=>{if(previewUrl.current)URL.revokeObjectURL(previewUrl.current)},[])
  const modalOpen=!!draft||remove!==null
  useEffect(()=>{if(!modalOpen)return;const previous=document.activeElement as HTMLElement;const dialog=document.querySelector<HTMLElement>('.banner-manager-dialog');const elements=()=>Array.from(dialog?.querySelectorAll<HTMLElement>('button:not(:disabled),input,select,textarea,a[href]')||[]).filter(e=>e.offsetParent!==null);elements()[0]?.focus();const handler=(e:KeyboardEvent)=>{if(e.key==='Escape'){setDraft(null);setRemove(null)}if(e.key==='Tab'){const list=elements();if(e.shiftKey&&document.activeElement===list[0]){e.preventDefault();list.at(-1)?.focus()}else if(!e.shiftKey&&document.activeElement===list.at(-1)){e.preventDefault();list[0]?.focus()}}};document.addEventListener('keydown',handler);return()=>{document.removeEventListener('keydown',handler);previous?.focus()}},[modalOpen])
  function edit(value:BannerDraft){setDraft({...fresh(),...value});chooseFile(null);setError(null);setPreview(false)}
  async function refresh(){await cache.invalidateQueries({queryKey:['admin','banners']});await cache.invalidateQueries({queryKey:['clinic']})}
  async function save(){
    if(!draft||busy)return
    if(draft.kind!=='text'&&!file&&!draft.image_url){setError(new Error('Choose an image for this banner or poster before saving.'));return}
    setBusy(true);setError(null)
    try{
      const payload={...draft,body:draft.body||'',image_url:draft.kind==='text'?'':draft.image_url||'',image_alt:draft.image_alt||draft.title}
      const form=new FormData()
      for(const [key,value] of Object.entries(payload)){if(key!=='id')form.append(key,typeof value==='boolean'?(value?'1':'0'):String(value??''))}
      if(file&&draft.kind!=='text')form.append('image',file)
      if(draft.id)form.append('_method','PUT')
      await api(`/admin/banners${draft.id?'/'+draft.id:''}`,'POST',form)
      await refresh();chooseFile(null);setDraft(null)
    }catch(e){setError(e)}finally{setBusy(false)}
  }
  return <><div className="admin-heading"><div><span className="eyebrow">CLINIC ANNOUNCEMENTS</span><h1>Banners & posters</h1><p>Publish text notices, landscape image banners, or full posters in the carousel.</p></div><button className="button" onClick={()=>edit(fresh())}><Plus size={18}/>Add banner / poster</button></div><ErrorBox error={query.error}/>{query.isPending?<Loading/>:!query.data?.length?<Empty>Add your first announcement. Save it as a draft or publish it immediately.</Empty>:<div className="banner-admin-grid">{query.data.map(b=><article className="card" key={b.id}>{b.image_url?<img className="banner-admin-thumb" src={b.image_url} alt={b.image_alt||b.title}/>:<div className="banner-text-thumb"><ImagePlus/><span>Text announcement</span></div>}<div className="banner-admin-meta"><Badge status={b.published?'published':'draft'}/><span>{b.kind==='poster'?'Poster':b.kind==='banner'?'Image banner':'Text'}</span></div><h2>{b.title}</h2><p>{b.body}</p><div className="form-actions"><button className="button secondary" onClick={()=>edit(b)}>Edit</button><button className="text-link danger" onClick={()=>{setError(null);setRemove(b.id)}}>Delete</button></div></article>)}</div>}
  {draft&&<div className="modal-backdrop"><section className="modal card banner-manager-dialog" role="dialog" aria-modal="true" aria-label="Banner editor"><div className="section-heading"><h2>{draft.id?'Edit announcement':'New announcement'}</h2><button type="button" className="icon-button" aria-label="Close banner editor" onClick={()=>setDraft(null)}><X/></button></div><ErrorBox error={error}/><form onSubmit={e=>{e.preventDefault();save()}}><label>Announcement type<select value={draft.kind} onChange={e=>setDraft({...draft,kind:e.target.value as BannerDraft['kind']})}><option value="text">Text announcement</option><option value="banner">Image banner (landscape)</option><option value="poster">Poster (show complete image)</option></select></label><label>Title<input required maxLength={150} value={draft.title} onChange={e=>setDraft({...draft,title:e.target.value})}/></label>{draft.kind!=='text'&&<><label>Upload image<input type="file" accept="image/jpeg,image/png,image/webp" required={!draft.image_url&&!file} onChange={e=>chooseFile(e.target.files?.[0]||null)}/><small>JPG, PNG or WebP, up to 4 MB and 4000 × 4000 pixels. Landscape banners fill a wide frame. Posters stay fully visible without cropping.</small></label>{(localImage||draft.image_url)&&<img className="banner-upload-preview" src={localImage||draft.image_url} alt="Selected announcement artwork"/>}<label>Image description<input maxLength={200} value={draft.image_alt||''} onChange={e=>setDraft({...draft,image_alt:e.target.value})} placeholder="Describe the image; the title is used if left blank."/></label></>}<label>Message {draft.kind!=='text'&&'(optional)'}<textarea rows={3} required={draft.kind==='text'} maxLength={500} value={draft.body||''} onChange={e=>setDraft({...draft,body:e.target.value})}/></label><div className="form-grid"><label>Button label (optional)<input maxLength={50} value={draft.link_label||''} onChange={e=>setDraft({...draft,link_label:e.target.value})}/></label><label>Button destination<input value={draft.link_path||''} onChange={e=>setDraft({...draft,link_path:e.target.value})} placeholder="/appointment"/></label></div><label className="checkbox"><input type="checkbox" checked={draft.published} onChange={e=>setDraft({...draft,published:e.target.checked})}/>Publish this announcement</label><div className="form-actions"><button type="button" className="button secondary" onClick={()=>setPreview(!preview)}>{preview?'Hide preview':'Preview'}</button><button className="button" disabled={busy}>{busy?'Saving…':'Save announcement'}</button></div></form>{preview&&<div className="banner-editor-preview"><BannerCarousel banners={[{...draft,image_url:localImage||draft.image_url}]}/></div>}</section></div>}
  {remove!==null&&<div className="modal-backdrop"><section className="modal card banner-manager-dialog" role="dialog" aria-modal="true" aria-label="Delete announcement"><h2>Delete this announcement?</h2><p>It will be removed from the carousel.</p><ErrorBox error={error}/><div className="form-actions"><button className="button danger-button" disabled={busy} onClick={async()=>{setBusy(true);try{await api(`/admin/banners/${remove}`,'DELETE');await refresh();setRemove(null)}catch(e){setError(e)}finally{setBusy(false)}}}>Delete</button><button className="button secondary" onClick={()=>setRemove(null)}>Keep announcement</button></div></section></div>}</>
}

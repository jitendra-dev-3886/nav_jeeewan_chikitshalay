import { useEffect, useRef, useState } from 'react'

export function CoverImageField({file,url,alt,onFile,onAlt,onRemove}:{file:File|null;url:string;alt:string;onFile:(file:File|null)=>void;onAlt:(alt:string)=>void;onRemove:()=>void}) {
  const [preview,setPreview]=useState('')
  const input=useRef<HTMLInputElement>(null)
  useEffect(()=>{if(!file)return;const reader=new FileReader();reader.onload=()=>setPreview(String(reader.result));reader.readAsDataURL(file);return()=>{reader.onload=null;reader.abort()}},[file])
  const source=file?preview:url
  return <fieldset className="cover-image-field"><legend>Cover image (optional)</legend><p>Add a photograph for the public card and detail page.</p><label>Upload image<input ref={input} type="file" accept="image/jpeg,image/png,image/webp" onChange={e=>onFile(e.target.files?.[0]||null)}/><small>JPG, PNG or WebP. Up to 4 MB and 4000 × 4000 pixels.</small></label>{source&&<><img className="cover-upload-preview" src={source} alt={alt||'Selected cover image'}/><button className="text-link danger" type="button" onClick={()=>{if(input.current)input.current.value='';onRemove()}}>Remove image</button></>}<label>Image description<input value={alt} maxLength={200} onChange={e=>onAlt(e.target.value)} placeholder="Describe the photograph"/></label></fieldset>
}

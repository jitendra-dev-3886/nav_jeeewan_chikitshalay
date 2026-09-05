import { useEffect, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { ChevronLeft, ChevronRight, Expand, X, ZoomIn, ZoomOut } from 'lucide-react'
import { Empty, ErrorBox, Loading, PageTitle, Seo } from './components'
import { useClinic } from './lib'

export interface GalleryImage { id:number; path:string; alt:string; caption?:string|null; published?:boolean }
export function GalleryGrid({images,actions}:{images:GalleryImage[];actions?:(image:GalleryImage)=>ReactNode}) {
  const opener=useRef<HTMLButtonElement|null>(null)
  const [selected,setSelected]=useState<number|null>(null)
  const current=images.findIndex(image=>image.id===selected)
  return <><div className="clinic-gallery">{images.map(image=><figure className="gallery-card" key={image.id}>
    <button className="gallery-open" onClick={e=>{opener.current=e.currentTarget;setSelected(image.id)}} aria-label={`View image: ${image.alt}`}><img src={image.path} alt={image.alt} loading="lazy"/><span className="gallery-expand"><Expand size={18}/>View image</span></button>
    <figcaption><strong>{image.caption||image.alt}</strong>{actions&&<div className="gallery-card-actions">{actions(image)}</div>}</figcaption>
  </figure>)}</div>{current>=0&&<ImageViewer images={images} index={current} onSelect={index=>setSelected(images[index].id)} onClose={()=>{setSelected(null);requestAnimationFrame(()=>opener.current?.focus())}}/>}</>
}
function ImageViewer({images,index,onSelect,onClose}:{images:GalleryImage[];index:number;onSelect:(index:number)=>void;onClose:()=>void}) {
  const dialog=useRef<HTMLDialogElement>(null)
  const touch=useRef<number|null>(null)
  const [zoom,setZoom]=useState(false)
  const [failed,setFailed]=useState(false)
  const item=images[index]
  useEffect(()=>{const overflow=document.body.style.overflow;document.body.style.overflow='hidden';dialog.current?.showModal();return()=>{document.body.style.overflow=overflow}},[])
  const select=(next:number)=>{setZoom(false);setFailed(false);onSelect(next)}
  const move=(step:number)=>select((index+step+images.length)%images.length)
  return createPortal(<dialog ref={dialog} className="image-viewer" aria-label="Clinic image viewer" onCancel={e=>{e.preventDefault();onClose()}} onClick={e=>{if(e.target===e.currentTarget)onClose()}} onKeyDown={e=>{if(e.key==='Escape'){e.preventDefault();onClose();return}if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();move(e.key==='ArrowRight'?1:-1)}}}>
    <header className="viewer-toolbar"><div><span>CLINIC GALLERY</span><strong aria-live="polite">{index+1} / {images.length}</strong></div><div><button aria-label={zoom?'Fit image':'Zoom image'} onClick={()=>setZoom(!zoom)} disabled={failed}>{zoom?<ZoomOut/>:<ZoomIn/>}</button><button aria-label="Close image viewer" autoFocus onClick={onClose}><X/></button></div></header>
    <div className={`viewer-stage${zoom?' is-zoomed':''}`} onTouchStart={e=>{touch.current=e.touches[0].clientX}} onTouchEnd={e=>{if(!zoom&&touch.current!==null){const delta=e.changedTouches[0].clientX-touch.current;if(Math.abs(delta)>60)move(delta<0?1:-1)}touch.current=null}}>
      {failed?<p className="viewer-error">This image could not load. Please try again later.</p>:<img key={item.path} src={item.path} alt={item.alt} onError={()=>setFailed(true)}/>}
    </div>
    {images.length>1&&<><button className="viewer-arrow viewer-prev" aria-label="Previous image" onClick={()=>move(-1)}><ChevronLeft/></button><button className="viewer-arrow viewer-next" aria-label="Next image" onClick={()=>move(1)}><ChevronRight/></button></>}
    <footer className="viewer-footer"><p aria-live="polite">{item.caption||item.alt}</p>{images.length>1&&<div className="viewer-thumbnails" aria-label="Choose an image">{images.map((image,i)=><button key={image.id} aria-label={`Show image ${i+1}: ${image.alt}`} aria-pressed={i===index} onClick={()=>select(i)}><img src={image.path} alt="" loading="lazy"/></button>)}</div>}</footer>
  </dialog>,document.body)
}
export function GalleryPage(){const {data,isPending,error}=useClinic();return <div className="container section"><Seo title="Clinic gallery"/><PageTitle eyebrow="AROUND THE CLINIC" title="A closer look at Nav Jeevan.">Explore our clinic through photographs. Select an image to view it in full.</PageTitle>{isPending?<Loading/>:error?<ErrorBox error={error}/>:data?.media.length?<GalleryGrid images={data.media}/>:<Empty>Clinic photographs will appear here soon.</Empty>}</div>}

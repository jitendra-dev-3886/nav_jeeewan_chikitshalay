import { useEffect } from 'react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowUpRight, LoaderCircle, AlertCircle } from 'lucide-react'
import { label, useClinic } from './lib'
export function Logo({compact=false}:{compact?:boolean}) { const clinic=useClinic(); return <Link to="/" className="brand" aria-label="Nav Jeevan Chikitsalay home"><img className="brand-logo" src={clinic.data?.branding?.logo_url||'/brand-logo.jpg'} alt="" width="64" height="64"/>{!compact&&<span>Nav Jeevan<span className="brand-sub">नव जीवन चिकित्सालय</span></span>}</Link> }
export function Loading() { return <div className="state" role="status"><LoaderCircle className="spin"/> Loading…</div> }
export function ErrorBox({error}:{error:unknown}) { if(!error)return null; return <div className="error" role="alert"><AlertCircle size={18}/><span>{error instanceof Error?error.message:String(error)}</span></div> }
export function Empty({children}:{children:ReactNode}) { return <div className="empty">{children}</div> }
export function Badge({status}:{status:string}) { return <span className={`badge ${status}`}>{label(status)}</span> }
export function BookLink({children='Book an appointment',className=''}:{children?:ReactNode;className?:string}) { return <Link to="/appointment" className={`button ${className}`}>{children}<ArrowUpRight size={18}/></Link> }
export function PageTitle({eyebrow,title,children}:{eyebrow:string;title:string;children?:ReactNode}) { return <div className="page-title"><span className="eyebrow">{eyebrow}</span><h1>{title}</h1>{children&&<p>{children}</p>}</div> }
export function Seo({title,description='Clinic information and appointments with Dr. Parmesh Kumar at Nav Jeevan Chikitsalay, Singarjot Ghat, Mahuadhani.',noindex=false}:{title:string;description?:string;noindex?:boolean}) {
  useEffect(()=>{ document.title=`${title} | Nav Jeevan Chikitsalay`; const set=(name:string,content:string)=>{let tag=document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`);if(!tag){tag=document.createElement('meta');tag.name=name;document.head.append(tag)}tag.content=content};set('description',description);set('robots',noindex?'noindex, nofollow':'index, follow');let canonical=document.querySelector<HTMLLinkElement>('link[rel="canonical"]');if(!canonical){canonical=document.createElement('link');canonical.rel='canonical';document.head.append(canonical)}canonical.href=location.origin+location.pathname },[title,description,noindex]);return null
}

import { useQuery } from '@tanstack/react-query'
export class ApiError extends Error {
  errors: Record<string,string[]>; status: number
  constructor(message: string, status: number, errors: Record<string,string[]> = {}) { super(message); this.status=status; this.errors=errors }
}
let csrf = ''
export async function api<T>(path: string, method = 'GET', body?: unknown, retryCsrf = true): Promise<T> {
  if (method !== 'GET' && !csrf) { const response = await fetch('/api/auth/csrf', { credentials:'include',headers:{Accept:'application/json'} }); csrf=(await response.json()).token }
  const multipart=body instanceof FormData
  const response=await fetch(`/api${path}`,{method,credentials:'include',headers:{Accept:'application/json',...(multipart?{}:{'Content-Type':'application/json'}),...(method!=='GET'?{'X-CSRF-TOKEN':csrf}:{})},...(body?{body:multipart?body:JSON.stringify(body)}:{})})
  if(response.ok&&(path==='/auth/login'||path==='/auth/logout'))csrf=''
  if(response.status===419&&method!=='GET'&&retryCsrf){csrf='';return api<T>(path,method,body,false)}
  if(response.ok&&method!=='GET'){try{localStorage.setItem('clinic:updated',String(Date.now()))}catch{/* Storage may be disabled. */}}
  if(response.status===204) return undefined as T
  const data=await response.json().catch(()=>({message:'The server returned an unexpected response.'}))
  if(!response.ok) { if(response.status===419) csrf=''; throw new ApiError(data.message || 'Something went wrong. Please try again.',response.status,data.errors) }
  return data
}
export interface Service { image_url?:string|null; image_alt?:string|null; id:number; name:string; slug:string; summary:string; description:string; preparation:string; duration:number; icon:string; published:boolean; seo_title?:string; seo_description?:string }
export interface Page { image_url?:string|null; image_alt?:string|null; id:number; type:string; title:string; slug:string; excerpt:string; body:string; language:string; seo_title?:string; seo_description?:string }
export interface Profile { name:string; name_hi:string; doctor:string; doctor_hi:string; qualifications:string; designation:string; address:string; address_hi:string; phone:string; whatsapp:string; email:string; map_url:string; registration:string; hours_confirmed:boolean; services_confirmed:boolean; consent_text:string; instructions:string }
export interface Policy { instant_confirmation:boolean; horizon_days:number; lead_minutes:number; cutoff_hours:number; daily_capacity:number; reminder_hours:number[] }
export interface Clinic { branding?:{logo_url:string;custom:boolean}; banners:{kind?:'text'|'banner'|'poster';image_url?:string;image_alt?:string;id:number;title:string;body:string;link_label:string;link_path:string}[]; redirects:{from_path:string;to_path:string}[]; profile:Profile; booking:Policy; hours:{id:number;weekday:number;start_time:string;end_time:string}[]; testimonials:{id:number;name:string;quote:string}[]; media:{id:number;path:string;alt:string;caption:string}[] }
export interface Summary { reference:string; status:string; starts_at:string; ends_at:string; service:string; service_id:number; patient_name:string; phone_mask:string; can_manage:boolean }
export interface Appointment { id:number; reference:string; status:string; starts_at:string; ends_at:string; source:string; reason:string; internal_notes:string; service_id:number; patient:{id:number;name:string;phone:string;age_group:string}; service:Service; events?:{id:number;from_status:string;to_status:string;reason:string;created_at:string}[] }
export interface User { id:number;name:string;email:string;role:'admin'|'doctor'|'receptionist' }
export interface Paginated<T> { data:T[];current_page:number;last_page:number;total:number }
export const useClinic=()=>useQuery({queryKey:['clinic'],queryFn:()=>api<Clinic>('/public/clinic'),refetchOnWindowFocus:true,refetchInterval:30000})
export const useServices=()=>useQuery({queryKey:['services'],queryFn:()=>api<Service[]>('/public/services')})
export const useContent=()=>useQuery({queryKey:['content'],queryFn:()=>api<Page[]>('/public/content')})
export const dateText=(date:string)=>new Intl.DateTimeFormat('en-IN',{dateStyle:'medium',timeZone:'Asia/Kolkata'}).format(new Date(date))
export const timeText=(date:string)=>new Intl.DateTimeFormat('en-IN',{hour:'numeric',minute:'2-digit',timeZone:'Asia/Kolkata'}).format(new Date(date))
export const today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())
export function addDays(date:string,days:number) { const d=new Date(`${date}T12:00:00Z`); d.setUTCDate(d.getUTCDate()+days); return d.toISOString().slice(0,10) }
export const label=(s:string)=>s.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase())
export const statuses=['pending','confirmed','checked_in','completed','cancelled','no_show']
export const transitions:Record<string,string[]>={pending:['confirmed','cancelled'],confirmed:['checked_in','cancelled','no_show'],checked_in:['completed'],completed:[],cancelled:[],no_show:[]}

import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowUpRight, ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react'

export interface ClinicBanner { kind?:'text'|'banner'|'poster'; image_url?:string; image_alt?:string; id:number; title:string; body:string; link_label:string; link_path:string }

export function BannerCarousel({banners}:{banners:ClinicBanner[]}) {
  if (!banners.length) return null
  return <Carousel key={banners.map(b=>b.id).join(',')} banners={banners}/>
}

function Carousel({banners}:{banners:ClinicBanner[]}) {
  // A repeated visual slide lets a single published banner move in both directions too.
  const slides=banners.length===1?[banners[0],banners[0]]:banners
  const [index,setIndex]=useState(0)
  const [paused,setPaused]=useState(false)
  const [hovered,setHovered]=useState(false)
  const [focused,setFocused]=useState(false)
  const [hidden,setHidden]=useState(document.hidden)
  const [reduced,setReduced]=useState(()=>window.matchMedia('(prefers-reduced-motion: reduce)').matches)
  const direction=useRef(1)
  const touchStart=useRef<number|null>(null)
  useEffect(()=>{
    const media=window.matchMedia('(prefers-reduced-motion: reduce)')
    const motion=()=>setReduced(media.matches)
    const visibility=()=>setHidden(document.hidden)
    media.addEventListener('change',motion)
    document.addEventListener('visibilitychange',visibility)
    return()=>{media.removeEventListener('change',motion);document.removeEventListener('visibilitychange',visibility)}
  },[])
  useEffect(()=>{
    if(paused||hovered||focused||hidden||reduced)return
    const timer=window.setTimeout(()=>{
      if(index===slides.length-1)direction.current=-1
      if(index===0)direction.current=1
      setIndex(index+direction.current)
    },5000)
    return()=>window.clearTimeout(timer)
  },[index,slides.length,paused,hovered,focused,hidden,reduced])
  function move(delta:number){direction.current=delta;setIndex(current=>(current+delta+slides.length)%slides.length)}
  const active=index%banners.length
  return <section className="banner-carousel container" aria-label="Clinic announcements" aria-roledescription="carousel"
    onMouseEnter={()=>setHovered(true)} onMouseLeave={()=>setHovered(false)}
    onFocusCapture={()=>setFocused(true)} onBlurCapture={e=>{if(!e.currentTarget.contains(e.relatedTarget))setFocused(false)}}
    onKeyDown={e=>{if(e.key==='ArrowLeft'){e.preventDefault();move(-1)}if(e.key==='ArrowRight'){e.preventDefault();move(1)}}}>
    <div className="banner-viewport" onTouchStart={e=>{touchStart.current=e.touches[0].clientX}} onTouchEnd={e=>{if(touchStart.current!==null){const distance=e.changedTouches[0].clientX-touchStart.current;if(Math.abs(distance)>45)move(distance>0?-1:1)}touchStart.current=null}}>
      <div className="banner-track" style={{transform:`translateX(-${index*100}%)`}}>
        {slides.map((banner,i)=><div key={`${banner.id}-${i}`} className={`banner-slide ${banner.kind&&banner.kind!=='text'&&banner.image_url?'banner-slide-media banner-slide-'+banner.kind:''}`} role="group" aria-roledescription="slide" aria-label={`${i%banners.length+1} of ${banners.length}`} aria-hidden={i!==index} inert={i!==index}>
          {banner.kind!=='text'&&banner.image_url&&<div className="banner-artwork"><img src={banner.image_url} alt={banner.image_alt||banner.title} loading={i===index?'eager':'lazy'} decoding="async"/></div>}<div className="banner-copy"><span className="banner-kicker">FROM YOUR CLINIC</span><h2>{banner.title}</h2><p>{banner.body}</p></div>
          {banner.link_path&&<Link className="button light" to={banner.link_path}>{banner.link_label||'Learn more'}<ArrowUpRight size={18}/></Link>}
        </div>)}
      </div>
    </div>
    <div className="banner-controls">
      <div className="banner-pagination" aria-label="Choose announcement">{banners.length>1?banners.map((banner,i)=><button key={banner.id} className={active===i?'active':''} aria-label={`Show announcement ${i+1}: ${banner.title}`} aria-current={active===i?'true':undefined} onClick={()=>setIndex(i)}><span/></button>):<span className="banner-single-label">Clinic update</span>}</div>
      <div className="banner-navigation"><span className="banner-count" aria-live={paused||focused?'polite':'off'}>{active+1} / {banners.length}</span><button type="button" aria-label="Previous announcement" onClick={()=>move(-1)}><ChevronLeft size={19}/></button><button type="button" aria-label={paused?'Start banner autoplay':'Pause banner autoplay'} aria-pressed={paused} disabled={reduced} title={reduced?'Automatic motion disabled by your device preference':undefined} onClick={()=>setPaused(!paused)}>{paused||reduced?<Play size={16}/>:<Pause size={16}/>}</button><button type="button" aria-label="Next announcement" onClick={()=>move(1)}><ChevronRight size={19}/></button></div>
    </div>
  </section>
}

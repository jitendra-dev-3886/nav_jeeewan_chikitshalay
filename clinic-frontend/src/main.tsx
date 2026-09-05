import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './styles.css'
import './brand.css'
import './carousel.css'
import App from './ClinicApp.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
import './posters.css'

import './gallery.css'

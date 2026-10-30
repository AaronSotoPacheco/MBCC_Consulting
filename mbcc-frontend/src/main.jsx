import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './styles.css'
import QualityApp from './QualityApp.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <QualityApp />
  </StrictMode>,
)

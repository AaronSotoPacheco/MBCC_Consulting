import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './styles.css'
import QualityApp from './QualityApp.jsx'
import ErrorBoundary from './components/ErrorBoundary.jsx'
import { instalarMonitoreo } from './monitoreo.js'

instalarMonitoreo()

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <ErrorBoundary>
      <QualityApp />
    </ErrorBoundary>
  </StrictMode>,
)

import { Component } from 'react'
import { reportarError } from '../monitoreo.js'

// Si una pantalla falla al dibujarse, en lugar de quedar en blanco se avisa y se reporta al servidor.
export default class ErrorBoundary extends Component {
  state = { fallo: false }

  static getDerivedStateFromError() { return { fallo: true } }

  componentDidCatch(error, info) {
    reportarError(error?.message || 'Error al dibujar la pantalla', { detalle: `${error?.stack || ''}\n${info?.componentStack || ''}`, tipo: 'render' })
  }

  render() {
    if (!this.state.fallo) return this.props.children
    return (
      <div className="no-access" role="alert">
        <h1 className="page-title">Algo salió mal</h1>
        <p className="muted">La pantalla tuvo un error. Ya quedó registrado para revisarlo. Tus capturas guardadas sin conexión no se pierden.</p>
        <button className="btn btn-primary" onClick={() => { location.hash = '#/ordenes'; location.reload() }}>Recargar</button>
      </div>
    )
  }
}

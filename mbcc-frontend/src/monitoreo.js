// Reporta al servidor los errores de JavaScript que ocurren en el navegador (ver storage/logs/frontend-*.log).
// Es "mejor esfuerzo": nunca lanza errores ni reintenta, y limita cuántos envía por minuto para no saturar.

const BASE = import.meta.env.VITE_API_URL || '/api'
const MAX_POR_MINUTO = 5
let enviados = []
const vistos = new Set()

export function reportarError(mensaje, { detalle, tipo = 'error' } = {}) {
  try {
    if (import.meta.env.VITE_USE_MOCK === 'true') return
    const texto = String(mensaje || 'Error desconocido').slice(0, 500)
    const clave = `${tipo}|${texto}`
    const ahora = Date.now()
    enviados = enviados.filter((t) => ahora - t < 60000)
    if (vistos.has(clave) || enviados.length >= MAX_POR_MINUTO) return
    vistos.add(clave)
    enviados.push(ahora)
    fetch(`${BASE}/errores-cliente`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ mensaje: texto, detalle: detalle ? String(detalle).slice(0, 4000) : null, url: location.href.slice(0, 300), tipo }),
      keepalive: true,
    }).catch(() => {})
  } catch { /* nunca debe romper la app */ }
}

export function instalarMonitoreo() {
  window.addEventListener('error', (e) => {
    if (!e.message || e.message === 'Script error.') return
    reportarError(e.message, { detalle: e.error?.stack || `${e.filename}:${e.lineno}:${e.colno}` })
  })
  window.addEventListener('unhandledrejection', (e) => {
    const r = e.reason
    if (r?.sinRed || r?.status) return // fallos de red o respuestas del servidor ya se muestran en pantalla
    reportarError(r?.message || String(r), { detalle: r?.stack, tipo: 'promesa' })
  })
}

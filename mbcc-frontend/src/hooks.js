import { useCallback, useEffect, useRef, useState } from 'react'
import { api, auth } from './api.js'

// Carga datos al montar y cada vez que cambia `clave` (texto que identifica la consulta, por ejemplo la fecha).
// Devuelve { data, estado: 'cargando'|'listo'|'error', error, recargar, setData }.
// Ignora respuestas viejas si cambió la consulta mientras tanto; conserva los datos anteriores mientras recarga.
export function useAsync(fn, clave = '') {
  const [version, setVersion] = useState(0)
  const [res, setRes] = useState({ clave: null, data: null, error: '' })
  const actual = `${version}|${clave}`
  const fnRef = useRef(fn)

  useEffect(() => { fnRef.current = fn })

  useEffect(() => {
    let activo = true
    fnRef.current()
      .then((data) => { if (activo) setRes({ clave: actual, data, error: '' }) })
      .catch((e) => { if (activo) setRes((r) => ({ clave: actual, data: r.data, error: e.message || 'No se pudo cargar.' })) })
    return () => { activo = false }
  }, [actual])

  const recargar = useCallback(() => setVersion((v) => v + 1), [])
  const setData = useCallback((f) => setRes((r) => ({ ...r, data: typeof f === 'function' ? f(r.data) : f })), [])
  const estado = res.clave !== actual ? 'cargando' : res.error ? 'error' : 'listo'

  return { data: res.data, estado, error: res.clave === actual ? res.error : '', recargar, setData }
}

// Alertas de rechazos (NG) altos por orden, para supervisor y admin. Se consulta al montar y cada `ms`.
export function useAlertas(activo, ms = 30000) {
  const [alertas, setAlertas] = useState([])
  useEffect(() => {
    if (!activo) return undefined
    let vivo = true
    const cargar = () => api.alertas().then((r) => { if (vivo) setAlertas(r?.alertas || []) }).catch(() => {})
    cargar()
    const t = setInterval(cargar, ms)
    return () => { vivo = false; clearInterval(t) }
  }, [activo, ms])
  return activo ? alertas : []
}

// Recordatorio de asistencia: avisa si la sesión lleva `minutos` abierta y la persona aún no registra su entrada de hoy.
// Consulta al montar, al cambiar de pantalla (`ruta`) y cada minuto. `posponer` lo oculta otros `minutos`.
const inicioDeSesion = () => {
  const clave = `${String(auth.token || '').slice(-12)}|`
  try {
    const guardado = sessionStorage.getItem('mbcc_inicio') || ''
    if (guardado.startsWith(clave)) return Number(guardado.slice(clave.length)) || Date.now()
    const ahora = Date.now()
    sessionStorage.setItem('mbcc_inicio', clave + ahora)
    return ahora
  } catch { return Date.now() }
}

export function useRecordatorioAsistencia(activo, ruta, minutos = 10) {
  const [inicio] = useState(inicioDeSesion)
  const [sinEntrada, setSinEntrada] = useState(false)
  const [ahora, setAhora] = useState(() => Date.now())
  const [hasta, setHasta] = useState(0)

  useEffect(() => {
    if (!activo) return undefined
    let vivo = true
    const cargar = () => api.asistenciaHoy().then((r) => { if (vivo) setSinEntrada(r?.estado === 'sin_entrada') }).catch(() => {})
    cargar()
    const t = setInterval(() => { setAhora(Date.now()); cargar() }, 60000)
    return () => { vivo = false; clearInterval(t) }
  }, [activo, ruta])

  const mostrar = activo && sinEntrada && ahora - inicio >= minutos * 60000 && ahora >= hasta
  return { mostrar, posponer: () => { const n = Date.now(); setAhora(n); setHasta(n + minutos * 60000) } }
}

export const fmt = (n) => Number(n).toLocaleString('es-MX')

export const fechaHora = (iso) => (iso ? new Date(iso).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' }) : '—')

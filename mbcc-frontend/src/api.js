import { DEFECTOS, ESCANEOS_INICIALES, KPIS, ORDERS, PARETO } from './data.js'
import { nivelDe } from './access.js'

const BASE = import.meta.env.VITE_API_URL || '/api'
const FORCE_MOCK = import.meta.env.VITE_USE_MOCK === 'true'

export const auth = {
  get token() { return localStorage.getItem('mbcc_token') },
  set token(value) { value ? localStorage.setItem('mbcc_token', value) : localStorage.removeItem('mbcc_token') },
  get user() { try { return JSON.parse(localStorage.getItem('mbcc_user')) } catch { return null } },
  set user(value) { value ? localStorage.setItem('mbcc_user', JSON.stringify(value)) : localStorage.removeItem('mbcc_user') },
}

function errorFromPayload(payload, fallback) {
  const firstValidationError = Object.values(payload?.errors || {}).flat()[0]
  return payload?.message || firstValidationError || fallback
}

async function request(path, { method = 'GET', body, mock } = {}) {
  if (FORCE_MOCK) return mock()
  const esForm = typeof FormData !== 'undefined' && body instanceof FormData // con FormData el navegador pone el Content-Type

  let response
  try {
    response = await fetch(`${BASE}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body && !esForm ? { 'Content-Type': 'application/json' } : {}),
        ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
      },
      body: body ? (esForm ? body : JSON.stringify(body)) : undefined,
    })
  } catch {
    throw Object.assign(new Error('No se pudo conectar con Laravel. Verifica que el backend esté ejecutándose.'), { sinRed: true })
  }

  if (response.status === 401 && path !== '/login') {
    auth.token = null
    auth.user = null
    location.hash = '#/login'
  }
  if (!response.ok) {
    const payload = await response.json().catch(() => ({}))
    // errors: { campo: ['mensaje'] } cuando Laravel responde 422
    throw Object.assign(new Error(errorFromPayload(payload, 'Error del servidor')), { status: response.status, errors: payload.errors })
  }
  if (response.status === 204) return null
  return response.json()
}

async function download(path, nombre = 'inspecciones.csv') {
  const response = await fetch(`${BASE}${path}`, {
    headers: {
      Accept: 'text/csv, application/json',
      ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
    },
  })
  if (!response.ok) {
    const payload = await response.json().catch(() => ({}))
    throw new Error(errorFromPayload(payload, 'No se pudo exportar el reporte.'))
  }

  const url = URL.createObjectURL(await response.blob())
  const link = document.createElement('a')
  link.href = url
  link.download = nombre
  link.click()
  URL.revokeObjectURL(url)
}

async function resourceList(path) {
  const items = []
  let page = 1
  while (true) {
    const response = await request(`${path}?per_page=100&page=${page}`, { mock: async () => [] })
    if (Array.isArray(response)) return [...items, ...response]
    items.push(...(response.data || []))
    if (!response.last_page || page >= response.last_page) return items
    page += 1
  }
}

export const api = {
  login: (payload) => request('/login', {
    method: 'POST',
    body: payload,
    mock: async () => ({ token: 'demo-token', user: { nombre: 'Juan Pérez García', rol: 'Inspector', turno: payload.turno } }),
  }),
  // Inicio de sesión con el QR único del gafete (el código se valida en el servidor)
  loginQr: (payload) => request('/login/qr', {
    method: 'POST',
    body: payload,
    mock: async () => ({ token: 'demo-token', user: { nombre: 'Juan Pérez García', rol: 'Inspector', turno: payload.turno } }),
  }),
  logout: () => request('/logout', { method: 'POST', mock: async () => null }),
  asistenciaHoy: () => request('/asistencia/hoy', { mock: async () => ({ estado: 'sin_entrada', registro: null, recientes: [] }) }),
  asistenciaEntrada: () => request('/asistencia/entrada', { method: 'POST', mock: async () => ({}) }),
  asistenciaSalida: () => request('/asistencia/salida', { method: 'POST', mock: async () => ({}) }),
  asistenciaDia: (fecha) => request(`/asistencia?fecha=${fecha}`, { mock: async () => ({ fecha, resumen: { total: 0, presentes: 0, salieron: 0, ausentes: 0, retardos: 0 }, filas: [] }) }),
  asistenciaHistorial: (params) => request(`/asistencia/historial?${new URLSearchParams(params)}`, { mock: async () => ({ resumen: { registros: 0, horas: 0, retardos: 0, sin_salida: 0 }, usuarios: [], filas: [] }) }),
  asistenciaManual: (data) => request('/asistencia/manual', { method: 'POST', body: data, mock: async () => data }),
  exportarAsistencia: (params) => download(`/asistencia/datos.csv?${new URLSearchParams(params)}`, 'asistencia.csv'),
  alertas: () => request('/alertas', { mock: async () => ({ umbral: 5, ventana: 20, alertas: [] }) }),
  cambiarPassword: (data) => request('/me/password', { method: 'POST', body: data, mock: async () => ({ message: 'ok' }) }),
  auditoria: (params) => request(`/auditoria?${new URLSearchParams(params)}`, { mock: async () => ({ data: [], total: 0, current_page: 1, last_page: 1 }) }),
  // Supervisor y admin pueden pedir también las cerradas
  ordenes: (cerradas = false) => request(`/ordenes${cerradas ? '?cerradas=1' : ''}`, { mock: async () => ORDERS }),
  opcionesOrden: () => request('/ordenes/opciones', { mock: async () => ({ clientes: [], turnos: [] }) }),
  crearOrden: (data) => request('/ordenes', { method: 'POST', body: data, mock: async () => ({ ...ORDERS[0], so: data.numero_orden, meta: data.meta, estado: 'OPEN' }) }),
  editarOrden: (id, data) => request(`/ordenes/${id}`, { method: 'PATCH', body: data, mock: async () => ({ ...ORDERS[0], id, meta: data.meta, turno: data.turno }) }),
  estadoOrden: (id, estado) => request(`/ordenes/${id}/estado`, { method: 'PATCH', body: { estado }, mock: async () => ({ ...ORDERS[0], id, estado }) }),
  turno: (params) => request(`/turno?${new URLSearchParams(params)}`, {
    mock: async () => ({ fecha: params.fecha, alcance: params.alcance, kpis: { inspeccionadas: 0, ok: 0, ng: 0 }, porOrden: [], ultimos: [], porInspector: [] }),
  }),
  defectos: () => request('/defectos', { mock: async () => DEFECTOS.map((description) => ({ description })) }),
  escaneos: (orderId) => {
    const nivel = nivelDe(auth.user)
    const shift = ['admin', 'supervisor'].includes(nivel) ? null : auth.user?.turno
    const suffix = shift ? `?turno=${encodeURIComponent(shift)}` : ''
    return request(`/ordenes/${orderId}/inspecciones${suffix}`, {
      mock: async () => ESCANEOS_INICIALES.map((scan) => ({ ...scan, inspector: auth.user?.nombre })),
    })
  },
  // Los N escaneos más recientes de una orden (de cualquier día y turno), para la pantalla de órdenes
  ultimosEscaneos: (orderId, n = 15) => request(`/ordenes/${orderId}/inspecciones?ultimos=${n}`, { mock: async () => [] }),
  // data: { serie, resultado, defecto, evidencia?: Blob, ia?: { resultado, defecto, confianza } }.
  // Con evidencia (foto del rechazo) se envía como FormData; la IA solo queda registrada como sugerencia.
  registrarInspeccion: (orderId, { evidencia, ia, ...data }) => request(`/ordenes/${orderId}/inspecciones`, {
    method: 'POST',
    body: (() => {
      const turno = auth.user?.turno || null
      const extra = ia ? { ia_resultado: ia.resultado, ia_defecto: ia.defecto || null, ia_confianza: ia.confianza || null } : {}
      if (!evidencia) return { ...data, turno, ...extra }
      const fd = new FormData()
      Object.entries({ ...data, turno, ...extra }).forEach(([k, v]) => { if (v !== null && v !== undefined) fd.append(k, v) })
      fd.append('evidencia', evidencia, 'evidencia.jpg')
      return fd
    })(),
    mock: async () => ({ ...data, hora: new Date().toTimeString().slice(0, 5), inspector: auth.user?.nombre }),
  }),
  reporte: (params) => request(`/reportes/resumen?${new URLSearchParams(params)}`, {
    mock: async () => ({ kpis: KPIS, pareto: PARETO }),
  }),
  exportarCsv: (params) => download(`/reportes/datos.csv?${new URLSearchParams(params)}`),
  roles: () => resourceList('/roles'),
  usuarios: () => resourceList('/users'),
  crearUsuario: (data) => request('/users', { method: 'POST', body: data, mock: async () => data }),
  actualizarUsuario: (id, data) => request(`/users/${id}`, { method: 'PATCH', body: data, mock: async () => data }),
  // Credenciales QR (admin): el código en claro solo viene en la respuesta de generar
  generarQr: (id) => request(`/users/${id}/qr`, { method: 'POST', mock: async () => ({}) }),
  generarQrMasivo: (ids) => request('/users/qr-masivo', { method: 'POST', body: { ids }, mock: async () => [] }),
  revocarQr: (id) => request(`/users/${id}/qr`, { method: 'DELETE', mock: async () => null }),
  clientes: () => resourceList('/clients'),
  crearCliente: (data) => request('/clients', { method: 'POST', body: data, mock: async () => data }),
  actualizarCliente: (id, data) => request(`/clients/${id}`, { method: 'PATCH', body: data, mock: async () => data }),
  partes: () => resourceList('/part-numbers'),
  crearParte: (data) => request('/part-numbers', { method: 'POST', body: data, mock: async () => data }),
  actualizarParte: (id, data) => request(`/part-numbers/${id}`, { method: 'PATCH', body: data, mock: async () => data }),
  instrucciones: () => resourceList('/work-instructions'),
  // Se sube el PDF (FormData: part_number_id, code, revision, title, archivo). Queda en borrador.
  crearInstruccion: (formData) => request('/work-instructions', { method: 'POST', body: formData, mock: async () => ({}) }),
  // El estado y quién aprueba los decide el servidor
  aprobarInstruccion: (id) => request(`/work-instructions/${id}/aprobar`, { method: 'POST', mock: async () => ({}) }),
  obsoletaInstruccion: (id) => request(`/work-instructions/${id}/obsoleta`, { method: 'POST', mock: async () => ({}) }),
  eliminarInstruccion: (id) => request(`/work-instructions/${id}`, { method: 'DELETE', mock: async () => null }),
  verArchivoInstruccion: async (id) => {
    let response
    try {
      response = await fetch(`${BASE}/work-instructions/${id}/archivo`, {
        headers: {
          Accept: 'application/pdf, application/json',
          ...(auth.token ? { Authorization: 'Bearer ' + auth.token } : {}),
        },
      })
    } catch {
      throw Object.assign(new Error('No se pudo conectar con Laravel. Verifica que el backend esté ejecutándose.'), { sinRed: true })
    }
    if (response.status === 401) {
      auth.token = null
      auth.user = null
      location.hash = '#/login'
    }
    if (!response.ok) {
      const payload = await response.json().catch(() => ({}))
      throw new Error(errorFromPayload(payload, 'No se pudo abrir el documento. Verifica que el PDF esté disponible.'))
    }
    const url = URL.createObjectURL(await response.blob())
    const link = document.createElement('a')
    link.href = url
    link.target = '_blank'
    link.rel = 'noopener noreferrer'
    link.click()
    window.setTimeout(() => URL.revokeObjectURL(url), 60_000)
  },
  // Puntos clave (uno por línea) y especificaciones que el inspector ve en la Estación
  guardarContenidoInstruccion: (id, data) => request(`/work-instructions/${id}/contenido`, { method: 'PATCH', body: data, mock: async () => ({}) }),
  // PDF de la instrucción de una orden, como URL temporal (blob); quien lo pide debe liberarla con URL.revokeObjectURL
  pdfDeOrden: async (ordenId) => {
    if (FORCE_MOCK) throw new Error('El PDF no está disponible en modo demostración.')
    let response
    try {
      response = await fetch(`${BASE}/ordenes/${ordenId}/instruccion/archivo`, { headers: { Accept: 'application/pdf, application/json', ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}) } })
    } catch {
      throw new Error('No se pudo conectar con Laravel.')
    }
    if (!response.ok) {
      const payload = await response.json().catch(() => ({}))
      throw new Error(errorFromPayload(payload, 'No se pudo abrir el PDF de la instrucción.'))
    }
    return URL.createObjectURL(await response.blob())
  },
  // Comparación con IA: fotos de referencia OK/NG por instrucción y análisis de una foto de la pieza
  referenciasOrden: (ordenId) => request(`/ordenes/${ordenId}/referencias`, { mock: async () => [] }),
  referenciasInstruccion: (id) => request(`/work-instructions/${id}/referencias`, { mock: async () => [] }),
  subirReferencia: (id, formData) => request(`/work-instructions/${id}/referencias`, { method: 'POST', body: formData, mock: async () => ({}) }),
  eliminarReferencia: (id) => request(`/referencias/${id}`, { method: 'DELETE', mock: async () => null }),
  compararFoto: (ordenId, blob) => {
    const fd = new FormData()
    fd.append('imagen', blob, 'pieza.jpg')
    return request(`/ordenes/${ordenId}/comparar`, {
      method: 'POST',
      body: fd,
      mock: async () => ({ evaluable: true, resultado: 'OK', defecto: null, confianza: 'baja', explicacion: 'Modo demostración: sin IA real.', referencias: 0 }),
    })
  },
  // Devuelve una URL temporal (blob) de la foto; quien la pide debe liberarla con URL.revokeObjectURL
  imagenReferencia: async (id) => {
    if (FORCE_MOCK) return null
    let response
    try {
      response = await fetch(`${BASE}/referencias/${id}/imagen`, { headers: { ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}) } })
    } catch {
      throw new Error('No se pudo conectar con Laravel.')
    }
    if (!response.ok) throw new Error('La foto de referencia no está disponible.')
    return URL.createObjectURL(await response.blob())
  },
  // Trazabilidad por número de serie (historial, evidencias, sugerencia de la IA y retrabajos)
  trazabilidad: (serie) => request(`/trazabilidad?${new URLSearchParams({ serie })}`, { mock: async () => [] }),
  retrabajosPendientes: () => request('/trazabilidad/pendientes', { mock: async () => ({ total: 0, piezas: [] }) }),
  registrarRetrabajo: (id, data) => request(`/series/${id}/retrabajo`, { method: 'POST', body: data, mock: async () => ({}) }),
  imagenEvidencia: async (id) => {
    if (FORCE_MOCK) return null
    let response
    try {
      response = await fetch(`${BASE}/evidencias/${id}`, { headers: { ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}) } })
    } catch {
      throw new Error('No se pudo conectar con Laravel.')
    }
    if (!response.ok) throw new Error('La evidencia no está disponible.')
    return URL.createObjectURL(await response.blob())
  },
  defectosCatalogo: () => resourceList('/defect-catalog'),
  crearDefecto: (data) => request('/defect-catalog', { method: 'POST', body: data, mock: async () => data }),
  actualizarDefecto: (id, data) => request(`/defect-catalog/${id}`, { method: 'PATCH', body: data, mock: async () => data }),
}
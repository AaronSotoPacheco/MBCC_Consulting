import { DEFECTOS, ESCANEOS_INICIALES, KPIS, ORDERS, PARETO } from './data.js'

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

  let response
  try {
    response = await fetch(`${BASE}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    })
  } catch {
    throw new Error('No se pudo conectar con Laravel. Verifica que el backend esté ejecutándose.')
  }

  if (response.status === 401 && path !== '/login') {
    auth.token = null
    auth.user = null
    location.hash = '#/login'
  }
  if (!response.ok) {
    const payload = await response.json().catch(() => ({}))
    throw Object.assign(new Error(errorFromPayload(payload, 'Error del servidor')), { status: response.status })
  }
  if (response.status === 204) return null
  return response.json()
}

async function download(path) {
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
  link.download = 'inspecciones.csv'
  link.click()
  URL.revokeObjectURL(url)
}

export const api = {
  login: (payload) => request('/login', {
    method: 'POST',
    body: payload,
    mock: async () => ({ token: 'demo-token', user: { nombre: 'Juan Pérez García', rol: 'Inspector', turno: payload.turno } }),
  }),
  logout: () => request('/logout', { method: 'POST', mock: async () => null }),
  ordenes: () => request('/ordenes', { mock: async () => ORDERS }),
  defectos: () => request('/defectos', { mock: async () => DEFECTOS.map((description) => ({ description })) }),
  escaneos: (orderId) => {
    const shift = auth.user?.turno
    const suffix = shift ? `?turno=${encodeURIComponent(shift)}` : ''
    return request(`/ordenes/${orderId}/inspecciones${suffix}`, { mock: async () => ESCANEOS_INICIALES })
  },
  registrarInspeccion: (orderId, data) => request(`/ordenes/${orderId}/inspecciones`, {
    method: 'POST',
    body: { ...data, turno: auth.user?.turno || null },
    mock: async () => ({ ...data, hora: new Date().toTimeString().slice(0, 5) }),
  }),
  reporte: (params) => request(`/reportes/resumen?${new URLSearchParams(params)}`, {
    mock: async () => ({ kpis: KPIS, pareto: PARETO }),
  }),
  exportarCsv: (params) => download(`/reportes/datos.csv?${new URLSearchParams(params)}`),
}
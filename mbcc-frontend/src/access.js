// Niveles de uso. Coinciden con los nombres de rol en la base de datos.
//   inspector  → captura en piso (órdenes, estación, su turno)
//   supervisor → lo anterior + gestión de órdenes, reportes y control documental (instrucciones de trabajo)
//   admin      → todo: además usuarios, clientes y partes, catálogo de defectos y reabrir órdenes cerradas
//
// IMPORTANTE: esto solo decide qué se muestra. La seguridad real está en el backend.

const TODOS = ['inspector', 'supervisor', 'admin']
const GESTORES = ['supervisor', 'admin']

const ACCESO = {
  '/ordenes': TODOS,
  '/estacion': TODOS,
  '/turno': TODOS,
  '/asistencia': TODOS,
  '/trazabilidad': TODOS,
  '/reportes': GESTORES,
  '/config/instrucciones': GESTORES,
  '/config/usuarios': ['admin'],
  '/config/catalogos': ['admin'],
  '/config/defectos': ['admin'],
  '/config/qr': ['admin'],
  '/config/bitacora': ['admin'],
  '/config': GESTORES, // el supervisor entra a Configuración, pero solo ve Instrucciones de trabajo
}

export const ETIQUETA_NIVEL = { inspector: 'Inspector', supervisor: 'Supervisor', admin: 'Administrador' }

function normalizarNivel(value) {
  if (!value) return 'inspector'

  const texto = String(value)
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')

  if (texto.includes('admin')) return 'admin'
  if (texto.includes('supervisor')) return 'supervisor'
  if (texto.includes('inspector')) return 'inspector'

  return 'inspector'
}

export const nivelDe = (user) => normalizarNivel(user?.nivel ?? user?.rol)

export function puede(nivel, ruta) {
  const base = Object.keys(ACCESO).find((k) => ruta.startsWith(k))
  return base ? ACCESO[base].includes(nivel) : true
}

export const esGestor = (nivel) => GESTORES.includes(nivel)

// Acciones de estado disponibles para una orden. Reabrir una cerrada es solo del administrador.
export function accionesOrden(estado, nivel) {
  if (!esGestor(nivel)) return []
  if (estado === 'OPEN') return [{ id: 'pausar', texto: 'Pausar', a: 'PAUSED' }, { id: 'cerrar', texto: 'Cerrar', a: 'CLOSED' }]
  if (estado === 'PAUSED') return [{ id: 'reanudar', texto: 'Reanudar', a: 'OPEN' }, { id: 'cerrar', texto: 'Cerrar', a: 'CLOSED' }]
  if (estado === 'CLOSED' && nivel === 'admin') return [{ id: 'reabrir', texto: 'Reabrir', a: 'OPEN' }]
  return []
}

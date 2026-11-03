// Niveles de uso. Coinciden con los nombres de rol en la base de datos.
//   inspector  → captura en piso (órdenes, estación, su turno)
//   supervisor → lo anterior + reportes
//   admin      → todo, incluida la configuración
//
// IMPORTANTE: esto solo decide qué se muestra. La seguridad real está en el backend.

const TODOS = ['inspector', 'supervisor', 'admin']

const ACCESO = {
  '/ordenes': TODOS,
  '/estacion': TODOS,
  '/turno': TODOS,
  '/reportes': ['supervisor', 'admin'],
  '/config': ['admin'],
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

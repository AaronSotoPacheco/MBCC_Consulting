// Cola de capturas sin conexión. Guarda en IndexedDB (aguanta cerrar la pestaña o reiniciar el navegador)
// y, si el navegador no lo permite, en memoria. Cada captura lleva un id único: reenviarla nunca la duplica en el servidor.

const DB = 'mbcc_cola'
const STORE = 'pendientes'
let memoria = []
let abierta = null
let enCurso = null

function abrir() {
  if (abierta) return abierta
  abierta = new Promise((resolve) => {
    try {
      const req = indexedDB.open(DB, 1)
      req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'id' })
      req.onsuccess = () => resolve(req.result)
      req.onerror = () => resolve(null)
      req.onblocked = () => resolve(null)
    } catch { resolve(null) }
  })
  return abierta
}

const pedir = (db, modo, fn) => new Promise((resolve, reject) => {
  const tx = db.transaction(STORE, modo)
  const r = fn(tx.objectStore(STORE))
  tx.oncomplete = () => resolve(r?.result)
  tx.onerror = () => reject(tx.error)
  tx.onabort = () => reject(tx.error)
})

export function nuevoUuid() {
  if (globalThis.crypto?.randomUUID) return crypto.randomUUID()
  // Sin HTTPS randomUUID no existe: se arma con getRandomValues (o Math.random como último recurso)
  const b = new Uint8Array(16)
  if (globalThis.crypto?.getRandomValues) crypto.getRandomValues(b)
  else for (let i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256)
  b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('')
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`
}

/** item: { id, usuario, ordenId, datos, evidencia?: Blob, capturado: ISO, creado: ms, estado: 'pendiente' } */
export async function encolar(item) {
  const db = await abrir()
  if (!db) { memoria.push(item); return }
  try { await pedir(db, 'readwrite', (s) => s.put(item)) } catch { memoria.push(item) }
}

export async function listar(usuario) {
  const db = await abrir()
  let todos = []
  if (db) { try { todos = await pedir(db, 'readonly', (s) => s.getAll()) } catch { todos = [] } }
  return [...(todos || []), ...memoria].filter((i) => i.usuario === usuario).sort((a, b) => a.creado - b.creado)
}

async function guardar(item) {
  const db = await abrir()
  if (db) { try { await pedir(db, 'readwrite', (s) => s.put(item)); return } catch { /* cae a memoria */ } }
  memoria = memoria.map((m) => (m.id === item.id ? item : m))
}

export async function quitar(id) {
  memoria = memoria.filter((m) => m.id !== id)
  const db = await abrir()
  if (db) { try { await pedir(db, 'readwrite', (s) => s.delete(id)) } catch { /* nada */ } }
}

const definitivo = (e) => e?.status >= 400 && e.status < 500 && ![401, 408, 429].includes(e.status)

/**
 * Envía en orden las capturas pendientes del usuario con `enviar(item)`.
 * - Éxito (o "repetido"): se quita de la cola.
 * - Rechazo del servidor (422, 403…): queda marcada "rechazada" con el motivo, para que la persona decida.
 * - Sin red, sesión vencida o error del servidor: se detiene y se reintenta después.
 * Solo corre una sincronización a la vez.
 */
export function sincronizar(usuario, enviar) {
  if (enCurso) return enCurso
  enCurso = (async () => {
    const res = { enviadas: 0, rechazadas: 0, sinRed: false, respuestas: [] }
    for (const item of (await listar(usuario)).filter((i) => i.estado === 'pendiente')) {
      try {
        const reg = await enviar(item)
        await quitar(item.id)
        res.enviadas++
        res.respuestas.push({ item, reg })
      } catch (e) {
        if (e?.sinRed) { res.sinRed = true; break }
        if (definitivo(e)) {
          await guardar({ ...item, estado: 'rechazada', error: e.message || 'El servidor rechazó la captura.' })
          res.rechazadas++
        } else break
      }
    }
    return res
  })().finally(() => { enCurso = null })
  return enCurso
}

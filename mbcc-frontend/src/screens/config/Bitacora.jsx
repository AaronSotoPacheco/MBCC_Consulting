import { useState } from 'react'
import { api } from '../../api.js'
import { useAsync } from '../../hooks.js'
import { IconSearch } from '../../components/Icons.jsx'

const GRUPOS = [
  ['', 'Todo'], ['instruccion', 'Instrucciones'], ['orden', 'Órdenes'], ['usuario', 'Usuarios'],
  ['qr', 'Credenciales QR'], ['sesion', 'Accesos'], ['contrasena', 'Contraseñas'], ['asistencia', 'Asistencia'],
]
const ETIQUETA = Object.fromEntries(GRUPOS)
const cuando = (iso) => (iso ? new Date(iso.replace(' ', 'T')).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'medium' }) : '—')
const grupoDe = (accion) => accion.split('.')[0]
const alerta = (accion) => accion === 'sesion.fallida' || accion === 'contrasena.fallida'

// Bitácora de auditoría (solo administrador): quién aprobó, cerró, regeneró un QR o cambió accesos.
export default function Bitacora() {
  const [f, setF] = useState({ grupo: '', q: '', desde: '', hasta: '' })
  const [aplicado, setAplicado] = useState(f)
  const [pagina, setPagina] = useState(1)

  const params = { ...Object.fromEntries(Object.entries(aplicado).filter(([, v]) => v)), page: pagina }
  const { data, estado, error, recargar } = useAsync(() => api.auditoria(params), JSON.stringify(params))

  const aplicar = (e) => { e?.preventDefault(); setPagina(1); setAplicado(f) }
  const set = (k) => (e) => setF((p) => ({ ...p, [k]: e.target.value }))
  const filas = data?.data || []

  return (
    <section className="card config-list">
      <div className="config-list-header">
        <div>
          <h3>Bitácora de auditoría</h3>
          <span className="muted small">{estado === 'cargando' && !data ? 'Cargando…' : `${data?.total ?? 0} movimientos`}</span>
        </div>
      </div>

      <form className="bitacora-filtros" onSubmit={aplicar} role="search">
        <label className="field">
          <IconSearch />
          <input value={f.q} onChange={set('q')} placeholder="Buscar persona o descripción…" aria-label="Buscar" />
        </label>
        <select value={f.grupo} onChange={(e) => { const grupo = e.target.value; setF((p) => ({ ...p, grupo })); setAplicado((p) => ({ ...p, grupo })); setPagina(1) }} aria-label="Tipo de movimiento">
          {GRUPOS.map(([id, texto]) => <option key={id} value={id}>{texto}</option>)}
        </select>
        <input type="date" value={f.desde} onChange={set('desde')} aria-label="Desde" />
        <input type="date" value={f.hasta} onChange={set('hasta')} aria-label="Hasta" />
        <button className="btn btn-primary">Filtrar</button>
      </form>

      {error && <div className="config-empty"><p role="alert">{error}</p><button className="btn btn-outline" onClick={recargar}>Reintentar</button></div>}
      {!error && estado !== 'cargando' && filas.length === 0 && <p className="muted config-empty">No hay movimientos con esos filtros.</p>}

      {filas.length > 0 && (
        <div className="table-wrap">
          <table className="config-table rtable">
            <thead><tr><th>Fecha</th><th>Persona</th><th>Tipo</th><th>Movimiento</th><th>IP</th></tr></thead>
            <tbody>
              {filas.map((a) => (
                <tr key={a.id}>
                  <td className="titulo" data-label="Fecha">{cuando(a.fecha)}</td>
                  <td data-label="Persona">{a.usuario || '—'}</td>
                  <td data-label="Tipo"><span className={`pill ${alerta(a.accion) ? 'pill-warn' : 'pill-off'}`}>{ETIQUETA[grupoDe(a.accion)] || grupoDe(a.accion)}</span></td>
                  <td data-label="Movimiento">{a.resumen}</td>
                  <td data-label="IP" className="muted">{a.ip || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {data && data.last_page > 1 && (
        <nav className="pager" aria-label="Páginas de la bitácora">
          <button className="btn btn-outline" disabled={pagina <= 1} onClick={() => setPagina((p) => p - 1)}>Anterior</button>
          <span className="muted small">Página {data.current_page} de {data.last_page}</span>
          <button className="btn btn-outline" disabled={pagina >= data.last_page} onClick={() => setPagina((p) => p + 1)}>Siguiente</button>
        </nav>
      )}
    </section>
  )
}

import { useCallback, useEffect, useState } from 'react'
import { api } from '../api.js'
import { esGestor, nivelDe } from '../access.js'
import ImagenAuth from '../components/ImagenAuth.jsx'
import { Campo } from '../components/Campo.jsx'
import Modal from '../components/Modal.jsx'
import { IconSearch, IconTool } from '../components/Icons.jsx'

const ESTADO = { OK: 'badge-ok', NG: 'badge-ng', RETRABAJO: 'pill-warn', SCRAP: 'badge-ng' }
const cuando = (iso) => (iso ? new Date(iso.replace(' ', 'T')).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' }) : '—')
const cargarEvidencia = (id) => api.imagenEvidencia(id)

// Trazabilidad: busca una pieza por número de serie y muestra todo su historial.
export default function Trazabilidad({ user }) {
  const gestor = esGestor(nivelDe(user))
  const [texto, setTexto] = useState('')
  const [lista, setLista] = useState(null) // null = aún no se busca
  const [buscando, setBuscando] = useState(false)
  const [error, setError] = useState('')
  const [retrabajando, setRetrabajando] = useState(null)
  const [vista, setVista] = useState('buscar') // buscar | pendientes
  const [pend, setPend] = useState(null) // { total, piezas }
  const [errorPend, setErrorPend] = useState('')

  const cargarPendientes = useCallback(async () => {
    setErrorPend('')
    try { setPend(await api.retrabajosPendientes()) } catch (err) { setErrorPend(err.message) }
  }, [])
  useEffect(() => {
    let vivo = true
    api.retrabajosPendientes().then((d) => { if (vivo) setPend(d) }).catch((err) => { if (vivo) setErrorPend(err.message) })
    return () => { vivo = false }
  }, [])

  const buscar = async (e) => {
    e?.preventDefault()
    const serie = texto.trim()
    if (serie.length < 2) { setError('Escribe al menos 2 caracteres del número de serie.'); return }
    setBuscando(true); setError('')
    try { setLista(await api.trazabilidad(serie)) } catch (err) { setError(err.message); setLista(null) } finally { setBuscando(false) }
  }

  const actualizada = (nueva) => {
    setLista((prev) => prev && prev.map((r) => (r.id === nueva.id ? nueva : r)))
    setPend((prev) => prev && { total: Math.max(0, prev.total - 1), piezas: prev.piezas.filter((r) => r.id !== nueva.id) })
    setRetrabajando(null)
  }
  const verEnBusqueda = async (serie) => {
    setTexto(serie); setVista('buscar'); setLista(null); setError(''); setBuscando(true)
    try { setLista(await api.trazabilidad(serie)) } catch (err) { setError(err.message) } finally { setBuscando(false) }
  }

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="page-title">Trazabilidad</h1>
          <p className="muted">Busca una pieza por número de serie: dónde se inspeccionó, defectos, evidencias y retrabajos.</p>
        </div>
      </div>

      <div className="seg traza-tabs" role="group" aria-label="Vista">
        <button type="button" aria-pressed={vista === 'buscar'} onClick={() => setVista('buscar')}>Buscar serie</button>
        <button type="button" aria-pressed={vista === 'pendientes'} onClick={() => { setVista('pendientes'); cargarPendientes() }}>Retrabajos pendientes{pend ? ` (${pend.total})` : ''}</button>
      </div>

      {vista === 'pendientes' && (
        <>
          {errorPend && <p className="error" role="alert">{errorPend}</p>}
          {pend && pend.total === 0 && <p className="muted" role="status">No hay piezas pendientes de retrabajo.</p>}
          {pend && pend.total > pend.piezas.length && <p className="muted small">Mostrando las {pend.piezas.length} más antiguas de {pend.total}.</p>}
          <div className="traza-lista">
            {pend?.piezas.map((r) => <Tarjeta key={r.id} r={r} gestor={gestor} onRetrabajo={setRetrabajando} onVer={() => verEnBusqueda(r.serie)} />)}
          </div>
        </>
      )}

      {vista === 'buscar' && <>
      <form className="toolbar" onSubmit={buscar} role="search">
        <label className="field">
          <IconSearch />
          <input value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Número de serie (o parte)…" aria-label="Número de serie" autoComplete="off" autoFocus />
        </label>
        <button className="btn btn-primary" disabled={buscando}>{buscando ? 'Buscando…' : 'Buscar'}</button>
      </form>

      {error && <p className="error" role="alert">{error}</p>}
      {lista && lista.length === 0 && <p className="muted" role="status">No se encontró ninguna pieza con ese número de serie.</p>}

      <div className="traza-lista">
        {lista?.map((r) => <Tarjeta key={r.id} r={r} gestor={gestor} onRetrabajo={setRetrabajando} />)}
      </div>
      </>}

      {retrabajando && <FormRetrabajo registro={retrabajando} onCerrar={() => setRetrabajando(null)} onGuardado={actualizada} />}
    </>
  )
}

function Tarjeta({ r, gestor, onRetrabajo, onVer }) {
  return (
    <article className="card panel traza">
      <header className="traza-head">
        <h2>{onVer ? <button type="button" className="link-btn" onClick={onVer}>{r.serie}</button> : r.serie}</h2>
        <span className={`badge ${ESTADO[r.estado] || ''}`}>{r.estado}</span>
        {r.puede_retrabajo && <button className="btn btn-outline" onClick={() => onRetrabajo(r)}><IconTool width={16} height={16} /> Registrar retrabajo</button>}
      </header>
      <dl className="kv traza-datos">
        <div><dt>Orden</dt><dd>{r.orden || '—'}</dd></div>
        <div><dt>Parte / Cliente</dt><dd>{r.parte || '—'}{r.cliente ? ` · ${r.cliente}` : ''}</dd></div>
        <div><dt>Instrucción</dt><dd>{r.instruccion || '—'}</dd></div>
        <div><dt>Inspeccionada</dt><dd>{cuando(r.fecha)}{r.turno ? ` · Turno ${r.turno}` : ''}</dd></div>
        {gestor && <div><dt>Inspector</dt><dd>{r.inspector || '—'}</dd></div>}
      </dl>

      {r.defectos.length > 0 && (
        <section>
          <h3>Defectos</h3>
          {r.defectos.map((d) => (
            <div key={d.id} className="traza-defecto">
              <p><b>{d.defecto}</b>{d.zona ? ` · ${d.zona}` : ''}{d.notas ? ` — ${d.notas}` : ''}</p>
              {d.evidencia && <ImagenAuth id={d.id} cargar={cargarEvidencia} alt={`Evidencia: ${d.defecto}`} className="traza-foto" />}
            </div>
          ))}
        </section>
      )}

      {r.ia && (
        <p className={`small ${r.ia.coincide ? 'text-ok' : 'text-warn'}`}>
          La IA sugirió <b>{r.ia.resultado}</b>{r.ia.defecto ? ` (${r.ia.defecto})` : ''}{r.ia.confianza ? ` con confianza ${r.ia.confianza}` : ''}
          {' · '}{r.ia.coincide ? 'el inspector coincidió.' : 'el inspector decidió distinto.'}
        </p>
      )}

      {r.retrabajos.length > 0 && (
        <section>
          <h3>Retrabajos</h3>
          <ul className="traza-retrabajos">
            {r.retrabajos.map((w, i) => (
              <li key={i}>
                <b>{w.resultado === 'SCRAP' ? 'Desechada' : 'Reparada y aprobada'}</b> · {w.accion} <span className="muted small">({cuando(w.fecha)}{w.retrabajo_por ? ` · ${w.retrabajo_por}` : ''})</span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </article>
  )
}

function FormRetrabajo({ registro, onCerrar, onGuardado }) {
  const [accion, setAccion] = useState('')
  const [resultado, setResultado] = useState('PASSED')
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState('')

  const guardar = async (e) => {
    e.preventDefault()
    if (accion.trim().length < 3) { setError('Describe la acción realizada.'); return }
    setGuardando(true); setError('')
    try { onGuardado(await api.registrarRetrabajo(registro.id, { accion: accion.trim(), resultado })) } catch (err) { setError(err.message); setGuardando(false) }
  }

  return (
    <Modal
      titulo={`Retrabajo · ${registro.serie}`}
      onClose={onCerrar}
      onSubmit={guardar}
      pie={<>
        <button type="button" className="btn btn-outline" onClick={onCerrar}>Cancelar</button>
        <button className="btn btn-primary" disabled={guardando}>{guardando ? 'Guardando…' : 'Guardar'}</button>
      </>}
    >
      <Campo etiqueta="Acción realizada">
        <textarea className="area" value={accion} onChange={(e) => setAccion(e.target.value)} rows={3} maxLength={255} placeholder="Ej. Pulido de la carcasa y limpieza" autoFocus />
      </Campo>
      <div className="seg" role="group" aria-label="Resultado">
        <button type="button" aria-pressed={resultado === 'PASSED'} onClick={() => setResultado('PASSED')}>Reinspección OK</button>
        <button type="button" aria-pressed={resultado === 'SCRAP'} onClick={() => setResultado('SCRAP')}>Desechar (SCRAP)</button>
      </div>
      {error && <p className="error" role="alert">{error}</p>}
    </Modal>
  )
}

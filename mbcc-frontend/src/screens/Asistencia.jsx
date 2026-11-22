import { useEffect, useState } from 'react'
import { api } from '../api.js'
import { esGestor, nivelDe } from '../access.js'
import { fmt, useAsync } from '../hooks.js'
import Aviso from '../components/Aviso.jsx'
import Confirmar from '../components/Confirmar.jsx'
import Modal from '../components/Modal.jsx'
import { Campo, Entrada, Seleccion } from '../components/Campo.jsx'
import { errorDe } from '../components/errores.js'
import { IconCalendar, IconDownload } from '../components/Icons.jsx'

const dos = (n) => String(n).padStart(2, '0')
const aISO = (d) => `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`
const hoyISO = () => aISO(new Date())
const haceISO = (dias) => { const d = new Date(); d.setDate(d.getDate() - dias); return aISO(d) }
const dia = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const diaCorto = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' })
const horas = (h) => (h == null ? '—' : `${Number(h).toLocaleString('es-MX', { maximumFractionDigits: 1 })} h`)

const ESTADO = {
  dentro: ['pill-ok', 'Presente'],
  salio: ['pill-off', 'Ya salió'],
  ausente: ['pill-warn', 'Sin entrada'],
  inasistencia: ['pill-ng', 'Inasistencia'],
  descanso: ['pill-off', 'Descanso'],
}
const Estado = ({ id }) => <span className={`pill ${ESTADO[id][0]}`}>{ESTADO[id][1]}</span>
const Retardo = ({ min }) => (min > 0 ? <span className="pill pill-warn">Retardo {min} min</span> : null)

function Reloj() {
  const [ahora, setAhora] = useState(() => new Date())
  useEffect(() => {
    const t = setInterval(() => setAhora(new Date()), 1000)
    return () => clearInterval(t)
  }, [])
  return (
    <div className="reloj" aria-label="Hora actual">
      <b>{ahora.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })}</b>
      <span className="muted">{dia(aISO(ahora))}</span>
    </div>
  )
}

function ErrorCarga({ error, recargar }) {
  return (
    <div className="error state" role="alert">
      <span>{error}</span>
      <button className="btn btn-outline" onClick={recargar}>Reintentar</button>
    </div>
  )
}

// ---------------------------------------------------------------- mi asistencia
function MiAsistencia() {
  const { data, estado, error, recargar } = useAsync(() => api.asistenciaHoy(), 'hoy')
  const [ocupado, setOcupado] = useState(false)
  const [aviso, setAviso] = useState(null)
  const [confirmarSalida, setConfirmarSalida] = useState(false)

  const ejecutar = async (accion, ok) => {
    setOcupado(true); setAviso(null)
    try {
      await accion()
      setAviso({ tipo: 'ok', texto: ok })
      recargar()
    } catch (e) {
      setAviso({ tipo: 'error', texto: e.message })
    } finally {
      setOcupado(false); setConfirmarSalida(false)
    }
  }

  if (estado === 'error' && !data) return <ErrorCarga error={error} recargar={recargar} />
  if (!data) return <div className="card skeleton" style={{ minHeight: 220 }} aria-busy="true" />

  const reg = data.registro

  return (
    <>
      <Aviso aviso={aviso} onClose={() => setAviso(null)} />
      <section className="card checkin">
        <Reloj />
        <div className="checkin-estado" aria-live="polite">
          {data.jornada && <p className="muted small">Jornada {data.jornada} · tolerancia {data.tolerancia_min} min</p>}
          {data.estado === 'sin_entrada' && !data.inasistencia && <p>Aún no registras tu entrada de hoy.</p>}
          {data.estado === 'sin_entrada' && data.inasistencia && <p><span className="pill pill-ng">Inasistencia</span> La jornada de hoy ya terminó sin registro de entrada.</p>}
          {data.estado === 'dentro' && <p>Entraste a las <b>{reg.entrada_hora}</b> <Retardo min={reg.tarde_min} /></p>}
          {data.estado === 'salio' && <p>Entrada <b>{reg.entrada_hora}</b> · Salida <b>{reg.salida_hora}</b> · {horas(reg.horas)} <Retardo min={reg.tarde_min} /></p>}
        </div>
        {data.estado === 'sin_entrada' && !data.puede_entrar && <p className="info" role="status">{data.aviso_entrada}</p>}
        {data.estado === 'sin_entrada' && data.puede_entrar && (
          <button className="btn btn-primary checkin-btn" disabled={ocupado} onClick={() => ejecutar(api.asistenciaEntrada, 'Entrada registrada. ¡Buen turno!')}>Registrar entrada</button>
        )}
        {data.estado === 'dentro' && (
          <button className="btn btn-outline checkin-btn" disabled={ocupado} onClick={() => setConfirmarSalida(true)}>Registrar salida</button>
        )}
        {data.estado === 'salio' && <p className="muted small">Tu jornada de hoy está completa. Si hay un error, avisa a tu supervisor.</p>}
      </section>

      <h2 className="section-title">Mis últimos registros</h2>
      {data.recientes.length === 0 ? (
        <p className="muted state empty">Todavía no tienes registros.</p>
      ) : (
        <div className="rtable-wrap">
          <table className="rtable">
            <thead><tr><th>Día</th><th>Entrada</th><th>Salida</th><th className="num">Horas</th><th>Notas</th></tr></thead>
            <tbody>
              {data.recientes.map((r) => (
                <tr key={r.id}>
                  <td className="titulo">{diaCorto(r.fecha)}</td>
                  <td data-label="Entrada">{r.entrada_hora} <Retardo min={r.tarde_min} /></td>
                  <td data-label="Salida">{r.salida_hora || <span className="pill pill-warn">Sin salida</span>}</td>
                  <td className="num" data-label="Horas">{horas(r.horas)}</td>
                  <td data-label="Notas" className="muted">{r.origen === 'manual' ? `Corregido: ${r.nota}` : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {confirmarSalida && (
        <Confirmar
          titulo="Registrar salida"
          mensaje={`Se registrará tu salida a las ${new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false })}. Después ya no podrás volver a entrar el mismo día.`}
          textoConfirmar="Registrar salida"
          ocupado={ocupado}
          onConfirmar={() => ejecutar(api.asistenciaSalida, 'Salida registrada. ¡Buen descanso!')}
          onClose={() => setConfirmarSalida(false)}
        />
      )}
    </>
  )
}

// ---------------------------------------------------------------- corrección manual
function Corregir({ fila, fecha, usuarios, onClose, onGuardado }) {
  const [f, setF] = useState({
    usuario_id: fila?.usuario_id ? String(fila.usuario_id) : '',
    fecha: fila?.fecha || fecha,
    entrada: fila?.entrada_hora || '',
    salida: fila?.salida_hora || '',
    nota: '',
  })
  const [err, setErr] = useState(null)
  const [env, setEnv] = useState(false)
  const set = (k) => (e) => setF((p) => ({ ...p, [k]: e.target.value }))

  const guardar = async (e) => {
    e.preventDefault()
    setEnv(true); setErr(null)
    try {
      await api.asistenciaManual({ ...f, usuario_id: Number(f.usuario_id) || null, salida: f.salida || null })
      onGuardado()
    } catch (e2) {
      setErr(e2)
    } finally {
      setEnv(false)
    }
  }

  return (
    <Modal
      titulo={fila ? `Corregir asistencia · ${fila.nombre}` : 'Registrar asistencia manual'}
      onClose={onClose}
      onSubmit={guardar}
      pie={<><button type="button" className="btn btn-outline" onClick={onClose}>Cancelar</button><button className="btn btn-primary" disabled={env}>{env ? 'Guardando…' : 'Guardar'}</button></>}
    >
      {err && !err.errors && <Aviso aviso={{ tipo: 'error', texto: err.message }} onClose={() => setErr(null)} />}
      <div className="modal-grid">
        <Campo etiqueta="Empleado" error={errorDe(err, 'usuario_id')}>
          <Seleccion value={f.usuario_id} onChange={set('usuario_id')} disabled={!!fila} error={errorDe(err, 'usuario_id')}>
            <option value="">Selecciona…</option>
            {usuarios.map((u) => <option key={u.id} value={u.id}>{u.nombre} ({u.numero})</option>)}
          </Seleccion>
        </Campo>
        <Campo etiqueta="Fecha" error={errorDe(err, 'fecha')}>
          <Entrada type="date" value={f.fecha} max={hoyISO()} onChange={set('fecha')} error={errorDe(err, 'fecha')} />
        </Campo>
        <Campo etiqueta="Entrada" error={errorDe(err, 'entrada')}>
          <Entrada type="time" value={f.entrada} onChange={set('entrada')} error={errorDe(err, 'entrada')} />
        </Campo>
        <Campo etiqueta="Salida" ayuda="Déjala vacía si sigue en turno. Si es menor que la entrada, cuenta como día siguiente." error={errorDe(err, 'salida')}>
          <Entrada type="time" value={f.salida} onChange={set('salida')} error={errorDe(err, 'salida')} />
        </Campo>
      </div>
      <Campo etiqueta="Motivo de la corrección" ayuda="Queda en la bitácora de auditoría." error={errorDe(err, 'nota')}>
        <Entrada value={f.nota} onChange={set('nota')} maxLength={200} placeholder="Olvidó registrar su salida" error={errorDe(err, 'nota')} />
      </Campo>
    </Modal>
  )
}

// ---------------------------------------------------------------- hoy (equipo)
function Equipo() {
  const [fecha, setFecha] = useState(hoyISO())
  const { data, estado, error, recargar } = useAsync(() => api.asistenciaDia(fecha), fecha)
  const hist = useAsync(() => api.asistenciaHistorial({ desde: fecha, hasta: fecha }), `u|${fecha}`)
  const [editar, setEditar] = useState(null) // { fila } | {} para nuevo
  const [aviso, setAviso] = useState(null)
  const r = data?.resumen

  return (
    <>
      <Aviso aviso={aviso} onClose={() => setAviso(null)} />
      <div className="toolbar shift-tools">
        <label className="field">
          <IconCalendar />
          <input type="date" value={fecha} max={hoyISO()} onChange={(e) => e.target.value && setFecha(e.target.value)} aria-label="Fecha" />
        </label>
        <button className="btn btn-outline" onClick={recargar} disabled={estado === 'cargando'}>Actualizar</button>
        <button className="btn btn-primary" onClick={() => setEditar({})}>Registrar manualmente</button>
      </div>

      {estado === 'error' && <ErrorCarga error={error} recargar={recargar} />}
      {estado === 'cargando' && !data && <div className="card skeleton" style={{ minHeight: 160 }} aria-busy="true" />}

      {data && (
        <div className={estado === 'cargando' ? 'busy' : ''}>
          <div className="stats asis-stats">
            <div className="stat stat-ok"><small>Presentes</small><b>{fmt(r.presentes)}</b><span>de {fmt(r.total)}</span></div>
            <div className="stat"><small>Ya salieron</small><b>{fmt(r.salieron)}</b></div>
            <div className="stat"><small>Sin entrada</small><b>{fmt(r.ausentes)}</b></div>
            <div className="stat stat-ng"><small>Inasistencias</small><b>{fmt(r.inasistencias)}</b></div>
            <div className="stat"><small>Retardos</small><b>{fmt(r.retardos)}</b></div>
          </div>

          <div className="rtable-wrap">
            <table className="rtable">
              <thead><tr><th>Empleado</th><th>Estado</th><th>Entrada</th><th>Salida</th><th className="num">Horas</th><th /></tr></thead>
              <tbody>
                {data.filas.map((x) => (
                  <tr key={x.usuario_id}>
                    <td className="titulo">{x.nombre} <span className="muted small">{x.numero}</span></td>
                    <td data-label="Estado"><Estado id={x.estado} /> <Retardo min={x.tarde_min} /></td>
                    <td data-label="Entrada">{x.entrada_hora || '—'}</td>
                    <td data-label="Salida">{x.salida_hora || '—'}</td>
                    <td className="num" data-label="Horas">{horas(x.horas)}</td>
                    <td className="acciones">
                      {x.estado !== 'descanso' && <button className="btn btn-outline" onClick={() => setEditar({ fila: x.id ? x : { usuario_id: x.usuario_id, nombre: x.nombre } })}>{x.id ? 'Corregir' : 'Registrar'}</button>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {hist.data && <p className="muted small">Un día laborable sin entrada se marca como inasistencia al terminar la jornada. Los turnos nocturnos aparecen en el día en que iniciaron.</p>}
        </div>
      )}

      {editar && (
        <Corregir
          fila={editar.fila}
          fecha={fecha}
          usuarios={hist.data?.usuarios || data?.filas.map((x) => ({ id: x.usuario_id, nombre: x.nombre, numero: x.numero })) || []}
          onClose={() => setEditar(null)}
          onGuardado={() => { setEditar(null); setAviso({ tipo: 'ok', texto: 'Asistencia guardada y registrada en la bitácora.' }); recargar(); hist.recargar() }}
        />
      )}
    </>
  )
}

// ---------------------------------------------------------------- historial
function Historial() {
  const [f, setF] = useState({ desde: haceISO(6), hasta: hoyISO(), usuario_id: '' })
  const [aplicado, setAplicado] = useState(f)
  const [exportando, setExportando] = useState(false)
  const [aviso, setAviso] = useState(null)
  const params = Object.fromEntries(Object.entries(aplicado).filter(([, v]) => v))
  const { data, estado, error, recargar } = useAsync(() => api.asistenciaHistorial(params), JSON.stringify(params))
  const set = (k) => (e) => setF((p) => ({ ...p, [k]: e.target.value }))

  const exportar = async () => {
    setExportando(true); setAviso(null)
    try { await api.exportarAsistencia(params) } catch (e) { setAviso({ tipo: 'error', texto: e.message }) } finally { setExportando(false) }
  }

  return (
    <>
      <form className="asis-filtros" onSubmit={(e) => { e.preventDefault(); setAplicado(f) }} role="search">
        <input type="date" value={f.desde} max={f.hasta} onChange={set('desde')} aria-label="Desde" />
        <input type="date" value={f.hasta} min={f.desde} max={hoyISO()} onChange={set('hasta')} aria-label="Hasta" />
        <div className="field">
          <select value={f.usuario_id} onChange={set('usuario_id')} aria-label="Empleado">
            <option value="">Todo el equipo</option>
            {(data?.usuarios || []).map((u) => <option key={u.id} value={u.id}>{u.nombre}</option>)}
          </select>
        </div>
        <button className="btn btn-primary">Filtrar</button>
        <button type="button" className="btn btn-outline" onClick={exportar} disabled={exportando}><IconDownload width={16} height={16} /> {exportando ? 'Exportando…' : 'Exportar CSV'}</button>
      </form>
      <Aviso aviso={aviso} onClose={() => setAviso(null)} />

      {estado === 'error' && <ErrorCarga error={error} recargar={recargar} />}
      {estado === 'cargando' && !data && <div className="card skeleton" style={{ minHeight: 160 }} aria-busy="true" />}

      {data && (
        <div className={estado === 'cargando' ? 'busy' : ''}>
          <div className="stats asis-stats">
            <div className="stat"><small>Registros</small><b>{fmt(data.resumen.registros)}</b></div>
            <div className="stat"><small>Horas trabajadas</small><b>{fmt(data.resumen.horas)}</b></div>
            <div className="stat"><small>Retardos</small><b>{fmt(data.resumen.retardos)}</b></div>
            <div className="stat"><small>Sin salida</small><b>{fmt(data.resumen.sin_salida)}</b></div>
            <div className="stat stat-ng"><small>Inasistencias</small><b>{fmt(data.resumen.inasistencias)}</b></div>
          </div>
          {data.filas.length === 0 ? (
            <p className="muted state empty">No hay registros en este periodo.</p>
          ) : (
            <div className="rtable-wrap">
              <table className="rtable">
                <thead><tr><th>Día</th><th>Empleado</th><th>Entrada</th><th>Salida</th><th className="num">Horas</th><th>Notas</th></tr></thead>
                <tbody>
                  {data.filas.map((x) => (
                    <tr key={`${x.usuario_id}|${x.fecha}`}>
                      <td className="titulo">{diaCorto(x.fecha)}</td>
                      <td data-label="Empleado">{x.nombre}</td>
                      <td data-label="Entrada">{x.estado === 'inasistencia' ? <Estado id="inasistencia" /> : <>{x.entrada_hora} <Retardo min={x.tarde_min} /></>}</td>
                      <td data-label="Salida">{x.estado === 'inasistencia' ? '—' : x.salida_hora || <span className="pill pill-warn">Sin salida</span>}</td>
                      <td className="num" data-label="Horas">{horas(x.horas)}</td>
                      <td data-label="Notas" className="muted">{x.origen === 'manual' ? `Corregido: ${x.nota}` : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------- pantalla
export default function Asistencia({ user }) {
  const gestor = esGestor(nivelDe(user))
  const [vista, setVista] = useState('mia')
  const actual = gestor ? vista : 'mia'

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="page-title">Asistencia</h1>
          <p className="muted">Registra tu entrada y salida{gestor ? '; revisa y corrige la del equipo' : ''}.</p>
        </div>
      </div>

      {gestor && (
        <div className="toolbar">
          <div className="seg" role="group" aria-label="Vista">
            <button type="button" aria-pressed={actual === 'mia'} onClick={() => setVista('mia')}>Mi asistencia</button>
            <button type="button" aria-pressed={actual === 'equipo'} onClick={() => setVista('equipo')}>Equipo</button>
            <button type="button" aria-pressed={actual === 'historial'} onClick={() => setVista('historial')}>Historial</button>
          </div>
        </div>
      )}

      {actual === 'mia' && <MiAsistencia />}
      {actual === 'equipo' && <Equipo />}
      {actual === 'historial' && <Historial />}
    </>
  )
}

import { useCallback, useMemo, useState } from 'react'
import { api } from '../api.js'
import { accionesOrden, esGestor, nivelDe } from '../access.js'
import { fmt, useAlertas, useAsync } from '../hooks.js'
import Aviso from '../components/Aviso.jsx'
import Confirmar from '../components/Confirmar.jsx'
import OrdenForm from '../components/OrdenForm.jsx'
import { IconSearch, IconFilter, IconPlay, IconChevron, IconPlus, IconEdit, IconPause, IconRotate } from '../components/Icons.jsx'

const ESTADOS = {
  OPEN: { texto: 'Abierta', pill: 'pill-ok', color: 'text-ok' },
  PAUSED: { texto: 'Pausada', pill: 'pill-warn', color: 'text-warn' },
  CLOSED: { texto: 'Cerrada', pill: 'pill-off', color: 'text-off' },
}
const RANGO = { OPEN: 0, PAUSED: 1, CLOSED: 2 }
const ordenar = (a, b) => RANGO[a.estado] - RANGO[b.estado] || a.so.localeCompare(b.so)
const RESULTADO = { pausar: 'pausada', reanudar: 'reanudada', cerrar: 'cerrada', reabrir: 'reabierta' }
const ICONO = { pausar: IconPause, reanudar: IconPlay, cerrar: null, reabrir: IconRotate }

// Últimos escaneos de una orden: se piden al abrir el panel (no al cargar la lista) para no cargar de más.
function UltimosEscaneos({ ordenId }) {
  const [abierto, setAbierto] = useState(false)
  const [lista, setLista] = useState(null)
  const [error, setError] = useState('')

  const cargar = useCallback(async () => {
    setError('')
    try { setLista(await api.ultimosEscaneos(ordenId, 15)) } catch (e) { setError(e.message) }
  }, [ordenId])

  const alternar = () => {
    const nuevo = !abierto
    setAbierto(nuevo)
    if (nuevo) cargar()
  }

  return (
    <div className="escaneos">
      <button type="button" className="btn btn-ghost btn-sm" aria-expanded={abierto} onClick={alternar}>
        Últimos 15 escaneos <IconChevron width={14} height={14} className={abierto ? 'girado' : ''} />
      </button>
      {abierto && (
        <div className="escaneos-lista">
          {error && <p className="error" role="alert">{error} <button type="button" className="link-btn link-chico" onClick={cargar}>Reintentar</button></p>}
          {!error && !lista && <p className="muted small" role="status">Cargando…</p>}
          {lista && lista.length === 0 && <p className="muted small">Todavía no hay escaneos en esta orden.</p>}
          {lista && lista.length > 0 && (
            <ul>
              {lista.map((e) => (
                <li key={`${e.serie}-${e.fecha}-${e.hora}`}>
                  <span className="escaneo-serie">{e.serie}</span>
                  <span className={`badge ${e.resultado === 'OK' ? 'badge-ok' : 'badge-ng'}`}>{e.resultado}</span>
                  <span className="muted small escaneo-meta">{e.defecto ? `${e.defecto} · ` : ''}{e.fecha} {e.hora}{e.inspector ? ` · ${e.inspector}` : ''}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}

export default function Orders({ user, onOpen }) {
  const nivel = nivelDe(user)
  const gestor = esGestor(nivel)

  const alertas = useAlertas(gestor)
  const { data, estado, error, recargar: cargar, setData } = useAsync(() => api.ordenes(gestor), String(gestor))
  const ordenes = useMemo(() => [...(data || [])].sort(ordenar), [data])
  const [q, setQ] = useState('')
  const [filtro, setFiltro] = useState('activas') // activas = abiertas + pausadas
  const [modal, setModal] = useState(null) // { tipo: 'nueva' } | { tipo: 'editar', orden } | { tipo: 'confirmar', orden, accion }
  const [ocupado, setOcupado] = useState(null) // id de la orden que se está actualizando
  const [aviso, setAviso] = useState(null)
  const [errConfirmar, setErrConfirmar] = useState('')

  const visibles = useMemo(() => {
    const t = q.trim().toLowerCase()
    return ordenes.filter((o) =>
      (filtro === 'todas' || (filtro === 'activas' ? o.estado !== 'CLOSED' : o.estado === filtro)) &&
      (!t || [o.parte, o.so, o.cliente, o.descripcion].some((v) => v?.toLowerCase().includes(t))))
  }, [ordenes, q, filtro])

  const reemplazar = (n) => setData((prev) => [...(prev || []).filter((x) => x.id !== n.id), n])

  const cambiarEstado = async (o, accion) => {
    setOcupado(o.id); setAviso(null); setErrConfirmar('')
    try {
      const n = await api.estadoOrden(o.id, accion.a)
      reemplazar(n)
      setModal(null)
      setAviso({ tipo: 'ok', texto: `Orden ${n.so} ${RESULTADO[accion.id]}.` })
    } catch (e) {
      if (modal?.tipo === 'confirmar') setErrConfirmar(e.message)
      else setAviso({ tipo: 'error', texto: e.message })
    } finally {
      setOcupado(null)
    }
  }

  // Pausar y reanudar son inmediatos; cerrar y reabrir piden confirmación
  const pedir = (o, accion) => {
    if (accion.id === 'cerrar' || accion.id === 'reabrir') { setErrConfirmar(''); setModal({ tipo: 'confirmar', orden: o, accion }) }
    else cambiarEstado(o, accion)
  }

  const guardada = (n, creada) => {
    reemplazar(n)
    setModal(null)
    setAviso({ tipo: 'ok', texto: creada ? `Orden ${n.so} creada y abierta.` : `Orden ${n.so} actualizada.` })
  }

  const conf = modal?.tipo === 'confirmar' ? modal : null

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="page-title">Órdenes Activas</h1>
          <p className="muted">Selecciona una orden para comenzar con la inspección</p>
        </div>
        {gestor && (
          <button className="btn btn-primary" onClick={() => setModal({ tipo: 'nueva' })}>
            <IconPlus width={16} height={16} /> Nueva orden
          </button>
        )}
      </div>

      <Aviso aviso={aviso} onClose={() => setAviso(null)} />

      {alertas.length > 0 && (
        <section className="alertas" role="alert" aria-label="Alertas de rechazos">
          <h2>Rechazos altos ({alertas.length})</h2>
          <ul>
            {alertas.map((a) => (
              <li key={a.orden_id} className={`alerta alerta-${a.nivel}`}>
                <b>{a.so}</b>{a.parte ? ` · ${a.parte}` : ''} — <b>{a.tasa_ventana}% NG</b> en las últimas {a.piezas_ventana} piezas
                {a.defecto_principal ? `, sobre todo “${a.defecto_principal}”` : ''}. Acumulado: {a.tasa_total}% de {a.inspeccionadas}.
              </li>
            ))}
          </ul>
        </section>
      )}

      <div className="toolbar">
        <label className="field grow">
          <IconSearch />
          <input
            type="search" placeholder="Buscar por número de parte o número de orden…"
            value={q} onChange={(e) => setQ(e.target.value)} aria-label="Buscar órdenes"
          />
        </label>
        <label className="field select-field">
          <IconFilter />
          <select value={filtro} onChange={(e) => setFiltro(e.target.value)} aria-label="Filtrar por estado">
            <option value="activas">Todas las órdenes</option>
            <option value="OPEN">Abiertas</option>
            <option value="PAUSED">Pausadas</option>
            {gestor && <option value="CLOSED">Cerradas</option>}
            {gestor && <option value="todas">Incluir cerradas</option>}
          </select>
          <IconChevron />
        </label>
      </div>

      {data && ordenes.length > 0 && (
        <p className="muted small count" aria-live="polite">
          {visibles.length} de {ordenes.length} {ordenes.length === 1 ? 'orden' : 'órdenes'}
        </p>
      )}

      {estado === 'error' && (
        <div className="error state" role="alert">
          <span>{error}</span>
          <button className="btn btn-outline" onClick={cargar}>Reintentar</button>
        </div>
      )}

      {data && ordenes.length === 0 && (
        <p className="muted state">No hay órdenes abiertas ni pausadas por el momento.{gestor ? ' Usa "Nueva orden" para crear la primera.' : ''}</p>
      )}
      {data && ordenes.length > 0 && visibles.length === 0 && (
        <p className="muted state">Ninguna orden coincide con la búsqueda o el filtro.</p>
      )}

      <div className="grid-cards" aria-busy={estado === 'cargando'}>
        {estado === 'cargando' && !data && [0, 1, 2].map((i) => <div key={i} className="card order skeleton" aria-hidden="true" />)}

        {data && visibles.map((o) => {
          const est = ESTADOS[o.estado] || ESTADOS.PAUSED
          const pct = o.meta ? Math.min(100, (o.inspeccionadas / o.meta) * 100) : 0
          const acciones = accionesOrden(o.estado, nivel)
          const trabajando = ocupado === o.id
          return (
            <article key={o.id} className="card order" aria-label={`Orden ${o.so}`} aria-busy={trabajando}>
              <span className={`pill ${est.pill}`}>{est.texto}</span>

              <dl className="kv two">
                <div><dt>Cliente</dt><dd>{o.cliente}</dd></div>
                <div><dt>Orden de servicio</dt><dd>{o.so}</dd></div>
                <div><dt>Número de parte</dt><dd className="strong">{o.parte}</dd></div>
                <div><dt>Descripción</dt><dd>{o.descripcion || '—'}</dd></div>
              </dl>

              <dl className="kv two">
                <div><dt>Meta de piezas</dt><dd>{fmt(o.meta)}</dd></div>
                <div><dt>Estado</dt><dd className={est.color}>{est.texto}</dd></div>
              </dl>

              <div className="progress-box">
                <div className="progress-line">
                  <span>{fmt(o.inspeccionadas)} inspeccionadas · {pct.toFixed(1)}%</span>
                  {o.rechazadas > 0 && <span className="text-bad">{fmt(o.rechazadas)} NG</span>}
                </div>
                <div className="progress" role="progressbar" aria-valuemin={0} aria-valuemax={o.meta} aria-valuenow={o.inspeccionadas} aria-label="Avance de inspección">
                  <i style={{ width: `${pct}%` }} />
                </div>
              </div>

              {o.wi && <p className="wi-line muted small">{o.wi.codigo} · Rev. {o.wi.revision}</p>}
              <UltimosEscaneos ordenId={o.id} />

              {o.estado === 'OPEN' ? (
                <button className="btn btn-primary btn-block" onClick={() => onOpen(o)}>
                  <IconPlay width={14} height={14} /> Iniciar Inspección en esta Orden
                </button>
              ) : (
                <button className="btn btn-outline btn-block" disabled title={o.estado === 'PAUSED' ? 'Un supervisor debe reanudar la orden' : 'La orden está cerrada'}>
                  {o.estado === 'PAUSED' ? 'Orden pausada' : 'Orden cerrada'}
                </button>
              )}

              {gestor && (acciones.length > 0 || o.estado !== 'CLOSED') && (
                <div className="card-actions" role="group" aria-label={`Administrar orden ${o.so}`}>
                  {acciones.map((a) => {
                    const Icono = ICONO[a.id]
                    return (
                      <button key={a.id} className="btn btn-ghost btn-sm" disabled={trabajando} onClick={() => pedir(o, a)}>
                        {Icono && <Icono width={14} height={14} />} {a.texto}
                      </button>
                    )
                  })}
                  {o.estado !== 'CLOSED' && (
                    <button className="btn btn-ghost btn-sm" disabled={trabajando} onClick={() => setModal({ tipo: 'editar', orden: o })}>
                      <IconEdit width={14} height={14} /> Editar
                    </button>
                  )}
                </div>
              )}
            </article>
          )
        })}
      </div>

      {modal?.tipo === 'nueva' && <OrdenForm onClose={() => setModal(null)} onGuardado={guardada} />}
      {modal?.tipo === 'editar' && <OrdenForm orden={modal.orden} onClose={() => setModal(null)} onGuardado={guardada} />}
      {conf && (
        <Confirmar
          titulo={conf.accion.id === 'cerrar' ? `Cerrar orden ${conf.orden.so}` : `Reabrir orden ${conf.orden.so}`}
          mensaje={conf.accion.id === 'cerrar'
            ? `Llevas ${fmt(conf.orden.inspeccionadas)} de ${fmt(conf.orden.meta)} piezas inspeccionadas. Una orden cerrada deja de aparecer entre las activas y solo un administrador puede reabrirla.`
            : `La orden ${conf.orden.so} volverá a estar abierta y disponible para inspección.`}
          textoConfirmar={conf.accion.id === 'cerrar' ? 'Cerrar orden' : 'Reabrir orden'}
          peligro={conf.accion.id === 'cerrar'}
          ocupado={ocupado === conf.orden.id}
          error={errConfirmar}
          onConfirmar={() => cambiarEstado(conf.orden, conf.accion)}
          onClose={() => setModal(null)}
        />
      )}
    </>
  )
}

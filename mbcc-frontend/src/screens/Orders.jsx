import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api.js'
import { IconSearch, IconFilter, IconPlay, IconChevron } from '../components/Icons.jsx'

const ESTADOS = {
  OPEN: { texto: 'Abierta', pill: 'pill-ok', color: 'text-ok' },
  PAUSED: { texto: 'Pausada', pill: 'pill-warn', color: 'text-warn' },
}
const fmt = (value) => (Number(value) || 0).toLocaleString('es-MX')

export default function Orders({ onOpen }) {
  const [ordenes, setOrdenes] = useState([])
  const [q, setQ] = useState('')
  const [filtro, setFiltro] = useState('todas')
  const [estado, setEstado] = useState('cargando')
  const [error, setError] = useState('')

  const cargar = useCallback(() => {
    setEstado('cargando')
    setError('')
    api.ordenes()
      .then((items) => { setOrdenes(items); setEstado('listo') })
      .catch((requestError) => { setError(requestError.message || 'No se pudieron cargar las órdenes.'); setEstado('error') })
  }, [])

  useEffect(() => {
    let active = true
    api.ordenes()
      .then((items) => { if (active) { setOrdenes(items); setEstado('listo') } })
      .catch((requestError) => { if (active) { setError(requestError.message || 'No se pudieron cargar las órdenes.'); setEstado('error') } })
    return () => { active = false }
  }, [])

  const visibles = useMemo(() => {
    const term = q.trim().toLowerCase()
    return ordenes.filter((order) =>
      (filtro === 'todas' || order.estado === filtro) &&
      (!term || [order.parte, order.so, order.cliente, order.descripcion].some((value) => value?.toLowerCase().includes(term))))
  }, [ordenes, q, filtro])

  return (
    <>
      <h1 className="page-title">Órdenes Activas</h1>
      <p className="muted">Selecciona una orden para comenzar con la inspección</p>
      <div className="toolbar">
        <label className="field grow"><IconSearch /><input placeholder="Buscar por número de parte o número de orden…" value={q} onChange={(event) => setQ(event.target.value)} /></label>
        <label className="field select-field"><IconFilter /><select value={filtro} onChange={(event) => setFiltro(event.target.value)}><option value="todas">Todas las órdenes</option><option value="OPEN">Abiertas</option><option value="PAUSED">Pausadas</option></select><IconChevron /></label>
      </div>
      {estado === 'listo' && ordenes.length > 0 && <p className="muted small count" aria-live="polite">{visibles.length} de {ordenes.length} {ordenes.length === 1 ? 'orden' : 'órdenes'}</p>}
      {estado === 'error' && <div className="error state" role="alert"><span>{error}</span><button className="btn btn-outline" onClick={cargar}>Reintentar</button></div>}
      {estado === 'listo' && ordenes.length === 0 && <p className="muted state">No hay órdenes abiertas ni pausadas por el momento.</p>}
      {estado === 'listo' && ordenes.length > 0 && visibles.length === 0 && <p className="muted state">Ninguna orden coincide con la búsqueda.</p>}
      <div className="grid-cards" aria-busy={estado === 'cargando'}>
        {estado === 'cargando' && [0, 1, 2].map((index) => <div key={index} className="card order skeleton" aria-hidden="true" />)}
        {estado === 'listo' && visibles.map((order) => {
          const currentStatus = ESTADOS[order.estado] || { texto: order.estado, pill: 'pill-warn', color: 'text-warn' }
          const progress = order.meta ? Math.min(100, (order.inspeccionadas / order.meta) * 100) : 0
          return (
            <article key={order.id} className="card order" aria-label={`Orden ${order.so}`}>
              <span className={`pill ${currentStatus.pill}`}>{currentStatus.texto}</span>
              <dl className="kv two">
                <div><dt>Cliente</dt><dd>{order.cliente}</dd></div>
                <div><dt>Orden de servicio</dt><dd>{order.so}</dd></div>
                <div><dt>Número de parte</dt><dd className="strong">{order.parte}</dd></div>
                <div><dt>Descripción</dt><dd>{order.descripcion || '—'}</dd></div>
              </dl>
              <dl className="kv two">
                <div><dt>Meta de piezas</dt><dd>{fmt(order.meta)}</dd></div>
                <div><dt>Estado</dt><dd className={currentStatus.color}>{currentStatus.texto}</dd></div>
              </dl>
              <div className="progress-box">
                <div className="progress-line">
                  <span>{fmt(order.inspeccionadas)} inspeccionadas · {progress.toFixed(1)}%</span>
                  {order.rechazadas > 0 && <span className="text-bad">{fmt(order.rechazadas)} NG</span>}
                </div>
                <div className="progress" role="progressbar" aria-valuemin={0} aria-valuemax={order.meta} aria-valuenow={order.inspeccionadas} aria-label="Avance de inspección">
                  <i style={{ width: `${progress}%` }} />
                </div>
              </div>
              {order.wi && <p className="wi-line muted small">{order.wi.codigo} · Rev. {order.wi.revision}</p>}
              <button className="btn btn-primary btn-block" onClick={() => onOpen(order)} disabled={order.estado !== 'OPEN'}>
                <IconPlay width={14} height={14} /> {order.estado === 'OPEN' ? 'Iniciar Inspección en esta Orden' : 'Orden pausada'}
              </button>
            </article>
          )
        })}
      </div>
    </>
  )
}
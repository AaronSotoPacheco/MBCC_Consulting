import { useEffect, useMemo, useState } from 'react'
import { api } from '../api.js'
import { IconSearch, IconFilter, IconPlay, IconChevron } from '../components/Icons.jsx'

export default function Orders({ onOpen }) {
  const [ordenes, setOrdenes] = useState([])
  const [q, setQ] = useState('')
  const [filtro, setFiltro] = useState('todas')
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.ordenes().then(setOrdenes).catch((requestError) => setError(requestError.message)).finally(() => setCargando(false))
  }, [])

  const visibles = useMemo(() => {
    const term = q.trim().toLowerCase()
    return ordenes.filter((order) =>
      (filtro === 'todas' || order.estado.toLowerCase() === filtro) &&
      (!term || order.parte.toLowerCase().includes(term) || order.so.toLowerCase().includes(term) || order.cliente.toLowerCase().includes(term)))
  }, [ordenes, q, filtro])

  return (
    <>
      <h1 className="page-title">Órdenes Activas</h1>
      <p className="muted">Selecciona una orden para comenzar con la inspección</p>
      <div className="toolbar">
        <label className="field grow"><IconSearch /><input placeholder="Buscar por número de parte o número de orden…" value={q} onChange={(event) => setQ(event.target.value)} /></label>
        <label className="field select-field"><IconFilter /><select value={filtro} onChange={(event) => setFiltro(event.target.value)}><option value="todas">Todas las órdenes</option><option value="abierta">Abiertas</option><option value="pausada">Pausadas</option></select><IconChevron /></label>
      </div>
      {cargando && <p className="muted">Cargando órdenes…</p>}
      {error && <p className="error" role="alert">{error}</p>}
      {!cargando && !error && visibles.length === 0 && <p className="muted">No hay órdenes que coincidan con la búsqueda.</p>}
      <div className="grid-cards">
        {visibles.map((order) => (
          <article key={order.id} className="card order">
            <span className={`pill ${order.estado === 'Abierta' ? 'pill-ok' : 'pill-warn'}`}>{order.estado === 'Abierta' ? '◔' : '◑'} {order.estado}</span>
            <dl className="kv two">
              <div><dt>Cliente</dt><dd>{order.cliente}</dd></div>
              <div><dt>Orden de servicio</dt><dd>{order.so}</dd></div>
              <div><dt>Número de parte</dt><dd className="strong">{order.parte}</dd></div>
              <div><dt>Descripción</dt><dd>{order.descripcion}</dd></div>
            </dl>
            <dl className="kv two">
              <div><dt>Meta de piezas</dt><dd>{Number(order.meta).toLocaleString('es-MX')}</dd></div>
              <div><dt>Estado</dt><dd className={order.estado === 'Abierta' ? 'text-ok' : 'text-warn'}>{order.estado}</dd></div>
            </dl>
            <button className="btn btn-primary btn-block" onClick={() => onOpen(order)} disabled={order.estado !== 'Abierta'}>
              <IconPlay width={14} height={14} /> {order.estado === 'Abierta' ? 'Iniciar Inspección en esta Orden' : 'Orden pausada'}
            </button>
          </article>
        ))}
      </div>
    </>
  )
}
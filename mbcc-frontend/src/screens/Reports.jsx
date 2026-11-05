import { useEffect, useState } from 'react'
import { api, auth } from '../api.js'
import { nivelDe } from '../access.js'
import { IconCalendar, IconChevron, IconSearch, IconGauge, IconTool, IconTrash, IconTable } from '../components/Icons.jsx'

const hoy = new Date()
const DEFAULT_FILTERS = {
  desde: new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().slice(0, 10),
  hasta: hoy.toISOString().slice(0, 10),
  turno: 'todos',
  orden: 'todas',
}

export default function Reports() {
  const [desde, setDesde] = useState(DEFAULT_FILTERS.desde)
  const [hasta, setHasta] = useState(DEFAULT_FILTERS.hasta)
  const [turno, setTurno] = useState(DEFAULT_FILTERS.turno)
  const [orden, setOrden] = useState(DEFAULT_FILTERS.orden)
  const [data, setData] = useState(null)
  const [ordenes, setOrdenes] = useState([])
  const [error, setError] = useState('')

  const cargar = async () => {
    setError('')
    try { setData(await api.reporte({ desde, hasta, turno, orden })) } catch (requestError) { setError(requestError.message) }
  }
  useEffect(() => {
    let active = true
    api.reporte(DEFAULT_FILTERS)
      .then((report) => { if (active) setData(report) })
      .catch((requestError) => { if (active) setError(requestError.message) })
    api.ordenes()
      .then((items) => { if (active) setOrdenes(items) })
      .catch((requestError) => { if (active) setError(requestError.message) })

    return () => { active = false }
  }, [])

  const exportar = async () => {
    setError('')
    try { await api.exportarCsv({ desde, hasta, turno, orden }) } catch (requestError) { setError(requestError.message) }
  }

  const k = data?.kpis
  const max = data ? Math.max(...(data.pareto || []).map((item) => item.cantidad), 1) : 1
  const ticks = [0, 20, 40, 60, 80]
  const ordenesActivas = data?.ordenesActivas || []
  const retrabajos = data?.retrabajos || []
  const inspecciones = data?.inspecciones || []
  const verInspector = ['admin', 'supervisor'].includes(nivelDe(auth.user))

  return (
    <>
      <h1 className="page-title">Reportes y Análisis</h1>
      <p className="muted">Consulta el desempeño del turno y genera reportes oficiales</p>
      <section className="card filters">
        <label><small>Rango de fechas</small><div className="field range"><IconCalendar /><input type="date" value={desde} onChange={(event) => setDesde(event.target.value)} aria-label="Desde" /><span>–</span><input type="date" value={hasta} onChange={(event) => setHasta(event.target.value)} aria-label="Hasta" /></div></label>
        <label><small>Turno</small><div className="field"><select value={turno} onChange={(event) => setTurno(event.target.value)}><option value="todos">Todos los turnos</option><option value="1">Turno 1</option><option value="2">Turno 2</option><option value="3">Turno 3</option></select><IconChevron /></div></label>
        <label><small>Órdenes</small><div className="field"><select value={orden} onChange={(event) => setOrden(event.target.value)}><option value="todas">Todas las órdenes</option>{ordenes.map((item) => <option key={item.id} value={item.so}>{item.so}</option>)}</select><IconChevron /></div></label>
        <button className="btn btn-primary apply" onClick={cargar}><IconSearch width={16} height={16} /> Aplicar filtros</button>
      </section>
      {error && <p className="error" role="alert">{error}</p>}

      {k && <div className="kpis">
        <article className="card kpi kpi-ok"><span className="kpi-icon"><IconGauge width={26} height={26} /></span><div><small>Eficiencia general</small><b>{k.eficiencia.valor}%</b><span className="text-ok">{k.eficiencia.delta}</span></div></article>
        <article className="card kpi kpi-info"><span className="kpi-icon"><IconTool width={26} height={26} /></span><div><small>Retrabajos</small><b>{k.retrabajos.valor}</b><span className="muted">{k.retrabajos.detalle}</span></div></article>
        <article className="card kpi kpi-bad"><span className="kpi-icon"><IconTrash width={26} height={26} /></span><div><small>Scrap</small><b>{k.scrap.valor}</b><span className="text-bad">● {k.scrap.detalle}</span></div></article>
      </div>}

      <div className="reports-grid">
        <section className="card pareto"><h2>Pareto de Defectos</h2>{data && <div className="chart" role="img" aria-label="Gráfica de Pareto de defectos">
          <div className="y-axis">{[...ticks].reverse().map((tick) => <span key={tick}>{tick}</span>)}</div>
          <div className="plot">{ticks.map((tick) => <i key={tick} className="gridline" style={{ bottom: `${(tick / 80) * 100}%` }} />)}</div>
          <div className="plot"><div className="bars">{(data.pareto || []).map((item) => <div className="bar-col" key={item.defecto}><span className="bar-val">{item.cantidad}</span><div className="bar" style={{ height: `${(item.cantidad / Math.max(80, max)) * 100}%` }} /><span className="bar-label">{item.defecto}</span></div>)}</div></div>
        </div>}</section>
        <section className="card export"><h2>Exportar Reporte</h2><p className="muted small">Descarga los resultados del periodo seleccionado.</p><button className="btn btn-outline btn-block" onClick={exportar}><IconTable width={16} height={16} /> Exportar Datos a CSV</button></section>
      </div>

      <div className="report-panels">
        <section className="card report-panel">
          <h2>Órdenes activas</h2>
          {ordenesActivas.length === 0 ? (
            <p className="muted">No hay órdenes activas en este momento.</p>
          ) : (
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Orden</th>
                    <th>Cliente</th>
                    <th>Parte</th>
                    <th>Estado</th>
                    <th>Inspeccionadas</th>
                    <th>NG</th>
                  </tr>
                </thead>
                <tbody>
                  {ordenesActivas.map((order) => (
                    <tr key={order.id}>
                      <td>{order.orden}</td>
                      <td>{order.cliente}</td>
                      <td>{order.parte}</td>
                      <td><span className={`pill ${order.estado === 'OPEN' ? 'pill-ok' : 'pill-warn'}`}>{order.estado === 'OPEN' ? 'Abierta' : 'Pausada'}</span></td>
                      <td>{order.inspeccionadas}</td>
                      <td>{order.rechazadas}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <section className="card report-panel">
          <h2>Retrabajos</h2>
          {retrabajos.length === 0 ? (
            <p className="muted">No hay retrabajos registrados en el periodo.</p>
          ) : (
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Orden</th>
                    <th>Serie</th>
                    <th>Parte</th>
                    <th>Acción</th>
                    <th>Resultado</th>
                    <th>Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  {retrabajos.map((item) => (
                    <tr key={item.id}>
                      <td>{item.orden}</td>
                      <td>{item.serie}</td>
                      <td>{item.parte}</td>
                      <td>{item.accion}</td>
                      <td>{item.resultado}</td>
                      <td>{item.fecha ? new Date(item.fecha).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>

      <section className="card report-panel">
        <h2>Detalle de inspecciones</h2>
        {inspecciones.length === 0 ? (
          <p className="muted">No hay inspecciones registradas en el periodo seleccionado.</p>
        ) : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Orden</th>
                  <th>Parte</th>
                  <th>Serie</th>
                  <th>Resultado</th>
                  <th>Defecto</th>
                  {verInspector && <th>Inspector</th>}
                  <th>Turno</th>
                </tr>
              </thead>
              <tbody>
                {inspecciones.map((item, index) => (
                  <tr key={`${item.serie}-${index}`}>
                    <td>{item.fecha ? new Date(item.fecha).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '—'}</td>
                    <td>{item.orden || '—'}</td>
                    <td>{item.parte || '—'}</td>
                    <td>{item.serie}</td>
                    <td><span className={`badge ${item.resultado === 'OK' ? 'badge-ok' : 'badge-ng'}`}>{item.resultado}</span></td>
                    <td>{item.defecto || '—'}</td>
                    {verInspector && <td>{item.inspector || '—'}</td>}
                    <td>{item.turno || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </>
  )
}
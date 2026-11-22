import { useState } from 'react'
import { api } from '../api.js'
import { esGestor, nivelDe } from '../access.js'
import { fmt, useAsync } from '../hooks.js'
import { IconCalendar } from '../components/Icons.jsx'

const dos = (n) => String(n).padStart(2, '0')
const hoyISO = () => { const d = new Date(); return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}` }
const pct = (n, t) => (t ? `${((n / t) * 100).toFixed(1)}%` : '—')
const dia = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

// Mi Turno: lo inspeccionado en el día. Inspector: solo lo suyo. Supervisor y admin pueden cambiar a "Equipo".
export default function Shift({ user }) {
  const gestor = esGestor(nivelDe(user))
  const [fecha, setFecha] = useState(hoyISO())
  const [alcance, setAlcance] = useState('mio')
  const { data, estado, error, recargar } = useAsync(() => api.turno({ fecha, alcance }), `${fecha}|${alcance}`)

  const k = data?.kpis
  const equipo = alcance === 'equipo'

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="page-title">Mi Turno</h1>
          <p className="muted">{equipo ? 'Lo inspeccionado por todo el equipo' : 'Lo que has inspeccionado'} · {dia(fecha)}</p>
        </div>
      </div>

      <div className="toolbar shift-tools">
        <label className="field">
          <IconCalendar />
          <input type="date" value={fecha} max={hoyISO()} onChange={(e) => e.target.value && setFecha(e.target.value)} aria-label="Fecha" />
        </label>
        <button className="btn btn-outline" onClick={recargar} disabled={estado === 'cargando'}>Actualizar</button>
        {gestor && (
          <div className="seg" role="group" aria-label="Alcance">
            <button type="button" aria-pressed={!equipo} onClick={() => setAlcance('mio')}>Mi turno</button>
            <button type="button" aria-pressed={equipo} onClick={() => setAlcance('equipo')}>Equipo</button>
          </div>
        )}
      </div>

      {estado === 'error' && (
        <div className="error state" role="alert">
          <span>{error}</span>
          <button className="btn btn-outline" onClick={recargar}>Reintentar</button>
        </div>
      )}

      {estado === 'cargando' && !data && <div className="card skeleton" style={{ minHeight: 160 }} aria-busy="true" />}

      {k && (
        <div className={estado === 'cargando' ? 'busy' : ''}>
          <div className="stats shift-stats">
            <div className="stat"><small>Inspeccionadas</small><b>{fmt(k.inspeccionadas)}</b></div>
            <div className="stat stat-ok"><small>OK</small><b>{fmt(k.ok)}</b><span>{pct(k.ok, k.inspeccionadas)}</span></div>
            <div className="stat stat-ng"><small>NG (Rechazos)</small><b>{fmt(k.ng)}</b><span>{pct(k.ng, k.inspeccionadas)}</span></div>
          </div>

          {k.inspeccionadas === 0 ? (
            <p className="muted state empty">No hay inspecciones registradas en esta fecha.</p>
          ) : (
            <>
              <h2 className="section-title">Por orden</h2>
              <div className="rtable-wrap">
                <table className="rtable">
                  <thead><tr><th>Orden</th><th>Cliente</th><th>Parte</th><th className="num">Inspec.</th><th className="num">OK</th><th className="num">NG</th></tr></thead>
                  <tbody>
                    {data.porOrden.map((o) => (
                      <tr key={o.so}>
                        <td className="titulo">{o.so}</td>
                        <td data-label="Cliente">{o.cliente}</td>
                        <td data-label="Parte">{o.parte}</td>
                        <td className="num" data-label="Inspeccionadas">{fmt(o.inspeccionadas)}</td>
                        <td className="num" data-label="OK">{fmt(o.ok)}</td>
                        <td className="num" data-label="NG">{fmt(o.ng)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {equipo && data.porInspector && (
                <>
                  <h2 className="section-title">Por inspector</h2>
                  <div className="rtable-wrap">
                    <table className="rtable">
                      <thead><tr><th>Inspector</th><th className="num">Inspec.</th><th className="num">OK</th><th className="num">NG</th><th className="num">Eficiencia</th></tr></thead>
                      <tbody>
                        {data.porInspector.map((i) => (
                          <tr key={i.nombre}>
                            <td className="titulo">{i.nombre}</td>
                            <td className="num" data-label="Inspeccionadas">{fmt(i.inspeccionadas)}</td>
                            <td className="num" data-label="OK">{fmt(i.ok)}</td>
                            <td className="num" data-label="NG">{fmt(i.ng)}</td>
                            <td className="num" data-label="Eficiencia">{pct(i.ok, i.inspeccionadas)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </>
              )}

              {!equipo && data.ultimos && (
                <>
                  <h2 className="section-title">Últimos escaneos</h2>
                  <div className="rtable-wrap">
                    <table className="rtable">
                      <thead><tr><th>Número de serie</th><th>Orden</th><th>Resultado</th><th className="num">Hora</th></tr></thead>
                      <tbody>
                        {data.ultimos.map((u) => (
                          <tr key={u.serie}>
                            <td className="titulo">{u.serie}</td>
                            <td data-label="Orden">{u.so}</td>
                            <td data-label="Resultado"><span className={`badge ${u.resultado === 'OK' ? 'badge-ok' : 'badge-ng'}`}>{u.resultado}</span></td>
                            <td className="num" data-label="Hora">{u.hora}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </>
              )}
            </>
          )}
        </div>
      )}
    </>
  )
}

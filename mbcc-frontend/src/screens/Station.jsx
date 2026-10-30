import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../api.js'
import { WORK_INSTRUCTION } from '../data.js'
import { IconDoc, IconScan, IconCheck, IconX, IconChevron, IconImage } from '../components/Icons.jsx'

export default function Station({ ordenId, orden }) {
  const [serie, setSerie] = useState('')
  const [escaneos, setEscaneos] = useState([])
  const [defectos, setDefectos] = useState([])
  const [defecto, setDefecto] = useState(null)
  const [mostrarDefectos, setMostrarDefectos] = useState(true)
  const [pendiente, setPendiente] = useState(null)
  const [aviso, setAviso] = useState('')
  const [torque, setTorque] = useState(false)
  const inputRef = useRef(null)

  useEffect(() => {
    api.escaneos(ordenId).then(setEscaneos).catch((error) => setAviso(error.message))
    api.defectos().then((items) => setDefectos(items.map((item) => item.description))).catch((error) => setAviso(error.message))
  }, [ordenId])
  useEffect(() => { inputRef.current?.focus() }, [])

  const ok = escaneos.filter((scan) => scan.resultado === 'OK').length
  const ng = escaneos.length - ok
  const total = escaneos.length
  const pct = (count) => (total ? ((count / total) * 100).toFixed(1) : '0.0')
  const instruccion = orden?.instruccion ?? WORK_INSTRUCTION

  const registrar = useCallback(async (resultado, defectoElegido) => {
    if (!serie.trim()) { setAviso('Escanea o escribe un número de serie primero.'); inputRef.current?.focus(); return }
    if (resultado === 'NG' && !defectoElegido) { setPendiente('NG'); setMostrarDefectos(true); setAviso('Selecciona el defecto para registrar el rechazo.'); return }
    try {
      const reg = await api.registrarInspeccion(ordenId, { serie: serie.trim(), resultado, defecto: defectoElegido || null })
      setEscaneos((previous) => [{ serie: serie.trim(), resultado, hora: reg.hora }, ...previous])
      setSerie('')
      setDefecto(null)
      setPendiente(null)
      setAviso('')
      inputRef.current?.focus()
    } catch (error) {
      setAviso(error.message)
    }
  }, [serie, ordenId])

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === 'F1') { event.preventDefault(); registrar('OK') }
      if (event.key === 'F2') { event.preventDefault(); registrar('NG', defecto) }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [registrar, defecto])

  const elegirDefecto = (item) => {
    setDefecto(item)
    if (pendiente === 'NG') registrar('NG', item)
  }

  return (
    <div className="station">
      <section className="card panel wi">
        <header className="panel-head"><h2><IconDoc /> Instrucción de Trabajo (WI)</h2><a className="link" href="#/estacion">Ver completa</a></header>
        <p className="strong">{instruccion.codigo}</p><p>{instruccion.titulo}</p>
        {instruccion.puntos?.length > 0 && <><h3>Puntos clave:</h3><ul>{instruccion.puntos.map((point) => <li key={point}>{point}</li>)}</ul></>}
        {instruccion.archivo && <p className="muted small">Documento: {instruccion.archivo}</p>}
        <h3>Criterios visuales</h3>
        <div className="visuals">
          <figure className="vis ok"><span className="tag tag-ok"><IconCheck width={12} height={12} /> OK · Pieza aceptable</span><div className="ph"><IconImage /></div></figure>
          <figure className="vis ng"><span className="tag tag-ng"><IconX width={12} height={12} /> NG · Rayón en carcasa</span><div className="ph"><IconImage /></div></figure>
        </div>
        <button className="field select-btn" onClick={() => setTorque(!torque)} aria-expanded={torque}><span>Especificaciones de torque / tornillería</span><IconChevron /></button>
        {torque && <p className="muted small">Torque 1.2 – 1.5 Nm · Tornillo M3 × 8 · Secuencia en cruz.</p>}
        <button className="btn btn-primary">Ampliar imagen de rechazo</button>
      </section>

      <section className="card panel capture">
        <header className="panel-head"><h2><IconScan /> Captura y Escaneo</h2></header>
        <label className="scan"><IconScan width={22} height={22} /><input ref={inputRef} value={serie} placeholder="Escanear Código de Barras / Número de Serie…" onChange={(event) => setSerie(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') registrar('OK') }} autoComplete="off" inputMode="text" /></label>
        <p className="muted small">Mantén el escáner activo o presiona Enter</p>
        {aviso && <p className="error" role="status">{aviso}</p>}
        <div className="decision">
          <button className="big big-ok" onClick={() => registrar('OK')}><IconCheck width={30} height={30} /> PASSED / OK <small>[F1]</small></button>
          <button className="big big-ng" onClick={() => registrar('NG', defecto)}><IconX width={30} height={30} /> FAILED / NG <small>[F2]</small></button>
        </div>
        <div className="defects">
          <button className="defects-head" onClick={() => setMostrarDefectos(!mostrarDefectos)} aria-expanded={mostrarDefectos}>Defectos (selecciona uno) <IconChevron style={{ transform: mostrarDefectos ? 'rotate(180deg)' : 'none' }} /></button>
          {mostrarDefectos && <div className="chips">{defectos.map((item) => <button key={item} className={`chip ${defecto === item ? 'on' : ''}`} onClick={() => elegirDefecto(item)}>{item}</button>)}</div>}
          {mostrarDefectos && defectos.length === 0 && <p className="muted small">No hay defectos activos en el catálogo.</p>}
        </div>
      </section>

      <section className="card panel summary">
        <header className="panel-head"><h2><IconClock /> Resumen del Turno</h2></header>
        <div className="stats">
          <div className="stat"><small>Total inspeccionadas</small><b>{total}</b></div>
          <div className="stat stat-ok"><small>OK</small><b>{ok}</b><span>{pct(ok)}%</span></div>
          <div className="stat stat-ng"><small>NG (Rechazos)</small><b>{ng}</b><span>{pct(ng)}%</span></div>
        </div>
        <div className="last">
          <div className="last-head">Últimos 5 Escaneos</div>
          <div className="table-wrap"><table><thead><tr><th>#</th><th>Número de Serie</th><th>Resultado</th><th>Hora</th></tr></thead>
            <tbody>{escaneos.slice(0, 5).map((scan, index) => <tr key={scan.serie + index}><td>{index + 1}</td><td>{scan.serie}</td><td><span className={`badge ${scan.resultado === 'OK' ? 'badge-ok' : 'badge-ng'}`}>{scan.resultado}</span></td><td>{scan.hora}</td></tr>)}</tbody>
          </table></div>
        </div>
      </section>
    </div>
  )
}

function IconClock(props) {
  return <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
}
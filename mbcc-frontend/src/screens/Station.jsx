import { useCallback, useEffect, useRef, useState } from 'react'
import { api, auth } from '../api.js'
import { nivelDe } from '../access.js'
import { WORK_INSTRUCTION } from '../data.js'
import ComparadorIA from '../components/ComparadorIA.jsx'
import ImagenAuth from '../components/ImagenAuth.jsx'
import VisorPDF from '../components/VisorPDF.jsx'
import { encolar, listar, nuevoUuid, quitar, sincronizar } from '../cola.js'
import { IconDoc, IconScan, IconCheck, IconX, IconChevron, IconImage } from '../components/Icons.jsx'

export default function Station({ ordenId, orden }) {
  const verInspector = ['admin', 'supervisor'].includes(nivelDe(auth.user))
  const [serie, setSerie] = useState('')
  const [escaneos, setEscaneos] = useState([])
  const [defectos, setDefectos] = useState([])
  const [defecto, setDefecto] = useState(null)
  const [mostrarDefectos, setMostrarDefectos] = useState(true)
  const [pendiente, setPendiente] = useState(null)
  const [aviso, setAviso] = useState('')
  const [verPdf, setVerPdf] = useState(false)
  const inputRef = useRef(null)
  const [refs, setRefs] = useState([]) // fotos de referencia OK / NG de la instrucción de la orden
  const [sugerencia, setSugerencia] = useState(null) // veredicto de la IA (solo sugiere)
  const [rondaIA, setRondaIA] = useState(0)
  const [foto, setFoto] = useState(null) // foto analizada: queda como evidencia si la pieza se rechaza
  const uid = auth.user?.id ?? 'anon'
  const [cola, setCola] = useState([]) // capturas guardadas en este dispositivo (pendientes o rechazadas)
  const [sinc, setSinc] = useState(false)
  const [info, setInfo] = useState('')
  const [enLinea, setEnLinea] = useState(() => navigator.onLine)
  const [cerrada, setCerrada] = useState(orden?.estado === 'CLOSED' ? { auto: false } : null) // la orden ya no recibe piezas
  const [progreso, setProgreso] = useState({ insp: orden?.inspeccionadas ?? null, meta: orden?.meta ?? null })
  const pendientesRef = useRef(0)

  const refrescarCola = useCallback(() => listar(uid).then(setCola).catch(() => {}), [uid])
  const enviar = useCallback((it) => api.registrarInspeccion(it.ordenId, { ...it.datos, evidencia: it.evidencia, cliente_uuid: it.id, capturado_en: it.capturado }), [])
  const alRegistrar = useCallback((reg) => {
    if (reg?.meta != null) setProgreso({ insp: reg.inspeccionadas, meta: reg.meta })
    if (reg?.orden_cerrada) setCerrada({ auto: true })
  }, [])

  const sincronizarAhora = useCallback(async () => {
    setSinc(true)
    try {
      const r = await sincronizar(uid, enviar)
      await refrescarCola()
      if (r.enviadas > 0) {
        api.escaneos(ordenId).then(setEscaneos).catch(() => {})
        const deEsta = r.respuestas.filter((x) => String(x.item.ordenId) === String(ordenId)).map((x) => x.reg)
        if (deEsta.length) alRegistrar(deEsta.at(-1))
        if (deEsta.some((g) => g?.orden_cerrada)) setCerrada({ auto: true })
        setInfo(`Se enviaron ${r.enviadas} captura${r.enviadas === 1 ? '' : 's'} guardadas sin conexión.`)
      }
    } finally { setSinc(false) }
  }, [uid, enviar, refrescarCola, ordenId, alRegistrar])

  useEffect(() => { pendientesRef.current = cola.filter((c) => c.estado === 'pendiente').length }, [cola])
  useEffect(() => {
    const arranque = setTimeout(() => { refrescarCola(); sincronizarAhora() }, 0)
    const on = () => { setEnLinea(true); sincronizarAhora() }
    const off = () => setEnLinea(false)
    window.addEventListener('online', on); window.addEventListener('offline', off)
    const reintento = setInterval(() => { if (pendientesRef.current > 0) sincronizarAhora() }, 20000)
    return () => { clearTimeout(arranque); clearInterval(reintento); window.removeEventListener('online', on); window.removeEventListener('offline', off) }
  }, [refrescarCola, sincronizarAhora])

  useEffect(() => {
    api.escaneos(ordenId).then(setEscaneos).catch((error) => setAviso(error.message))
    api.defectos().then((items) => setDefectos(items.map((item) => item.description))).catch((error) => setAviso(error.message))
    api.referenciasOrden(ordenId).then(setRefs).catch(() => setRefs([]))
  }, [ordenId])
  useEffect(() => { inputRef.current?.focus() }, [])

  const colaOrden = cola.filter((c) => c.estado === 'pendiente' && String(c.ordenId) === String(ordenId))
  const rechazadas = cola.filter((c) => c.estado === 'rechazada')
  const filas = [
    ...colaOrden.map((c) => ({ serie: c.datos.serie, resultado: c.datos.resultado, hora: new Date(c.capturado).toTimeString().slice(0, 5), pendiente: true })),
    ...escaneos,
  ]
  const ok = filas.filter((scan) => scan.resultado === 'OK').length
  const ng = filas.length - ok
  const total = filas.length
  const pct = (count) => (total ? ((count / total) * 100).toFixed(1) : '0.0')
  const instruccion = orden?.wi ? {
    codigo: `${orden.wi.codigo} · Rev. ${orden.wi.revision}`,
    titulo: orden.wi.titulo,
    archivo: orden.wi.archivo,
    puntos: orden.wi.puntos ?? [],
    especificaciones: orden.wi.especificaciones,
  } : WORK_INSTRUCTION

  const refOk = refs.find((r) => r.tipo === 'OK')

  // La IA rellena el registro: preselecciona el defecto; el inspector confirma con OK / NG
  const aplicarSugerencia = (r, blob) => {
    setSugerencia(r)
    setFoto(blob || null)
    if (r?.evaluable) {
      setDefecto(r.resultado === 'NG' && defectos.includes(r.defecto) ? r.defecto : null)
      if (r.resultado === 'NG') setMostrarDefectos(true)
    }
  }
  const sugerido = sugerencia?.evaluable ? sugerencia.resultado : null

  const registrar = async (resultado, defectoElegido) => {
    if (cerrada) return
    const s = serie.trim()
    if (!s) { setAviso('Escanea o escribe un número de serie primero.'); inputRef.current?.focus(); return }
    if (resultado === 'NG' && !defectoElegido) { setPendiente('NG'); setMostrarDefectos(true); setAviso('Selecciona el defecto para registrar el rechazo.'); return }
    if (filas.some((f) => f.serie === s)) { setAviso('Esa serie ya fue capturada en esta orden.'); return }

    const uuid = nuevoUuid() // el servidor ignora un reenvío con el mismo id
    const capturado = new Date().toISOString()
    const datos = {
      serie: s, resultado, defecto: defectoElegido || null,
      ia: sugerencia?.evaluable ? { resultado: sugerencia.resultado, defecto: sugerencia.defecto, confianza: sugerencia.confianza } : null,
    }
    const evidencia = resultado === 'NG' ? foto : null
    const limpiar = () => {
      setSerie(''); setDefecto(null); setPendiente(null); setAviso(''); setSugerencia(null); setFoto(null)
      setRondaIA((n) => n + 1)
      inputRef.current?.focus()
    }
    try {
      const reg = await api.registrarInspeccion(ordenId, { ...datos, evidencia, cliente_uuid: uuid, capturado_en: capturado })
      setEscaneos((previous) => [{ serie: s, resultado, hora: reg.hora, inspector: reg.inspector }, ...previous])
      setInfo('')
      alRegistrar(reg)
      limpiar()
    } catch (error) {
      if (error.sinRed) {
        // Sin conexión: la pieza se guarda en este dispositivo y se envía sola al volver la red
        await encolar({ id: uuid, usuario: uid, ordenId, datos, evidencia, capturado, creado: Date.now(), estado: 'pendiente' })
        await refrescarCola()
        limpiar()
        setInfo('Sin conexión: la pieza quedó guardada en este dispositivo y se enviará sola al reconectar.')
      } else {
        setAviso(error.message)
        if (error.status === 422 && /no está abierta/i.test(error.message)) setCerrada({ auto: false })
      }
    }
  }

  // Los atajos F1/F2 usan siempre la última versión de registrar (sin volver a suscribir el teclado en cada cambio)
  const registrarRef = useRef(registrar)
  const defectoRef = useRef(defecto)
  useEffect(() => { registrarRef.current = registrar; defectoRef.current = defecto })
  useEffect(() => {
    const onKey = (event) => {
      if (event.key === 'F1') { event.preventDefault(); registrarRef.current('OK') }
      if (event.key === 'F2') { event.preventDefault(); registrarRef.current('NG', defectoRef.current) }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const elegirDefecto = (item) => {
    setDefecto(item)
    if (pendiente === 'NG') registrar('NG', item)
  }

  return (
    <div className="station">
      <section className="card panel wi">
        <header className="panel-head"><h2><IconDoc /> Instrucción de Trabajo (WI)</h2>{instruccion.archivo && <button type="button" className="link-btn link-chico" onClick={() => setVerPdf(true)}>Ver completa</button>}</header>
        <p className="strong">{instruccion.codigo}</p><p>{instruccion.titulo}</p>
        {instruccion.puntos?.length > 0 && <><h3>Puntos clave:</h3><ul>{instruccion.puntos.map((point) => <li key={point}>{point}</li>)}</ul></>}
        <h3>Criterios visuales</h3>
        <div className="visuals visuals-uno">
          <figure className="vis ok"><span className="tag tag-ok"><IconCheck width={12} height={12} /> OK · Pieza aceptable</span>{refOk ? <ImagenAuth key={refOk.id} id={refOk.id} alt="Referencia OK" className="vis-img" /> : <div className="ph"><IconImage /></div>}</figure>
        </div>
        {instruccion.especificaciones && <><h3>Especificaciones</h3><p className="especificaciones">{instruccion.especificaciones}</p></>}
        {verPdf && <VisorPDF ordenId={ordenId} titulo={`${instruccion.codigo} · ${instruccion.titulo}`} onClose={() => setVerPdf(false)} />}
      </section>

      <section className="card panel capture">
        <header className="panel-head"><h2><IconScan /> Captura y Escaneo</h2></header>
        {!enLinea && <p className="chip-offline" role="status">Sin conexión · las piezas se guardan en este dispositivo</p>}
        {cerrada && (
          <div className="cierre" role="status">
            <b>{cerrada.auto ? '¡Meta alcanzada! La orden se cerró automáticamente.' : 'Esta orden ya no está abierta para inspección.'}</b>
            <button type="button" className="btn btn-primary" onClick={() => { location.hash = '#/ordenes' }}>Volver a Órdenes</button>
          </div>
        )}
        <fieldset className="captura" disabled={!!cerrada}>
        <ComparadorIA key={rondaIA} ordenId={ordenId} listo={!!refOk} onResultado={aplicarSugerencia} />
        <label className="scan"><IconScan width={22} height={22} /><input ref={inputRef} value={serie} placeholder="Escanear Código de Barras / Número de Serie…" onChange={(event) => setSerie(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') registrar('OK') }} autoComplete="off" inputMode="text" /></label>
        <p className="muted small">Mantén el escáner activo o presiona Enter</p>
        {aviso && <p className="error" role="status">{aviso}</p>}
        {info && <p className="info" role="status">{info}</p>}
        <div className="decision">
          <button className={`big big-ok ${sugerido === 'OK' ? 'sugerido' : ''}`} onClick={() => registrar('OK')}><IconCheck width={30} height={30} /> PASSED / OK <small>[F1]</small></button>
          <button className={`big big-ng ${sugerido === 'NG' ? 'sugerido' : ''}`} onClick={() => registrar('NG', defecto)}><IconX width={30} height={30} /> FAILED / NG <small>[F2]</small></button>
        </div>
        <div className="defects">
          <button className="defects-head" onClick={() => setMostrarDefectos(!mostrarDefectos)} aria-expanded={mostrarDefectos}>Defectos (selecciona uno) <IconChevron style={{ transform: mostrarDefectos ? 'rotate(180deg)' : 'none' }} /></button>
          {mostrarDefectos && <div className="chips">{defectos.map((item) => <button key={item} className={`chip ${defecto === item ? 'on' : ''}`} onClick={() => elegirDefecto(item)}>{item}</button>)}</div>}
          {mostrarDefectos && defectos.length === 0 && <p className="muted small">No hay defectos activos en el catálogo.</p>}
        </div>
        </fieldset>

        {cola.some((c) => c.estado === 'pendiente') && (
          <div className="cola" role="status">
            <div><b>{cola.filter((c) => c.estado === 'pendiente').length} captura(s) pendiente(s) de enviar</b><small>Guardadas en este dispositivo; se envían solas al haber conexión.</small></div>
            <button type="button" className="btn btn-outline" disabled={sinc} onClick={sincronizarAhora}>{sinc ? 'Enviando…' : 'Sincronizar ahora'}</button>
          </div>
        )}
        {rechazadas.length > 0 && (
          <div className="cola cola-rechazada" role="alert">
            <b>El servidor no aceptó {rechazadas.length === 1 ? 'esta captura' : 'estas capturas'}:</b>
            <ul>{rechazadas.map((c) => (
              <li key={c.id}><span><b>{c.datos.serie}</b> ({c.datos.resultado}) · {c.error}</span> <button type="button" className="link-btn link-chico" onClick={async () => { await quitar(c.id); refrescarCola() }}>Descartar</button></li>
            ))}</ul>
          </div>
        )}
      </section>

      <section className="card panel summary">
        <header className="panel-head"><h2><IconClock /> Resumen del Turno</h2></header>
        {progreso.meta != null && (
          <div className="avance" aria-label="Avance de la orden">
            <div className="avance-txt"><small>Avance de la orden</small><b>{(progreso.insp ?? 0) + colaOrden.length} / {progreso.meta}</b></div>
            <div className="avance-barra"><i style={{ width: `${Math.min(100, (((progreso.insp ?? 0) + colaOrden.length) / Math.max(progreso.meta, 1)) * 100)}%` }} /></div>
          </div>
        )}
        <div className="stats">
          <div className="stat"><small>Total inspeccionadas</small><b>{total}</b></div>
          <div className="stat stat-ok"><small>OK</small><b>{ok}</b><span>{pct(ok)}%</span></div>
          <div className="stat stat-ng"><small>NG (Rechazos)</small><b>{ng}</b><span>{pct(ng)}%</span></div>
        </div>
        <div className="last">
          <div className="last-head">Últimos 5 Escaneos</div>
          <div className="table-wrap"><table><thead><tr><th>#</th><th>Número de Serie</th><th>Resultado</th>{verInspector && <th>Inspector</th>}<th>Hora</th></tr></thead>
            <tbody>{filas.slice(0, 5).map((scan, index) => <tr key={scan.serie + index}><td>{index + 1}</td><td>{scan.serie}</td><td><span className={`badge ${scan.resultado === 'OK' ? 'badge-ok' : 'badge-ng'}`}>{scan.resultado}</span>{scan.pendiente && <span className="pill pill-warn pendiente">Pendiente</span>}</td>{verInspector && <td>{scan.inspector || '—'}</td>}<td>{scan.hora}</td></tr>)}</tbody>
          </table></div>
        </div>
      </section>
    </div>
  )
}

function IconClock(props) {
  return <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
}
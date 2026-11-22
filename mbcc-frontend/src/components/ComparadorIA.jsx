import { useEffect, useRef, useState } from 'react'
import { api } from '../api.js'
import { reducirImagen } from './foto.js'
import { IconImage, IconCheck, IconX } from './Icons.jsx'

const CAMARA = (p) => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...p}>
    <path d="M4 8h3l2-3h6l2 3h3a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V9a1 1 0 011-1z" /><circle cx="12" cy="13" r="4" />
  </svg>
)
const CONFIANZA = { alta: 'Confianza alta', media: 'Confianza media', baja: 'Confianza baja' }

// Toma o elige una foto de la pieza y la compara con las referencias OK/NG de la WI usando IA.
// Solo sugiere: llama a onResultado(resultado, foto) (la foto sirve de evidencia si se rechaza) y el inspector confirma con los botones OK / NG.
export default function ComparadorIA({ ordenId, listo, onResultado }) {
  const camara = useRef(null)
  const galeria = useRef(null)
  const [vista, setVista] = useState(null) // URL temporal de la miniatura
  const [estado, setEstado] = useState('inactivo') // inactivo | analizando | listo | error
  const [res, setRes] = useState(null)
  const [error, setError] = useState('')
  const aviso = useRef(onResultado)
  useEffect(() => { aviso.current = onResultado })
  useEffect(() => () => { if (vista) URL.revokeObjectURL(vista) }, [vista])

  const analizar = async (e) => {
    const archivo = e.target.files?.[0]
    e.target.value = '' // permite volver a elegir la misma foto
    if (!archivo) return
    setEstado('analizando'); setError(''); setRes(null); aviso.current(null)
    setVista(URL.createObjectURL(archivo))
    try {
      const blob = await reducirImagen(archivo)
      const r = await api.compararFoto(ordenId, blob)
      setRes(r); setEstado('listo'); aviso.current(r, blob)
    } catch (err) {
      setError(err.message || 'No se pudo comparar la foto.'); setEstado('error')
    }
  }

  const ocupado = estado === 'analizando'
  const deshabilitado = ocupado || !listo

  return (
    <div className="ia-box">
      <div className="ia-head"><b>Verificar con IA</b><span className="muted small">Compara la pieza con la foto de referencia OK de la WI</span></div>

      <div className="ia-acciones">
        <button type="button" className="btn btn-outline" onClick={() => camara.current?.click()} disabled={deshabilitado}><CAMARA /> Tomar foto</button>
        <button type="button" className="btn btn-outline" onClick={() => galeria.current?.click()} disabled={deshabilitado}><IconImage width={18} height={18} /> Galería</button>
        <input ref={camara} type="file" accept="image/*" capture="environment" hidden onChange={analizar} aria-label="Tomar foto con la cámara" />
        <input ref={galeria} type="file" accept="image/*" hidden onChange={analizar} aria-label="Elegir foto de la galería" />
      </div>
      {!listo && <p className="muted small">Esta instrucción aún no tiene fotos de referencia OK. Un supervisor puede subirlas en Configuración → Instrucciones de trabajo.</p>}

      {vista && (
        <div className="ia-cuerpo">
          <img src={vista} alt="Foto de la pieza a comparar" className="ia-vista" />
          <div className="ia-estado" aria-live="polite">
            {ocupado && <p className="muted">Comparando con las referencias…</p>}
            {estado === 'error' && <p className="error" role="alert">{error}</p>}
            {estado === 'listo' && res && !res.evaluable && (
              <p className="error" role="status">No se pudo evaluar la foto. {res.explicacion} Toma otra con mejor luz y la pieza completa.</p>
            )}
            {estado === 'listo' && res?.evaluable && (
              <div className={`ia-res ${res.resultado === 'OK' ? 'ia-ok' : 'ia-ng'}`}>
                <div className="ia-veredicto">
                  {res.resultado === 'OK' ? <IconCheck width={16} height={16} /> : <IconX width={16} height={16} />}
                  <b>Sugerencia: {res.resultado}</b>
                  <span className="muted small">{CONFIANZA[res.confianza]}</span>
                </div>
                {res.resultado === 'NG' && <p className="small">Defecto: <b>{res.defecto || 'no identificado, elígelo abajo'}</b></p>}
                <p className="small">{res.explicacion}</p>
                <p className="muted small">La IA solo sugiere: confirma con PASSED / FAILED.</p>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

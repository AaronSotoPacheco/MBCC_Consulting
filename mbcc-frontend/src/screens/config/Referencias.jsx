import { useCallback, useState } from 'react'
import { api } from '../../api.js'
import { useAsync } from '../../hooks.js'
import Modal from '../../components/Modal.jsx'
import Aviso from '../../components/Aviso.jsx'
import ImagenAuth from '../../components/ImagenAuth.jsx'
import { Campo, Entrada } from '../../components/Campo.jsx'
import { errorDe } from '../../components/errores.js'
import { reducirImagen } from '../../components/foto.js'
import { IconTrash, IconPlus } from '../../components/Icons.jsx'
import Estado from './Estado.jsx'

const MAX = 4 // por tipo; el servidor lo valida también

// Fotos de referencia OK de una instrucción de trabajo (alimentan la comparación con IA en la Estación).
export default function Referencias({ wi, onClose }) {
  const cargar = useCallback(() => api.referenciasInstruccion(wi.id), [wi.id])
  const { data, estado, error, recargar } = useAsync(cargar)
  const [archivo, setArchivo] = useState(null)
  const [errLocal, setErrLocal] = useState('')
  const [err, setErr] = useState(null)
  const [env, setEnv] = useState(false)
  const [borrando, setBorrando] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [pedir, setPedir] = useState(null) // id que espera confirmación para eliminarse

  const lista = data || []
  const fotos = lista.filter((r) => r.tipo === 'OK')
  const lleno = fotos.length >= MAX

  const subir = async (e) => {
    e.preventDefault()
    setErr(null); setErrLocal(''); setAviso(null)
    if (!archivo) { setErrLocal('Elige una foto.'); return }
    setEnv(true)
    try {
      const blob = await reducirImagen(archivo)
      const fd = new FormData()
      fd.append('tipo', 'OK')
      fd.append('imagen', blob, 'referencia.jpg')
      await api.subirReferencia(wi.id, fd)
      setArchivo(null)
      e.target.reset()
      recargar()
      setAviso({ tipo: 'ok', texto: 'Foto de referencia agregada.' })
    } catch (e2) {
      if (e2.errors) setErr(e2); else setErrLocal(e2.message)
    } finally {
      setEnv(false)
    }
  }

  const borrar = async (r) => {
    setBorrando(r.id); setAviso(null)
    try { await api.eliminarReferencia(r.id); setPedir(null); recargar() } catch (e) { setAviso({ tipo: 'error', texto: e.message }) } finally { setBorrando(null) }
  }

  const galeria = (
    <section>
      <h3 className="ref-titulo">Pieza aceptable (OK) <span className="muted small">({fotos.length}/{MAX})</span></h3>
      {fotos.length === 0 ? <p className="muted small">Sin fotos.</p> : (
        <div className="ref-grid">
          {fotos.map((r) => (
            <figure key={r.id} className="ref-item">
              <ImagenAuth key={r.id} id={r.id} alt="Foto de referencia OK" />
              {pedir === r.id ? (
                <div className="ref-conf">
                  <button type="button" className="btn btn-sm btn-danger" onClick={() => borrar(r)} disabled={borrando === r.id}>Eliminar</button>
                  <button type="button" className="btn btn-sm btn-ghost" onClick={() => setPedir(null)}>No</button>
                </div>
              ) : (
                <button type="button" className="btn btn-sm btn-ghost" onClick={() => setPedir(r.id)} aria-label="Eliminar foto de referencia"><IconTrash width={14} height={14} /> Quitar</button>
              )}
            </figure>
          ))}
        </div>
      )}
    </section>
  )

  return (
    <Modal
      titulo={`Fotos de referencia · ${wi.code} Rev. ${wi.revision}`}
      onClose={onClose}
      pie={<button type="button" className="btn btn-outline" onClick={onClose}>Cerrar</button>}
    >
      <p className="muted small">La IA compara la foto de la pieza contra estas imágenes. Sube al menos una foto de la pieza aceptable.</p>
      <Aviso aviso={aviso} onClose={() => setAviso(null)} />
      <Estado estado={estado} error={error} data={data} recargar={recargar} vacio="" />
      {data && galeria}

      <form className="ref-form" onSubmit={subir} noValidate>
        <h3 className="ref-titulo">Agregar foto</h3>
        <Campo etiqueta="Foto (JPG, PNG o WebP)" error={errLocal || errorDe(err, 'imagen')}>
          <Entrada type="file" accept="image/*" onChange={(e) => { setArchivo(e.target.files[0] || null); setErrLocal('') }} error={errLocal || errorDe(err, 'imagen')} />
        </Campo>
        {err && !['tipo', 'imagen'].some((c) => err.errors?.[c]) && <p className="error" role="alert">{err.message}</p>}
        <button type="submit" className="btn btn-primary" disabled={env || lleno}><IconPlus width={16} height={16} /> {env ? 'Subiendo…' : lleno ? `Máximo ${MAX} fotos` : 'Agregar foto'}</button>
      </form>
    </Modal>
  )
}

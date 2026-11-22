import { useEffect, useState } from 'react'
import { api } from '../api.js'
import Modal from './Modal.jsx'

// Muestra el PDF de la instrucción de trabajo de la orden dentro de la Estación.
export default function VisorPDF({ ordenId, titulo, onClose }) {
  const [url, setUrl] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let vivo = true
    let blob = null
    api.pdfDeOrden(ordenId)
      .then((u) => { if (!vivo) { URL.revokeObjectURL(u); return } blob = u; setUrl(u) })
      .catch((e) => { if (vivo) setError(e.message) })
    return () => { vivo = false; if (blob) URL.revokeObjectURL(blob) }
  }, [ordenId])

  return (
    <Modal
      titulo={titulo}
      onClose={onClose}
      pie={(
        <>
          {url && <a className="btn btn-outline" href={url} target="_blank" rel="noopener noreferrer">Abrir en otra pestaña</a>}
          <button type="button" className="btn btn-primary" onClick={onClose}>Cerrar</button>
        </>
      )}
    >
      {error && <p className="error" role="alert">{error}</p>}
      {!url && !error && <p className="muted" role="status">Cargando documento…</p>}
      {url && <iframe className="pdf-frame" src={url} title={titulo} />}
    </Modal>
  )
}

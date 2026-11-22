import { useEffect, useState } from 'react'
import { api } from '../api.js'
import { IconImage } from './Icons.jsx'

// Foto de referencia protegida: se pide con el token (un <img src> no puede enviarlo) y se muestra desde un blob.
// Usar con key={id} para reiniciar al cambiar de imagen.
export default function ImagenAuth({ id, alt, className = '', cargar = api.imagenReferencia, ...props }) {
  const [src, setSrc] = useState(null)
  const [fallo, setFallo] = useState(false)

  useEffect(() => {
    let vivo = true
    let url = null
    cargar(id)
      .then((u) => {
        if (!vivo) { if (u) URL.revokeObjectURL(u); return }
        url = u
        setSrc(u)
      })
      .catch(() => { if (vivo) setFallo(true) })
    return () => { vivo = false; if (url) URL.revokeObjectURL(url) }
  }, [id, cargar])

  if (src) return <img src={src} alt={alt} className={`ref-img ${className}`} {...props} />
  return <div className={`ref-img ref-img-vacia ${className}`} role={fallo ? 'img' : undefined} aria-label={fallo ? `${alt} (no disponible)` : undefined}><IconImage /></div>
}

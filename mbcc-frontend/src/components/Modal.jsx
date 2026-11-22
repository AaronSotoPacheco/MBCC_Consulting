import { useEffect, useId, useRef } from 'react'
import { createPortal } from 'react-dom'
import { IconX } from './Icons.jsx'

const FOCO = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'

// Ventana modal accesible: Escape cierra, el foco queda dentro y vuelve al botón que la abrió.
// En celular se muestra como hoja inferior. Con `onSubmit` el cuerpo y el pie van dentro de un <form>.
export default function Modal({ titulo, onClose, onSubmit, pie, children }) {
  const id = useId()
  const ref = useRef(null)
  const cerrar = useRef(onClose)
  useEffect(() => { cerrar.current = onClose })

  useEffect(() => {
    const previo = document.activeElement
    const primero = ref.current?.querySelector('.modal-body input:not([disabled]),.modal-body select:not([disabled])') || ref.current
    primero?.focus()

    const tecla = (e) => {
      if (e.key === 'Escape') { e.stopPropagation(); cerrar.current() }
      if (e.key === 'Tab' && ref.current) {
        const f = [...ref.current.querySelectorAll(FOCO)].filter((el) => el.offsetParent !== null)
        if (!f.length) return
        const a = f[0]; const z = f[f.length - 1]
        if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus() }
        else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus() }
      }
    }
    document.addEventListener('keydown', tecla)
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', tecla)
      document.body.style.overflow = ''
      previo?.focus?.()
    }
  }, [])

  const cuerpo = (
    <>
      <div className="modal-body">{children}</div>
      {pie && <footer className="modal-foot">{pie}</footer>}
    </>
  )

  return createPortal(
    <div className="modal-overlay" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose() }}>
      <div className="modal" role="dialog" aria-modal="true" aria-labelledby={id} ref={ref} tabIndex={-1}>
        <header className="modal-head">
          <h2 id={id}>{titulo}</h2>
          <button type="button" className="icon-btn" onClick={onClose} aria-label="Cerrar"><IconX width={18} height={18} /></button>
        </header>
        {onSubmit ? <form className="modal-form" onSubmit={onSubmit} noValidate>{cuerpo}</form> : <div className="modal-form">{cuerpo}</div>}
      </div>
    </div>,
    document.body,
  )
}

import { IconChevron } from './Icons.jsx'

// Campo de formulario con etiqueta, ayuda y mensaje de error (el error puede venir del servidor).
export function Campo({ etiqueta, error, ayuda, children }) {
  return (
    <label className="campo">
      <span className="campo-label">{etiqueta}</span>
      {children}
      {ayuda && !error && <small className="campo-ayuda">{ayuda}</small>}
      {error && <small className="campo-error" role="alert">{error}</small>}
    </label>
  )
}

export const Entrada = ({ error, ...p }) => (
  <div className={`field ${error ? 'invalid' : ''}`}>
    <input {...p} aria-invalid={error ? true : undefined} />
  </div>
)

export const Seleccion = ({ error, children, ...p }) => (
  <div className={`field ${error ? 'invalid' : ''}`}>
    <select {...p} aria-invalid={error ? true : undefined}>{children}</select>
    <IconChevron />
  </div>
)

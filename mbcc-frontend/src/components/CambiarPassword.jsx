import { useState } from 'react'
import { api } from '../api.js'
import { Campo, Entrada } from './Campo.jsx'
import Modal from './Modal.jsx'
import { errorDe } from './errores.js'

// Cambio de la contraseña propia. El servidor cierra las demás sesiones abiertas de esta cuenta.
export default function CambiarPassword({ onCerrar }) {
  const [v, setV] = useState({ actual: '', nueva: '', confirmar: '' })
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState(null)
  const [listo, setListo] = useState(false)
  const set = (k) => (e) => setV((p) => ({ ...p, [k]: e.target.value }))

  const guardar = async (e) => {
    e.preventDefault()
    if (v.nueva.length < 8) { setError({ message: 'La contraseña nueva debe tener al menos 8 caracteres.', errors: { nueva: ['Mínimo 8 caracteres.'] } }); return }
    if (v.nueva !== v.confirmar) { setError({ message: 'La confirmación no coincide.', errors: { nueva_confirmation: ['No coincide con la contraseña nueva.'] } }); return }
    setGuardando(true); setError(null)
    try {
      await api.cambiarPassword({ actual: v.actual, nueva: v.nueva, nueva_confirmation: v.confirmar })
      setListo(true)
    } catch (err) { setError(err) } finally { setGuardando(false) }
  }

  const campoError = (k) => errorDe(error, k)
  const general = error && !error.errors ? error.message : null

  return (
    <Modal
      titulo="Cambiar contraseña"
      onClose={onCerrar}
      onSubmit={listo ? (e) => { e.preventDefault(); onCerrar() } : guardar}
      pie={listo
        ? <button className="btn btn-primary">Listo</button>
        : <>
          <button type="button" className="btn btn-outline" onClick={onCerrar}>Cancelar</button>
          <button className="btn btn-primary" disabled={guardando}>{guardando ? 'Guardando…' : 'Cambiar contraseña'}</button>
        </>}
    >
      {listo ? (
        <p role="status">Tu contraseña se actualizó. Por seguridad se cerraron tus sesiones en otros dispositivos.</p>
      ) : (
        <>
          <Campo etiqueta="Contraseña actual" error={campoError('actual')}>
            <Entrada type="password" value={v.actual} onChange={set('actual')} autoComplete="current-password" error={campoError('actual')} />
          </Campo>
          <Campo etiqueta="Contraseña nueva" ayuda="Mínimo 8 caracteres y distinta a la actual." error={campoError('nueva')}>
            <Entrada type="password" value={v.nueva} onChange={set('nueva')} autoComplete="new-password" error={campoError('nueva')} />
          </Campo>
          <Campo etiqueta="Confirma la contraseña nueva" error={campoError('nueva_confirmation')}>
            <Entrada type="password" value={v.confirmar} onChange={set('confirmar')} autoComplete="new-password" error={campoError('nueva_confirmation')} />
          </Campo>
          {general && <p className="error" role="alert">{general}</p>}
        </>
      )}
    </Modal>
  )
}

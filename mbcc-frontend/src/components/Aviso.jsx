import { IconX } from './Icons.jsx'

// Mensaje de resultado de una acción: { tipo: 'ok' | 'error', texto }
export default function Aviso({ aviso, onClose }) {
  if (!aviso) return null
  return (
    <div className={`notice notice-${aviso.tipo}`} role={aviso.tipo === 'error' ? 'alert' : 'status'}>
      <span>{aviso.texto}</span>
      <button type="button" className="icon-btn" onClick={onClose} aria-label="Cerrar aviso"><IconX width={14} height={14} /></button>
    </div>
  )
}

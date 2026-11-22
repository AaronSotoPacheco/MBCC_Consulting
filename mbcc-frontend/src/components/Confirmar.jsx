import Modal from './Modal.jsx'

export default function Confirmar({ titulo, mensaje, textoConfirmar = 'Confirmar', peligro = false, ocupado = false, error, onConfirmar, onClose }) {
  return (
    <Modal
      titulo={titulo}
      onClose={onClose}
      onSubmit={(e) => { e.preventDefault(); onConfirmar() }}
      pie={(
        <>
          <button type="button" className="btn btn-outline" onClick={onClose} disabled={ocupado}>Cancelar</button>
          <button type="submit" className={`btn ${peligro ? 'btn-danger' : 'btn-primary'}`} disabled={ocupado}>
            {ocupado ? 'Procesando…' : textoConfirmar}
          </button>
        </>
      )}
    >
      <p>{mensaje}</p>
      {error && <p className="error" role="alert">{error}</p>}
    </Modal>
  )
}

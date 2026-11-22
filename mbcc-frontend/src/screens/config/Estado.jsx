// Estados comunes de carga/error de una lista
export default function Estado({ estado, error, data, recargar, vacio }) {
  if (estado === 'error') return (
    <div className="error state" role="alert"><span>{error}</span><button className="btn btn-outline" onClick={recargar}>Reintentar</button></div>
  )
  if (!data) return <div className="card skeleton" style={{ minHeight: 140 }} aria-busy="true" />
  if (vacio) return <p className="muted state">{vacio}</p>
  return null
}

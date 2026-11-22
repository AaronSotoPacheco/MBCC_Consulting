import { useEffect, useState } from 'react'
import { api } from '../api.js'
import Modal from './Modal.jsx'
import { Campo, Entrada, Seleccion } from './Campo.jsx'
import { errorDe } from './errores.js'

const HORARIO = '8:00 AM - 4:00 PM'

// Crear una orden nueva o editar meta y horario de una existente (la parte y la instrucción no cambian).
export default function OrdenForm({ orden, onClose, onGuardado }) {
  const editar = !!orden
  const [op, setOp] = useState(null) // clientes → partes → instrucciones vigentes
  const [errCarga, setErrCarga] = useState('')
  const [f, setF] = useState({ cliente: '', parte: '', wi: '', numero: '', meta: orden ? String(orden.meta) : '', turno: orden?.turno || HORARIO })
  const [err, setErr] = useState(null)
  const [enviando, setEnviando] = useState(false)

  useEffect(() => { api.opcionesOrden().then(setOp).catch((e) => setErrCarga(e.message)) }, [])

  const set = (k) => (e) => setF((x) => ({ ...x, [k]: e.target.value }))
  const cliente = op?.clientes.find((c) => String(c.id) === f.cliente)
  const partes = cliente?.partes || []
  const parte = partes.find((p) => String(p.id) === f.parte)
  const wis = parte?.instrucciones || []

  const elegirCliente = (e) => setF((x) => ({ ...x, cliente: e.target.value, parte: '', wi: '' }))
  const elegirParte = (e) => {
    const p = partes.find((q) => String(q.id) === e.target.value)
    setF((x) => ({ ...x, parte: e.target.value, wi: p?.instrucciones.length === 1 ? String(p.instrucciones[0].id) : '' }))
  }

  const enviar = async (e) => {
    e.preventDefault()
    setEnviando(true); setErr(null)
    try {
      const n = editar
        ? await api.editarOrden(orden.id, { meta: Number(f.meta), turno: f.turno || null })
        : await api.crearOrden({ numero_orden: f.numero.trim(), parte_id: Number(f.parte), instruccion_id: Number(f.wi), meta: Number(f.meta), turno: f.turno || null })
      onGuardado(n, !editar)
    } catch (e2) {
      setErr(e2)
    } finally {
      setEnviando(false)
    }
  }

  const hayCampoConError = err?.errors && ['numero_orden', 'parte_id', 'instruccion_id', 'meta', 'turno'].some((c) => err.errors[c])
  const generico = err && (!err.errors || !hayCampoConError) ? (errorDe(err, 'estado') || err.message) : ''

  return (
    <Modal
      titulo={editar ? `Editar orden ${orden.so}` : 'Nueva orden'}
      onClose={onClose}
      onSubmit={enviar}
      pie={(
        <>
          <button type="button" className="btn btn-outline" onClick={onClose} disabled={enviando}>Cancelar</button>
          <button type="submit" className="btn btn-primary" disabled={enviando || (!editar && !op)}>
            {enviando ? 'Guardando…' : editar ? 'Guardar cambios' : 'Crear orden'}
          </button>
        </>
      )}
    >
      {errCarga && <p className="error" role="alert">{errCarga}</p>}

      {editar ? (
        <p className="muted small">{orden.cliente} · {orden.parte} · {orden.wi?.codigo} Rev. {orden.wi?.revision}</p>
      ) : (
        <>
          <Campo etiqueta="Cliente">
            <Seleccion value={f.cliente} onChange={elegirCliente} disabled={!op}>
              <option value="">{op ? 'Selecciona un cliente' : 'Cargando…'}</option>
              {op?.clientes.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
            </Seleccion>
          </Campo>

          <Campo etiqueta="Número de parte" error={errorDe(err, 'parte_id')} ayuda={parte?.descripcion}>
            <Seleccion value={f.parte} onChange={elegirParte} disabled={!cliente} error={errorDe(err, 'parte_id')}>
              <option value="">{cliente ? (partes.length ? 'Selecciona un número de parte' : 'Este cliente no tiene partes') : 'Primero elige un cliente'}</option>
              {partes.map((p) => <option key={p.id} value={p.id}>{p.parte}</option>)}
            </Seleccion>
          </Campo>

          <Campo etiqueta="Instrucción de trabajo vigente" error={errorDe(err, 'instruccion_id')}>
            <Seleccion value={f.wi} onChange={set('wi')} disabled={!parte || !wis.length} error={errorDe(err, 'instruccion_id')}>
              <option value="">{parte ? (wis.length ? 'Selecciona una instrucción' : 'Sin instrucción vigente') : 'Primero elige una parte'}</option>
              {wis.map((w) => <option key={w.id} value={w.id}>{w.codigo} · Rev. {w.revision}</option>)}
            </Seleccion>
          </Campo>
          {parte && !wis.length && (
            <p className="error" role="status">
              Esta parte no tiene una instrucción de trabajo vigente. Un supervisor debe aprobar una en Configuración → Instrucciones de trabajo.
            </p>
          )}

          <Campo etiqueta="Número de orden de servicio" error={errorDe(err, 'numero_orden')}>
            <Entrada value={f.numero} onChange={set('numero')} placeholder="SO-45880" maxLength={50} autoComplete="off" error={errorDe(err, 'numero_orden')} />
          </Campo>
        </>
      )}

      <div className="modal-grid">
        <Campo etiqueta="Meta de piezas" error={errorDe(err, 'meta')}>
          <Entrada type="number" inputMode="numeric" min={1} max={1000000} value={f.meta} onChange={set('meta')} placeholder="1200" error={errorDe(err, 'meta')} />
        </Campo>
        <Campo etiqueta="Horario" error={errorDe(err, 'turno')}>
          <Entrada list="turnos-orden" value={f.turno} onChange={set('turno')} maxLength={50} error={errorDe(err, 'turno')} />
          <datalist id="turnos-orden">{(op?.turnos || []).map((t) => <option key={t} value={t} />)}</datalist>
        </Campo>
      </div>

      {generico && <p className="error" role="alert">{generico}</p>}
    </Modal>
  )
}

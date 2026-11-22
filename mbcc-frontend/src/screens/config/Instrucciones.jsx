import { useCallback, useState } from 'react'
import { api } from '../../api.js'
import { useAsync, fechaHora } from '../../hooks.js'
import Modal from '../../components/Modal.jsx'
import Aviso from '../../components/Aviso.jsx'
import Confirmar from '../../components/Confirmar.jsx'
import { Campo, Entrada, Seleccion } from '../../components/Campo.jsx'
import { errorDe } from '../../components/errores.js'
import { IconPlus, IconDoc, IconCheck, IconTrash, IconImage } from '../../components/Icons.jsx'
import Estado from './Estado.jsx'
import Referencias from './Referencias.jsx'

const EST = { DRAFT: ['Borrador', 'pill-warn'], ACTIVE: ['Vigente', 'pill-ok'], OBSOLETE: ['Obsoleta', 'pill-off'] }
const MAX = 20 * 1024 * 1024
const CAMPOS = ['part_number_id', 'code', 'revision', 'title', 'archivo']

function FormContenido({ w, onClose, onGuardado }) {
  const [puntos, setPuntos] = useState(w.key_points || '')
  const [espec, setEspec] = useState(w.specifications || '')
  const [err, setErr] = useState(null)
  const [env, setEnv] = useState(false)

  const enviar = async (e) => {
    e.preventDefault()
    setErr(null); setEnv(true)
    try { onGuardado(await api.guardarContenidoInstruccion(w.id, { key_points: puntos, specifications: espec })) } catch (e2) { setErr(e2); setEnv(false) }
  }

  return (
    <Modal
      titulo={`Contenido en Estación · ${w.code} Rev. ${w.revision}`}
      onClose={onClose}
      onSubmit={enviar}
      pie={(
        <>
          <button type="button" className="btn btn-outline" onClick={onClose} disabled={env}>Cancelar</button>
          <button type="submit" className="btn btn-primary" disabled={env}>{env ? 'Guardando…' : 'Guardar'}</button>
        </>
      )}
    >
      <p className="muted small">Lo que verá el inspector junto a la instrucción mientras inspecciona. El documento controlado sigue siendo el PDF.</p>
      <Campo etiqueta="Puntos clave (uno por línea)" error={errorDe(err, 'key_points')}>
        <textarea className="area" rows={5} maxLength={2000} value={puntos} onChange={(e) => setPuntos(e.target.value)} placeholder={'Verificar alineación de tornillos\nRevisar ausencia de rayones'} autoFocus />
      </Campo>
      <Campo etiqueta="Especificaciones (torque, tornillería…)" error={errorDe(err, 'specifications')}>
        <textarea className="area" rows={3} maxLength={2000} value={espec} onChange={(e) => setEspec(e.target.value)} placeholder="Torque 1.2 – 1.5 Nm · Tornillo M3 × 8" />
      </Campo>
      {err && !err.errors && <p className="error" role="alert">{err.message}</p>}
    </Modal>
  )
}

function Form({ partes, onClose, onGuardado }) {
  const [f, setF] = useState({ part_number_id: '', code: '', revision: '', title: '' })
  const [archivo, setArchivo] = useState(null)
  const [errLocal, setErrLocal] = useState('')
  const [err, setErr] = useState(null)
  const [env, setEnv] = useState(false)
  const set = (k) => (e) => setF((x) => ({ ...x, [k]: e.target.value }))

  const elegir = (e) => {
    const a = e.target.files[0]
    setErrLocal('')
    if (a && a.type !== 'application/pdf' && !a.name.toLowerCase().endsWith('.pdf')) { setErrLocal('El archivo debe ser un PDF.'); setArchivo(null); return }
    if (a && a.size > MAX) { setErrLocal('El PDF no puede pasar de 20 MB.'); setArchivo(null); return }
    setArchivo(a || null)
  }

  const enviar = async (e) => {
    e.preventDefault()
    setErr(null)
    if (!archivo) { setErrLocal('Selecciona el archivo PDF.'); return }
    setEnv(true)
    const fd = new FormData()
    Object.entries(f).forEach(([k, v]) => fd.append(k, v))
    fd.append('archivo', archivo)
    try { onGuardado(await api.crearInstruccion(fd)) } catch (e2) { setErr(e2) } finally { setEnv(false) }
  }

  const general = err && !CAMPOS.some((c) => err.errors?.[c]) ? err.message : ''
  return (
    <Modal
      titulo="Nueva revisión de instrucción"
      onClose={onClose}
      onSubmit={enviar}
      pie={(
        <>
          <button type="button" className="btn btn-outline" onClick={onClose} disabled={env}>Cancelar</button>
          <button type="submit" className="btn btn-primary" disabled={env}>{env ? 'Subiendo…' : 'Guardar como borrador'}</button>
        </>
      )}
    >
      <Campo etiqueta="Número de parte" error={errorDe(err, 'part_number_id')}>
        <Seleccion value={f.part_number_id} onChange={set('part_number_id')} error={errorDe(err, 'part_number_id')}>
          <option value="">Selecciona una parte</option>
          {partes.map((p) => <option key={p.id} value={p.id}>{p.part_number} · {p.client}</option>)}
        </Seleccion>
      </Campo>
      <div className="modal-grid">
        <Campo etiqueta="Código" error={errorDe(err, 'code')}><Entrada value={f.code} onChange={set('code')} placeholder="WI-7301" maxLength={50} error={errorDe(err, 'code')} /></Campo>
        <Campo etiqueta="Revisión" error={errorDe(err, 'revision')}><Entrada value={f.revision} onChange={set('revision')} placeholder="C" maxLength={10} error={errorDe(err, 'revision')} /></Campo>
      </div>
      <Campo etiqueta="Título" error={errorDe(err, 'title')}><Entrada value={f.title} onChange={set('title')} maxLength={200} error={errorDe(err, 'title')} /></Campo>
      <Campo etiqueta="Archivo PDF (máx. 20 MB)" error={errLocal || errorDe(err, 'archivo')}>
        <Entrada type="file" accept="application/pdf,.pdf" onChange={elegir} error={errLocal || errorDe(err, 'archivo')} />
      </Campo>
      <p className="muted small">Se guarda como borrador. Al aprobarla pasa a vigente y la revisión vigente anterior de esa parte queda obsoleta.</p>
      {general && <p className="error" role="alert">{general}</p>}
    </Modal>
  )
}

export default function Instrucciones() {
  const cargar = useCallback(async () => {
    const [instrucciones, partes] = await Promise.all([api.instrucciones(), api.partes()])
    return { instrucciones, partes }
  }, [])
  const { data, estado, error, recargar, setData } = useAsync(cargar)
  const [modal, setModal] = useState(false)
  const [contenido, setContenido] = useState(null)
  const [aviso, setAviso] = useState(null)
  const [ocupado, setOcupado] = useState(null)
  const [fotos, setFotos] = useState(null) // instrucción cuyas fotos de referencia se administran
  const [confirm, setConfirm] = useState(null) // { w, tipo: 'obsoleta' | 'borrar', error }
  const [q, setQ] = useState('')
  const [filtro, setFiltro] = useState('todas')

  const aplicar = async (w, tipo) => {
    setOcupado(w.id)
    setAviso(null)
    try {
      if (tipo === 'borrar') {
        await api.eliminarInstruccion(w.id)
        setData((d) => ({ ...d, instrucciones: d.instrucciones.filter((x) => x.id !== w.id) }))
        setAviso({ tipo: 'ok', texto: `Borrador ${w.code} Rev. ${w.revision} eliminado.` })
      } else {
        await (tipo === 'aprobar' ? api.aprobarInstruccion(w.id) : api.obsoletaInstruccion(w.id))
        recargar() // aprobar también deja obsoleta la vigente anterior
        setAviso({ tipo: 'ok', texto: tipo === 'aprobar' ? `${w.code} Rev. ${w.revision} aprobada y vigente.` : `${w.code} Rev. ${w.revision} marcada como obsoleta.` })
      }
      setConfirm(null)
    } catch (e) {
      if (confirm) setConfirm((c) => ({ ...c, error: e.message }))
      else setAviso({ tipo: 'error', texto: e.message })
    } finally {
      setOcupado(null)
    }
  }
  const ver = async (w) => {
    try { await api.verArchivoInstruccion(w.id) } catch (e) { setAviso({ tipo: 'error', texto: e.message }) }
  }

  const t = q.trim().toLowerCase()
  const lista = (data?.instrucciones || []).filter((w) => (filtro === 'todas' || w.status === filtro) && (!t || `${w.code} ${w.title} ${w.part_number} ${w.client}`.toLowerCase().includes(t)))

  return (
    <>
      <div className="toolbar">
        <label className="field grow"><input type="search" placeholder="Buscar código, título o parte…" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Buscar instrucciones" /></label>
        <Seleccion value={filtro} onChange={(e) => setFiltro(e.target.value)} aria-label="Filtrar por estado">
          <option value="todas">Todos los estados</option>
          <option value="ACTIVE">Vigentes</option>
          <option value="DRAFT">Borradores</option>
          <option value="OBSOLETE">Obsoletas</option>
        </Seleccion>
        <button className="btn btn-primary" onClick={() => setModal(true)} disabled={!data}><IconPlus width={16} height={16} /> Nueva revisión</button>
      </div>
      <Aviso aviso={aviso} onClose={() => setAviso(null)} />
      <Estado estado={estado} error={error} data={data} recargar={recargar} vacio={data && lista.length === 0 ? 'No hay instrucciones que coincidan.' : ''} />
      {data && lista.length > 0 && (
        <div className="rtable-wrap">
          <table className="rtable">
            <thead><tr><th>Documento</th><th>Parte</th><th>Revisión</th><th>Estado</th><th>Aprobó</th><th /></tr></thead>
            <tbody>
              {lista.map((w) => {
                const [txt, cls] = EST[w.status] || [w.status, 'pill-off']
                const bz = ocupado === w.id
                return (
                  <tr key={w.id}>
                    <td className="titulo"><span className="config-doc"><IconDoc width={17} height={17} /><span><b>{w.code}</b><small>{w.title}</small></span></span></td>
                    <td data-label="Parte">{w.part_number}<br /><small className="muted">{w.client}</small></td>
                    <td data-label="Revisión">{w.revision}</td>
                    <td data-label="Estado"><span className={`pill ${cls}`}>{txt}</span></td>
                    <td data-label="Aprobó">{w.approved_by_name ? <>{w.approved_by_name}<br /><small className="muted">{fechaHora(w.approved_at)}</small></> : '—'}</td>
                    <td className="acciones">
                      <button className="btn btn-ghost btn-sm" onClick={() => ver(w)} disabled={bz}><IconDoc width={14} height={14} /> Ver PDF</button>
                      {w.status !== 'OBSOLETE' && <button className="btn btn-ghost btn-sm" onClick={() => setContenido(w)} disabled={bz}>Contenido</button>}
                      {w.status !== 'OBSOLETE' && <button className="btn btn-ghost btn-sm" onClick={() => setFotos(w)} disabled={bz}><IconImage width={14} height={14} /> Fotos IA</button>}
                      {w.status === 'DRAFT' && (
                        <>
                          <button className="btn btn-ghost btn-sm" onClick={() => aplicar(w, 'aprobar')} disabled={bz}><IconCheck width={14} height={14} /> Aprobar</button>
                          <button className="btn btn-ghost btn-sm" onClick={() => setConfirm({ w, tipo: 'borrar' })} disabled={bz}><IconTrash width={14} height={14} /> Eliminar</button>
                        </>
                      )}
                      {w.status === 'ACTIVE' && <button className="btn btn-ghost btn-sm" onClick={() => setConfirm({ w, tipo: 'obsoleta' })} disabled={bz}>Marcar obsoleta</button>}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
      {modal && (
        <Form
          partes={data.partes}
          onClose={() => setModal(false)}
          onGuardado={(w) => {
            setModal(false)
            recargar()
            setAviso({ tipo: 'ok', texto: `${w.code} Rev. ${w.revision} guardada como borrador. Apruébala para ponerla en uso.` })
          }}
        />
      )}
      {fotos && <Referencias wi={fotos} onClose={() => setFotos(null)} />}
      {contenido && (
        <FormContenido
          w={contenido}
          onClose={() => setContenido(null)}
          onGuardado={(w) => {
            setContenido(null)
            recargar()
            setAviso({ tipo: 'ok', texto: `Contenido de ${w.code} Rev. ${w.revision} actualizado.` })
          }}
        />
      )}
      {confirm && (
        <Confirmar
          titulo={confirm.tipo === 'borrar' ? 'Eliminar borrador' : 'Marcar como obsoleta'}
          mensaje={confirm.tipo === 'borrar'
            ? `Se eliminará el borrador ${confirm.w.code} Rev. ${confirm.w.revision} y su PDF. No se puede deshacer.`
            : `${confirm.w.code} Rev. ${confirm.w.revision} dejará de estar vigente y no se podrá elegir al crear órdenes nuevas hasta aprobar otra revisión.`}
          textoConfirmar={confirm.tipo === 'borrar' ? 'Eliminar' : 'Marcar obsoleta'}
          peligro
          ocupado={ocupado === confirm.w.id}
          error={confirm.error}
          onConfirmar={() => aplicar(confirm.w, confirm.tipo)}
          onClose={() => setConfirm(null)}
        />
      )}
    </>
  )
}

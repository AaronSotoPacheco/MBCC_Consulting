import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../../api.js'
import { useAsync, fechaHora } from '../../hooks.js'
import { qrImagen } from '../../components/qr.js'
import Modal from '../../components/Modal.jsx'
import Aviso from '../../components/Aviso.jsx'
import Confirmar from '../../components/Confirmar.jsx'
import { IconScan } from '../../components/Icons.jsx'
import Estado from './Estado.jsx'

const ROL = { admin: 'Administrador', supervisor: 'Supervisor', inspector: 'Inspector' }
const lista = (r) => (Array.isArray(r) ? r : r?.data || [])

// Gafetes imprimibles. El código en claro solo existe aquí (en el servidor queda su hash): al cerrar no se puede volver a ver.
function Gafetes({ items, onClose }) {
  const [imgs, setImgs] = useState({})
  const [fallo, setFallo] = useState('')

  useEffect(() => {
    let vivo = true
    Promise.all(items.map((i) => qrImagen(i.qr, 360).then((url) => [i.id, url])))
      .then((pares) => { if (vivo) setImgs(Object.fromEntries(pares)) })
      .catch(() => { if (vivo) setFallo('No se pudieron dibujar los códigos QR.') })
    return () => { vivo = false }
  }, [items])

  return (
    <Modal
      titulo={`Gafetes con QR (${items.length})`}
      onClose={onClose}
      pie={(
        <>
          <button type="button" className="btn btn-outline" onClick={onClose}>Cerrar</button>
          <button type="button" className="btn btn-primary" onClick={() => window.print()} disabled={Object.keys(imgs).length !== items.length}>Imprimir</button>
        </>
      )}
    >
      <p className="notice notice-error no-print" role="note">
        Imprímelos ahora: por seguridad el código no se puede volver a mostrar. Si se pierde un gafete, genera uno nuevo y el anterior deja de funcionar.
      </p>
      {fallo && <p className="error" role="alert">{fallo}</p>}
      <div className="gafetes">
        {items.map((i) => (
          <article key={i.id} className="gafete" aria-label={`Gafete de ${i.nombre}`}>
            <p className="gafete-marca">MBCC Consulting</p>
            {imgs[i.id] ? <img src={imgs[i.id]} alt={`QR de ${i.nombre}`} /> : <div className="gafete-qr-vacio" />}
            <b className="gafete-nombre">{i.nombre}</b>
            <span className="gafete-meta">Empleado {i.numero_empleado}{i.rol ? ` · ${ROL[i.rol] || i.rol}` : ''}</span>
            <small>Escanéalo en la pantalla de inicio de sesión</small>
          </article>
        ))}
      </div>
    </Modal>
  )
}

export default function CredencialesQR() {
  const cargar = useCallback(async () => {
    const [usuarios, roles] = await Promise.all([api.usuarios(), api.roles()])
    return { usuarios: lista(usuarios), roles: lista(roles) }
  }, [])
  const { data, estado, error, recargar } = useAsync(cargar)
  const [q, setQ] = useState('')
  const [sel, setSel] = useState(() => new Set())
  const [aviso, setAviso] = useState(null)
  const [ocupado, setOcupado] = useState(false)
  const [gafetes, setGafetes] = useState(null)
  const [confirm, setConfirm] = useState(null) // { u, tipo: 'regenerar' | 'revocar', error }

  const rolDe = useCallback((u) => data?.roles.find((r) => r.id === u.role_id)?.name || '', [data])
  const t = q.trim().toLowerCase()
  const filas = useMemo(
    () => (data?.usuarios || []).filter((u) => !t || `${u.employee_number} ${u.full_name}`.toLowerCase().includes(t)),
    [data, t],
  )

  const alternar = (id) => setSel((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n })
  // Los administradores no se incluyen en la selección rápida: su cuenta da acceso total y es mejor que entren con contraseña
  const sinQr = () => setSel(new Set((data?.usuarios || []).filter((u) => u.is_active && !u.qr_generated_at && rolDe(u) !== 'admin').map((u) => u.id)))

  const terminar = (items) => {
    setGafetes(items)
    setSel(new Set())
    recargar()
  }

  const generar = async (u) => {
    setOcupado(true); setAviso(null)
    try {
      const r = await api.generarQr(u.id)
      setConfirm(null)
      terminar([r])
    } catch (e) {
      if (confirm) setConfirm((c) => ({ ...c, error: e.message })); else setAviso({ tipo: 'error', texto: e.message })
    } finally { setOcupado(false) }
  }

  const masivo = async () => {
    setOcupado(true); setAviso(null)
    try {
      const r = await api.generarQrMasivo([...sel])
      if (!r.length) setAviso({ tipo: 'error', texto: 'Ninguno de los seleccionados está activo.' }); else terminar(r)
    } catch (e) { setAviso({ tipo: 'error', texto: e.message }) } finally { setOcupado(false) }
  }

  const revocar = async (u) => {
    setOcupado(true)
    try {
      await api.revocarQr(u.id)
      setConfirm(null); recargar()
      setAviso({ tipo: 'ok', texto: `QR de ${u.full_name} revocado.` })
    } catch (e) { setConfirm((c) => ({ ...c, error: e.message })) } finally { setOcupado(false) }
  }

  // Con QR vigente se pide confirmación antes de regenerar (el gafete impreso dejaría de funcionar)
  const pedirGenerar = (u) => (u.qr_generated_at ? setConfirm({ u, tipo: 'regenerar' }) : generar(u))
  const masivoConQr = [...sel].some((id) => data?.usuarios.find((u) => u.id === id)?.qr_generated_at)

  const confirmar = () => {
    if (confirm.tipo === 'revocar') revocar(confirm.u)
    else if (confirm.tipo === 'masivo') { setConfirm(null); masivo() }
    else generar(confirm.u)
  }

  return (
    <>
      <p className="muted">Cada empleado puede iniciar sesión con un QR único impreso en su gafete. Solo se guarda una huella del código; si se pierde, se genera uno nuevo y el anterior deja de servir.</p>
      <div className="toolbar">
        <label className="field grow"><input type="search" placeholder="Buscar empleado o nombre…" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Buscar empleados" /></label>
        <button type="button" className="btn btn-outline" onClick={sinQr} disabled={!data}>Seleccionar sin QR</button>
        <button type="button" className="btn btn-primary" onClick={() => (masivoConQr ? setConfirm({ tipo: 'masivo' }) : masivo())} disabled={!sel.size || ocupado}>
          <IconScan width={16} height={16} /> Generar gafetes ({sel.size})
        </button>
      </div>
      <Aviso aviso={aviso} onClose={() => setAviso(null)} />
      <Estado estado={estado} error={error} data={data} recargar={recargar} vacio={data && filas.length === 0 ? 'No hay empleados que coincidan.' : ''} />
      {data && filas.length > 0 && (
        <div className="rtable-wrap">
          <table className="rtable">
            <thead><tr><th>Sel.</th><th>Empleado</th><th>Nombre</th><th>Rol</th><th>QR</th><th /></tr></thead>
            <tbody>
              {filas.map((u) => (
                <tr key={u.id}>
                  <td data-label="Seleccionar"><input type="checkbox" checked={sel.has(u.id)} disabled={!u.is_active} onChange={() => alternar(u.id)} aria-label={`Seleccionar a ${u.full_name}`} /></td>
                  <td className="titulo">{u.employee_number}</td>
                  <td data-label="Nombre">{u.full_name}{!u.is_active && <> <span className="pill pill-warn">Inactivo</span></>}</td>
                  <td data-label="Rol">{ROL[rolDe(u)] || '—'}</td>
                  <td data-label="QR">{u.qr_generated_at ? <span className="pill pill-ok" title={fechaHora(u.qr_generated_at)}>Generado</span> : <span className="pill pill-off">Sin QR</span>}</td>
                  <td className="acciones">
                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => pedirGenerar(u)} disabled={!u.is_active || ocupado}>{u.qr_generated_at ? 'Regenerar' : 'Generar QR'}</button>
                    {u.qr_generated_at && <button type="button" className="btn btn-ghost btn-sm" onClick={() => setConfirm({ u, tipo: 'revocar' })} disabled={ocupado}>Revocar</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {gafetes && <Gafetes items={gafetes} onClose={() => setGafetes(null)} />}
      {confirm && (
        <Confirmar
          titulo={confirm.tipo === 'revocar' ? 'Revocar QR' : confirm.tipo === 'masivo' ? 'Regenerar gafetes' : 'Regenerar QR'}
          mensaje={confirm.tipo === 'revocar'
            ? `${confirm.u.full_name} ya no podrá entrar con su gafete y se cerrarán sus sesiones abiertas.`
            : confirm.tipo === 'masivo'
              ? 'Algunos de los seleccionados ya tienen QR. Su gafete actual dejará de funcionar y se cerrarán sus sesiones abiertas.'
              : `El gafete actual de ${confirm.u.full_name} dejará de funcionar y se cerrarán sus sesiones abiertas.`}
          textoConfirmar={confirm.tipo === 'revocar' ? 'Revocar' : 'Regenerar'}
          peligro
          ocupado={ocupado}
          error={confirm.error}
          onConfirmar={confirmar}
          onClose={() => setConfirm(null)}
        />
      )}
    </>
  )
}

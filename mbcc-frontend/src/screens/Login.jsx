import { useState } from 'react'
import { api, auth } from '../api.js'
import Logo from '../components/Logo.jsx'
import EscanerQR from '../components/EscanerQR.jsx'
import { IconUser, IconLock, IconEye, IconEyeOff, IconMoon, IconChevron, IconArrow, IconScan } from '../components/Icons.jsx'

export default function Login({ onLogin }) {
  const [empleado, setEmpleado] = useState('')
  const [password, setPassword] = useState('')
  const [turno, setTurno] = useState('')
  const [ver, setVer] = useState(false)
  const [error, setError] = useState('')
  const [cargando, setCargando] = useState(false)
  const [modo, setModo] = useState('clave') // clave | qr
  const [camara, setCamara] = useState(false)
  const [codigo, setCodigo] = useState('')

  const submit = async (event) => {
    event.preventDefault()
    if (modo === 'qr') return
    setError('')
    if (!empleado.trim() || !password) return setError('Ingresa tu número de empleado y contraseña.')
    setCargando(true)
    try {
      const { token, user } = await api.login({ numero_empleado: empleado, password, turno: turno || null })
      auth.token = token
      auth.user = user
      onLogin(user)
    } catch (requestError) {
      setError(requestError.message || 'No se pudo iniciar sesión.')
    } finally {
      setCargando(false)
    }
  }

  // Gafete con QR único: la cámara o un lector de códigos entregan el texto y el servidor lo valida
  const entrarQr = async (texto) => {
    const qr = texto.trim()
    setCamara(false); setCodigo(''); setError('')
    if (!qr) return setError('Escanea tu código QR.')
    setCargando(true)
    try {
      const { token, user } = await api.loginQr({ qr, turno: turno || null })
      auth.token = token
      auth.user = user
      onLogin(user)
    } catch (requestError) {
      setError(requestError.message || 'No se pudo iniciar sesión con el QR.')
    } finally {
      setCargando(false)
    }
  }
  const cambiarModo = (m) => { setModo(m); setError(''); setCamara(false); setCodigo('') }

  const turnoCampo = (
    <label className="field">
      <IconMoon />
      <select value={turno} onChange={(event) => setTurno(event.target.value)} aria-label="Turno">
        <option value="">Turno (opcional)</option><option value="1">Turno 1 · Matutino</option><option value="2">Turno 2 · Vespertino</option><option value="3">Turno 3 · Nocturno</option>
      </select>
      <IconChevron />
    </label>
  )

  return (
    <div className="login">
      <header className="login-head"><Logo size="lg" /><span className="divider" /><h1>Control de Calidad<br />y Trazabilidad</h1></header>
      <main className="login-main">
        <form className="card login-card" onSubmit={submit} noValidate>
          <Logo size="md" />
          <h2>Iniciar Sesión</h2>
          <p className="muted">{modo === 'qr' ? 'Escanea el código QR de tu gafete' : 'Ingresa tus credenciales para acceder al sistema'}</p>
          <div className="seg login-modos" role="group" aria-label="Forma de acceso">
            <button type="button" aria-pressed={modo === 'clave'} onClick={() => cambiarModo('clave')}>Empleado y contraseña</button>
            <button type="button" aria-pressed={modo === 'qr'} onClick={() => cambiarModo('qr')}>Código QR</button>
          </div>
          {modo === 'qr' ? (
            <>
              {turnoCampo}
              {camara ? (
                <>
                  <EscanerQR onCodigo={entrarQr} />
                  <button type="button" className="btn btn-outline btn-block" onClick={() => setCamara(false)}>Cancelar</button>
                </>
              ) : (
                <button type="button" className="btn btn-primary btn-block" onClick={() => { setError(''); setCamara(true) }} disabled={cargando}><IconScan /> Escanear con la cámara</button>
              )}
              <label className="field">
                <IconScan />
                <input
                  type="password" autoComplete="off" placeholder="O escanea con un lector de gafetes" value={codigo} aria-label="Código del gafete"
                  onChange={(event) => setCodigo(event.target.value)}
                  onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); entrarQr(codigo) } }}
                />
              </label>
              {cargando && <p className="muted" role="status">Entrando…</p>}
              {error && <p className="error" role="alert">{error}</p>}
            </>
          ) : (
          <>
          <label className="field">
            <IconUser />
            <input inputMode="numeric" autoComplete="username" placeholder="Número de Empleado" value={empleado} onChange={(event) => setEmpleado(event.target.value)} />
          </label>
          <label className="field">
            <IconLock />
            <input type={ver ? 'text' : 'password'} autoComplete="current-password" placeholder="Contraseña" value={password} onChange={(event) => setPassword(event.target.value)} />
            <button type="button" className="icon-btn" onClick={() => setVer(!ver)} aria-label={ver ? 'Ocultar contraseña' : 'Mostrar contraseña'}>
              {ver ? <IconEyeOff /> : <IconEye />}
            </button>
          </label>
          {turnoCampo}
          {error && <p className="error" role="alert">{error}</p>}
          <button className="btn btn-primary btn-block" disabled={cargando}>
            {cargando ? 'Entrando…' : <>Iniciar Sesión <IconArrow /></>}
          </button>
          </>
          )}
        </form>
      </main>
      <footer className="login-foot"><span>MBCC Consulting <i /> Control de Calidad y Trazabilidad</span><span>v1.0.0 &nbsp; Soporte: TI Interno</span></footer>
    </div>
  )
}
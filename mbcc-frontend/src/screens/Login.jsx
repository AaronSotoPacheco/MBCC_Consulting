import { useState } from 'react'
import { api, auth } from '../api.js'
import Logo from '../components/Logo.jsx'
import { IconUser, IconLock, IconEye, IconEyeOff, IconMoon, IconChevron, IconArrow } from '../components/Icons.jsx'

export default function Login({ onLogin }) {
  const [empleado, setEmpleado] = useState('')
  const [password, setPassword] = useState('')
  const [turno, setTurno] = useState('')
  const [ver, setVer] = useState(false)
  const [error, setError] = useState('')
  const [cargando, setCargando] = useState(false)

  const submit = async (event) => {
    event.preventDefault()
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

  return (
    <div className="login">
      <header className="login-head"><Logo size="lg" /><span className="divider" /><h1>Control de Calidad<br />y Trazabilidad</h1></header>
      <main className="login-main">
        <form className="card login-card" onSubmit={submit} noValidate>
          <Logo size="md" />
          <h2>Iniciar Sesión</h2>
          <p className="muted">Ingresa tus credenciales para acceder al sistema</p>
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
          <label className="field">
            <IconMoon />
            <select value={turno} onChange={(event) => setTurno(event.target.value)}>
              <option value="">Turno (opcional)</option><option value="1">Turno 1 · Matutino</option><option value="2">Turno 2 · Vespertino</option><option value="3">Turno 3 · Nocturno</option>
            </select>
            <IconChevron />
          </label>
          {error && <p className="error" role="alert">{error}</p>}
          <button className="btn btn-primary btn-block" disabled={cargando}>
            {cargando ? 'Entrando…' : <>Iniciar Sesión <IconArrow /></>}
          </button>
        </form>
      </main>
      <footer className="login-foot"><span>MBCC Consulting <i /> Control de Calidad y Trazabilidad</span><span>v1.0.0 &nbsp; Soporte: TI Interno</span></footer>
    </div>
  )
}
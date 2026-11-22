import { useState } from 'react'
import Logo from './Logo.jsx'
import CambiarPassword from './CambiarPassword.jsx'
import { esGestor, nivelDe, puede } from '../access.js'
import { useAlertas, useRecordatorioAsistencia } from '../hooks.js'
import { IconHome, IconChart, IconClock, IconSearch, IconGear, IconCalendar, IconLogout, IconUser, IconLock } from './Icons.jsx'

const NAV = [
  { to: '/ordenes', label: 'Órdenes Activas', short: 'Órdenes', Icon: IconHome },
  { to: '/reportes', label: 'Reportes', short: 'Reportes', Icon: IconChart },
  { to: '/trazabilidad', label: 'Trazabilidad', short: 'Series', Icon: IconSearch },
  { to: '/turno', label: 'Mi Turno', short: 'Mi Turno', Icon: IconClock },
  { to: '/asistencia', label: 'Asistencia', short: 'Asistencia', Icon: IconCalendar },
  { to: '/config', label: 'Configuración', short: 'Config.', Icon: IconGear },
]

export default function Shell({ user, route, navigate, onLogout, crumbs, children }) {
  const [cambiandoClave, setCambiandoClave] = useState(false)
  const nivel = nivelDe(user)
  const alertas = useAlertas(esGestor(nivel), 60000)
  const recordatorio = useRecordatorioAsistencia(!route.startsWith('/asistencia'), route, 10)
  const visibleNav = NAV.filter(({ to }) => puede(nivel, to))
  const activeTo = visibleNav.find((item) => route.startsWith(item.to))?.to || visibleNav[0]?.to || '/ordenes'

  return (
    <div className="shell">
      <header className="topbar">
        <Logo />
        {crumbs && (
          <nav className="crumbs only-desktop" aria-label="Ruta">
            {crumbs.map((crumb, index) => (
              <span key={index}>
                {crumb.to ? <a href={`#${crumb.to}`}>{crumb.label}</a> : <b>{crumb.label}</b>}
                {index < crumbs.length - 1 && <i>›</i>}
              </span>
            ))}
          </nav>
        )}
        <div className="spacer" />
        <span className="online"><i /> <em>En línea</em></span>
        <div className="who">
          <span className="avatar"><IconUser width={16} height={16} /></span>
          <div><strong>{user?.nombre}</strong><small>{user?.rol}</small></div>
        </div>
        <button className="btn btn-outline" onClick={() => setCambiandoClave(true)} aria-label="Cambiar contraseña" title="Cambiar contraseña">
          <IconLock width={16} height={16} /> <span className="hide-sm">Contraseña</span>
        </button>
        <button className="btn btn-outline-danger" onClick={onLogout}>
          <IconLogout width={16} height={16} /> <span className="hide-sm">Cerrar Sesión</span>
        </button>
      </header>

      <div className="shell-body">
        <aside className="sidebar">
          {visibleNav.map(({ to, label, Icon }) => (
            <a key={to} href={`#${to}`} className={activeTo === to ? 'active' : ''} onClick={() => navigate(to)}>
              <Icon /> {label}{to === '/ordenes' && alertas.length > 0 && <span className="nav-badge" title="Órdenes con rechazos altos">{alertas.length}</span>}
            </a>
          ))}
        </aside>
        <main className="content">
          {recordatorio.mostrar && (
            <div className="recordatorio" role="alert">
              <span><b>No has registrado tu entrada.</b> Llevas más de 10 minutos con la sesión abierta.</span>
              <span className="recordatorio-acc">
                <a className="btn btn-primary" href="#/asistencia" onClick={() => navigate('/asistencia')}>Registrar entrada</a>
                <button type="button" className="btn btn-outline" onClick={recordatorio.posponer}>Recordar en 10 min</button>
              </span>
            </div>
          )}
          {children}
        </main>
      </div>

      {cambiandoClave && <CambiarPassword onCerrar={() => setCambiandoClave(false)} />}

      <nav className="tabbar" aria-label="Navegación principal">
        {visibleNav.map(({ to, short, Icon }) => (
          <a key={to} href={`#${to}`} className={activeTo === to ? 'active' : ''}>
            <Icon width={20} height={20} /><span>{short}</span>{to === '/ordenes' && alertas.length > 0 && <i className="nav-badge nav-badge-tab" aria-label="Alertas de rechazos">{alertas.length}</i>}
          </a>
        ))}
      </nav>
    </div>
  )
}
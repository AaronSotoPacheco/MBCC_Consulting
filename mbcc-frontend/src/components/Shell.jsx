import Logo from './Logo.jsx'
import { IconHome, IconChart, IconClock, IconGear, IconLogout, IconUser } from './Icons.jsx'

const NAV = [
  { to: '/ordenes', label: 'Órdenes Activas', short: 'Órdenes', Icon: IconHome },
  { to: '/reportes', label: 'Reportes', short: 'Reportes', Icon: IconChart },
  { to: '/turno', label: 'Mi Turno', short: 'Mi Turno', Icon: IconClock },
  { to: '/config', label: 'Configuración', short: 'Config.', Icon: IconGear },
]

export default function Shell({ user, route, navigate, onLogout, crumbs, children }) {
  const activeTo = NAV.find((item) => route.startsWith(item.to))?.to || '/ordenes'

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
        <button className="btn btn-outline-danger" onClick={onLogout}>
          <IconLogout width={16} height={16} /> <span className="hide-sm">Cerrar Sesión</span>
        </button>
      </header>

      <div className="shell-body">
        <aside className="sidebar">
          {NAV.map(({ to, label, Icon }) => (
            <a key={to} href={`#${to}`} className={activeTo === to ? 'active' : ''} onClick={() => navigate(to)}>
              <Icon /> {label}
            </a>
          ))}
        </aside>
        <main className="content">{children}</main>
      </div>

      <nav className="tabbar" aria-label="Navegación principal">
        {NAV.map(({ to, short, Icon }) => (
          <a key={to} href={`#${to}`} className={activeTo === to ? 'active' : ''}>
            <Icon width={20} height={20} /><span>{short}</span>
          </a>
        ))}
      </nav>
    </div>
  )
}
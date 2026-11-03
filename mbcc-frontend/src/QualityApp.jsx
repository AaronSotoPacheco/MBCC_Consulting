import { useEffect, useState } from 'react'
import { api, auth } from './api.js'
import { ETIQUETA_NIVEL, nivelDe, puede } from './access.js'
import Shell from './components/Shell.jsx'
import Login from './screens/Login.jsx'
import Orders from './screens/Orders.jsx'
import Station from './screens/Station.jsx'
import Reports from './screens/Reports.jsx'

function useHashRoute() {
  const get = () => location.hash.replace(/^#/, '') || '/'
  const [route, setRoute] = useState(get)
  useEffect(() => {
    const on = () => setRoute(get())
    window.addEventListener('hashchange', on)
    return () => window.removeEventListener('hashchange', on)
  }, [])
  return [route, (to) => { location.hash = '#' + to }]
}

function SinAcceso({ nivel, navigate }) {
  return (
    <div className="no-access" role="alert">
      <h1 className="page-title">Sin acceso</h1>
      <p className="muted">
        Tu nivel de uso ({ETIQUETA_NIVEL[nivel]}) no tiene permiso para ver esta sección.
        Si la necesitas, solicita que revisen tu rol.
      </p>
      <button className="btn btn-primary" onClick={() => navigate('/ordenes')}>Volver a Órdenes</button>
    </div>
  )
}

export default function QualityApp() {
  const [route, navigate] = useHashRoute()
  const [user, setUser] = useState(auth.user)
  const [actual, setActual] = useState(() => {
    try { return JSON.parse(sessionStorage.getItem('mbcc_orden')) } catch { return null }
  })

  const logout = async () => {
    if (auth.token) await api.logout().catch(() => null)
    auth.token = null
    auth.user = null
    setUser(null)
    navigate('/login')
  }
  const login = (nextUser) => { setUser(nextUser); navigate('/ordenes') }

  if (!user || route === '/login' || route === '/') {
    if (user && route === '/') { navigate('/ordenes'); return null }
    return <Login onLogin={login} />
  }

  const nivel = nivelDe(user)
  const shellProps = { user, route, navigate, onLogout: logout }

  if (!puede(nivel, route)) {
    return <Shell {...shellProps}><SinAcceso nivel={nivel} navigate={navigate} /></Shell>
  }

  if (route.startsWith('/estacion/')) {
    const id = route.split('/')[2]
    return (
      <Shell {...shellProps} crumbs={[
        { label: 'Órdenes', to: '/ordenes' },
        { label: actual && String(actual.id) === id ? actual.so : `Orden ${id}` },
        { label: 'Estación de Trabajo' },
      ]}>
        <Station ordenId={id} orden={actual} />
      </Shell>
    )
  }

  if (route.startsWith('/reportes')) return <Shell {...shellProps}><Reports /></Shell>

  if (route.startsWith('/turno') || route.startsWith('/config')) {
    return (
      <Shell {...shellProps}>
        <h1 className="page-title">{route.startsWith('/turno') ? 'Mi Turno' : 'Configuración'}</h1>
        <p className="muted">Pantalla pendiente de diseñar.</p>
      </Shell>
    )
  }

  return (
    <Shell {...shellProps}>
      <Orders onOpen={(order) => {
        sessionStorage.setItem('mbcc_orden', JSON.stringify(order))
        setActual(order)
        navigate(`/estacion/${order.id}`)
      }} />
    </Shell>
  )
}
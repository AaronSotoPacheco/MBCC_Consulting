import { useEffect, useMemo, useState } from 'react'
import './App.css'

const quickLinks = ['Roles', 'Clientes', 'Part Numbers', 'Work Instructions', 'Defect Catalog', 'Serial Records']
const modules = [
  { id: 'dashboard', label: 'Dashboard', endpoint: null },
  { id: 'roles', label: 'Roles', endpoint: '/api/roles?per_page=100' },
  { id: 'clients', label: 'Clientes', endpoint: '/api/clients?per_page=100' },
  { id: 'partNumbers', label: 'Part Numbers', endpoint: '/api/part-numbers?per_page=100' },
  { id: 'workInstructions', label: 'Work Instructions', endpoint: '/api/work-instructions?per_page=100' },
  { id: 'defectCatalog', label: 'Defect Catalog', endpoint: '/api/defect-catalog?per_page=100' },
  { id: 'serialRecords', label: 'Serial Records', endpoint: '/api/serial-records?per_page=100' },
]

const formatCount = (value) => (Number.isFinite(value) ? value.toLocaleString('es-MX') : '0')

const renderValue = (value) => {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'Sí' : 'No'
  return String(value)
}

function App() {
  const [activeModule, setActiveModule] = useState('dashboard')
  const [moduleData, setModuleData] = useState({})
  const [metrics, setMetrics] = useState({
    roles: 0,
    clients: 0,
    partNumbers: 0,
    defects: 0,
    serialRecords: 0,
  })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchModuleData = async (moduleId) => {
    const module = modules.find((item) => item.id === moduleId)
    if (!module || !module.endpoint) return []

    const response = await fetch(module.endpoint)
    if (!response.ok) {
      throw new Error(`No se pudo cargar ${module.label}.`)
    }

    const payload = await response.json()
    return Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : []
  }

  useEffect(() => {
    const fetchDashboardData = async () => {
      try {
        setLoading(true)
        setError('')

        const endpoints = [
          { key: 'roles', url: '/api/roles?per_page=100' },
          { key: 'clients', url: '/api/clients?per_page=100' },
          { key: 'partNumbers', url: '/api/part-numbers?per_page=100' },
          { key: 'defects', url: '/api/defect-catalog?per_page=100' },
          { key: 'serialRecords', url: '/api/serial-records?per_page=100' },
        ]

        const results = await Promise.all(
          endpoints.map(async ({ key, url }) => {
            const response = await fetch(url)
            if (!response.ok) {
              throw new Error(`No se pudo cargar ${key}.`)
            }

            const payload = await response.json()
            const data = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : []
            return { key, count: data.length }
          }),
        )

        const nextMetrics = results.reduce((acc, item) => {
          acc[item.key] = item.count
          return acc
        }, {})

        setMetrics({
          roles: nextMetrics.roles ?? 0,
          clients: nextMetrics.clients ?? 0,
          partNumbers: nextMetrics.partNumbers ?? 0,
          defects: nextMetrics.defects ?? 0,
          serialRecords: nextMetrics.serialRecords ?? 0,
        })
      } catch (err) {
        setError(err.message)
      } finally {
        setLoading(false)
      }
    }

    if (activeModule === 'dashboard') {
      fetchDashboardData()
      return
    }

    const loadModule = async () => {
      try {
        setLoading(true)
        setError('')
        const data = await fetchModuleData(activeModule)
        setModuleData((current) => ({ ...current, [activeModule]: data }))
      } catch (err) {
        setError(err.message)
      } finally {
        setLoading(false)
      }
    }

    loadModule()
  }, [activeModule])

  const stats = useMemo(
    () => [
      { label: 'Roles activos', value: formatCount(metrics.roles), change: '+8.2%', tone: 'blue' },
      { label: 'Clientes', value: formatCount(metrics.clients), change: '+12.4%', tone: 'green' },
      { label: 'Part numbers', value: formatCount(metrics.partNumbers), change: '+5.1%', tone: 'purple' },
      { label: 'Defectos', value: formatCount(metrics.defects), change: '-2.4%', tone: 'amber' },
    ],
    [metrics],
  )

  const recentActivity = useMemo(
    () => [
      { title: 'Roles registrados en sistema', user: `${metrics.roles} registros`, time: 'Actualizado ahora', status: 'success' },
      { title: 'Clientes activos', user: `${metrics.clients} clientes`, time: 'Actualizado ahora', status: 'info' },
      { title: 'Defectos en catálogo', user: `${metrics.defects} defectos`, time: 'Actualizado ahora', status: 'warning' },
      { title: 'Serial records', user: `${metrics.serialRecords} registros`, time: 'Actualizado ahora', status: 'success' },
    ],
    [metrics],
  )

  const assignments = useMemo(
    () => [
      { name: 'Calidad A', progress: Math.min(100, Math.round((metrics.defects / Math.max(metrics.defects + 10, 1)) * 100)), value: `${Math.min(100, Math.round((metrics.defects / Math.max(metrics.defects + 10, 1)) * 100))}%` },
      { name: 'Producción', progress: 67, value: '67%' },
      { name: 'Rework', progress: 54, value: '54%' },
      { name: 'Logística', progress: 91, value: '91%' },
    ],
    [metrics],
  )

  const tasks = useMemo(
    () => [
      { name: 'Auditar defectos semanales', due: 'Hoy', priority: 'Alta' },
      { name: 'Validar clientes nuevos', due: 'Mañana', priority: 'Media' },
      { name: `Revisar ${metrics.partNumbers} part numbers`, due: 'Jueves', priority: 'Alta' },
      { name: 'Revisar serial records', due: 'Viernes', priority: 'Baja' },
    ],
    [metrics],
  )

  const performance = useMemo(() => {
    const average = Math.round(((metrics.roles + metrics.clients + metrics.partNumbers + metrics.defects) / 4) * 1.7)
    return Math.min(99, Math.max(40, average))
  }, [metrics])

  const currentModule = modules.find((item) => item.id === activeModule) ?? modules[0]
  const currentRecords = activeModule === 'dashboard' ? [] : moduleData[activeModule] ?? []

  const renderModuleTable = () => {
    if (currentRecords.length === 0) {
      return <p className="empty-state">No hay registros para mostrar.</p>
    }

    const keys = Object.keys(currentRecords[0] ?? {})
    const columns = keys.filter((key) => !['created_at', 'updated_at'].includes(key)).slice(0, 5)

    return (
      <div className="module-table-wrap">
        <table className="module-table">
          <thead>
            <tr>
              {columns.map((key) => (
                <th key={key}>{key}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {currentRecords.map((record) => (
              <tr key={record.id ?? `${record.name ?? 'row'}-${Math.random()}`}>
                {columns.map((column) => (
                  <td key={`${record.id ?? Math.random()}-${column}`}>{renderValue(record[column])}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  const renderDashboard = () => (
    <>
      <div className="stats-grid">
        {stats.map((stat) => (
          <article key={stat.label} className={`stat-card ${stat.tone}`}>
            <div>
              <span>{stat.label}</span>
              <strong>{loading ? '…' : stat.value}</strong>
            </div>
            <em>{stat.change}</em>
          </article>
        ))}
      </div>

      <div className="main-grid">
        <section className="panel panel-large">
          <div className="panel-header">
            <h2>Resumen operativo</h2>
            <button className="ghost-btn small" type="button">Ver reporte</button>
          </div>

          <div className="chart-box">
            {[65, 72, 58, 81, 70, 90, 78].map((value, index) => (
              <div key={index} className="chart-column">
                <span style={{ height: `${value}%` }}> </span>
              </div>
            ))}
          </div>

          <div className="chart-labels">
            {['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((day) => (
              <span key={day}>{day}</span>
            ))}
          </div>
        </section>

        <section className="panel">
          <div className="panel-header">
            <h2>Actividad reciente</h2>
            <button className="ghost-btn small" type="button">Todo</button>
          </div>

          <div className="activity-list">
            {recentActivity.map((item) => (
              <div key={item.title} className="activity-item">
                <span className={`dot ${item.status}`}></span>
                <div>
                  <strong>{item.title}</strong>
                  <small>{item.user}</small>
                </div>
                <time>{item.time}</time>
              </div>
            ))}
          </div>
        </section>
      </div>

      <div className="lower-grid">
        <section className="panel">
          <div className="panel-header">
            <h2>Asignaciones</h2>
            <button className="ghost-btn small" type="button">Detalle</button>
          </div>

          <div className="progress-list">
            {assignments.map((item) => (
              <div key={item.name} className="progress-item">
                <div className="progress-row">
                  <span>{item.name}</span>
                  <strong>{item.value}</strong>
                </div>
                <div className="progress-bar">
                  <span style={{ width: item.progress + '%' }}></span>
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className="panel">
          <div className="panel-header">
            <h2>Acciones rápidas</h2>
          </div>

          <div className="quick-links">
            {quickLinks.map((item) => (
              <button key={item} className="pill-btn" type="button">
                {item}
              </button>
            ))}
          </div>
        </section>

        <section className="panel">
          <div className="panel-header">
            <h2>Próximas tareas</h2>
          </div>

          <div className="task-list">
            {tasks.map((task) => (
              <div className="task-row" key={task.name}>
                <div>
                  <strong>{task.name}</strong>
                  <small>{task.due}</small>
                </div>
                <span className={`priority ${task.priority.toLowerCase()}`}>{task.priority}</span>
              </div>
            ))}
          </div>
        </section>
      </div>
    </>
  )

  return (
    <main className="dashboard-shell">
      <aside className="sidebar">
        <div className="brand-box">
          <div className="brand-mark">M</div>
          <div>
            <strong>MBCC</strong>
            <span>Consulting</span>
          </div>
        </div>

        <nav className="nav-menu">
          <p className="nav-label">General</p>
          {modules.map((module) => (
            <button
              key={module.id}
              type="button"
              className={`nav-item ${activeModule === module.id ? 'active' : ''}`}
              onClick={() => setActiveModule(module.id)}
            >
              {module.label}
            </button>
          ))}
        </nav>

        <div className="sidebar-card">
          <p>Performance</p>
          <strong>{performance}%</strong>
          <span>Mejora del mes</span>
        </div>
      </aside>

      <section className="content">
        <header className="topbar">
          <div>
            <p className="eyebrow">Panel general</p>
            <h1>{currentModule.label}</h1>
          </div>

          <div className="topbar-actions">
            <button className="ghost-btn" type="button">Exportar</button>
            <button className="primary-btn" type="button">+ Nuevo registro</button>
          </div>
        </header>

        {error && <div className="dashboard-error">{error}</div>}

        {activeModule === 'dashboard' ? renderDashboard() : (
          <section className="panel module-panel">
            <div className="module-header">
              <div>
                <h2>{currentModule.label}</h2>
                <p>Listado recibido desde Laravel</p>
              </div>
              <button className="primary-btn small-btn" type="button">Agregar</button>
            </div>

            {loading ? (
              <p className="empty-state">Cargando datos...</p>
            ) : (
              renderModuleTable()
            )}
          </section>
        )}
      </section>
    </main>
  )
}

export default App

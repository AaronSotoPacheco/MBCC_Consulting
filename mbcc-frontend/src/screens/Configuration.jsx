import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api.js'
import { nivelDe, puede } from '../access.js'
import { IconDoc, IconTrash, IconUser } from '../components/Icons.jsx'
import Instrucciones from './config/Instrucciones.jsx'
import CredencialesQR from './config/CredencialesQR.jsx'
import Bitacora from './config/Bitacora.jsx'

const SECTIONS = [
  { id: 'instrucciones', label: 'Instrucciones de trabajo', path: '/config/instrucciones', roles: ['supervisor', 'admin'] },
  { id: 'usuarios', label: 'Usuarios', path: '/config/usuarios', roles: ['admin'] },
  { id: 'qr', label: 'Credenciales QR', path: '/config/qr', roles: ['admin'] },
  { id: 'catalogos', label: 'Clientes y partes', path: '/config/catalogos', roles: ['admin'] },
  { id: 'defectos', label: 'Defectos', path: '/config/defectos', roles: ['admin'] },
  { id: 'bitacora', label: 'Bitácora', path: '/config/bitacora', roles: ['admin'] },
]

const list = (response) => Array.isArray(response) ? response : response?.data || []

function ResourceManager({
  title,
  description,
  singular,
  load,
  fields,
  columns,
  create,
  update,
  remove,
  serialize = (values) => values,
  rowActions,
  canDelete = () => false,
}) {
  const [items, setItems] = useState([])
  const [metadata, setMetadata] = useState({})
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [query, setQuery] = useState('')
  const [editing, setEditing] = useState(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [values, setValues] = useState({})

  const refresh = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const result = await load()
      setItems(list(result.items))
      setMetadata(result.metadata || {})
    } catch (requestError) {
      setError(requestError.message || `No se pudieron cargar ${title.toLowerCase()}.`)
    } finally {
      setLoading(false)
    }
  }, [load, title])

  useEffect(() => {
    let active = true
    load()
      .then((result) => {
        if (active) {
          setItems(list(result.items))
          setMetadata(result.metadata || {})
          setError('')
        }
      })
      .catch((requestError) => {
        if (active) setError(requestError.message || `No se pudieron cargar ${title.toLowerCase()}.`)
      })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [load, title])

  const fieldList = fields(metadata)
  const visible = useMemo(() => {
    const term = query.trim().toLocaleLowerCase()
    if (!term) return items
    return items.filter((item) => columns(metadata).some(({ search, key }) => {
      const value = search ? search(item) : item[key]
      return String(value ?? '').toLocaleLowerCase().includes(term)
    }))
  }, [items, query, metadata, columns])

  const openNew = () => {
    setEditing(null)
    setEditorOpen(true)
    setValues(Object.fromEntries(fieldList.map((field) => [field.key, field.defaultValue ?? (field.type === 'checkbox' ? false : '')])))
    setError('')
  }

  const openEdit = (item) => {
    setEditing(item)
    setEditorOpen(true)
    setValues(Object.fromEntries(fieldList.map((field) => [field.key, item[field.key] ?? (field.type === 'checkbox' ? false : '')])))
    setError('')
  }

  const save = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError('')
    try {
      const payload = serialize(values, editing)
      if (editing) await update(editing.id, payload)
      else await create(payload)
      setEditing(null)
      setEditorOpen(false)
      setValues({})
      await refresh()
    } catch (requestError) {
      setError(requestError.message || `No se pudo guardar ${singular.toLowerCase()}.`)
    } finally {
      setSaving(false)
    }
  }

  const deleteItem = async (item) => {
    if (!window.confirm(`¿Eliminar ${singular.toLowerCase()}${item.code ? ` ${item.code}` : ''}?`)) return
    setError('')
    try {
      await remove(item.id)
      await refresh()
    } catch (requestError) {
      setError(requestError.message || `No se pudo eliminar ${singular.toLowerCase()}.`)
    }
  }

  const performAction = async (item, action) => {
    setError('')
    try {
      await action.run(item)
      if (action.refresh !== false) await refresh()
    } catch (requestError) {
      setError(requestError.message || 'No se pudo actualizar el registro.')
    }
  }

  return (
    <section className="config-section">
      <header className="config-heading">
        <div><h2>{title}</h2><p className="muted">{description}</p></div>
        <button className="btn btn-primary" onClick={editorOpen ? () => { setEditing(null); setEditorOpen(false) } : openNew}>
          {editorOpen ? 'Cancelar' : `Nuevo ${singular.toLowerCase()}`}
        </button>
      </header>

      {error && <p className="error config-error" role="alert">{error}</p>}

      {editorOpen && (
        <form className="card config-editor" onSubmit={save}>
          <div className="config-editor-heading">
            <h3>{editing ? `Editar ${singular.toLowerCase()}` : `Nuevo ${singular.toLowerCase()}`}</h3>
            <button type="button" className="icon-btn" aria-label="Cerrar formulario" onClick={() => { setEditing(null); setEditorOpen(false) }}>×</button>
          </div>
          <div className="config-fields">
            {fieldList.map((field) => (
              <label className={`config-field${field.type === 'checkbox' ? ' config-check' : ''}`} key={field.key}>
                {field.type === 'checkbox' ? (
                  <>
                    <input type="checkbox" checked={Boolean(values[field.key])} onChange={(event) => setValues((current) => ({ ...current, [field.key]: event.target.checked }))} />
                    <span>{field.label}</span>
                  </>
                ) : (
                  <>
                    <span>{field.label}{field.required && !(editing && field.optionalOnEdit) && <i> *</i>}</span>
                    {field.type === 'select' ? (
                      <select
                        required={field.required && !(editing && field.optionalOnEdit)}
                        value={values[field.key] ?? ''}
                        onChange={(event) => setValues((current) => ({ ...current, [field.key]: event.target.value }))}
                      >
                        <option value="">Selecciona…</option>
                        {(field.options || []).map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                      </select>
                    ) : field.type === 'textarea' ? (
                      <textarea
                        required={field.required && !(editing && field.optionalOnEdit)}
                        maxLength={field.maxLength}
                        value={values[field.key] ?? ''}
                        placeholder={field.placeholder || ''}
                        onChange={(event) => setValues((current) => ({ ...current, [field.key]: event.target.value }))}
                      />
                    ) : (
                      <input
                        type={field.type || 'text'}
                        required={field.required && !(editing && field.optionalOnEdit)}
                        minLength={field.minLength}
                        maxLength={field.maxLength}
                        value={values[field.key] ?? ''}
                        placeholder={field.placeholder || ''}
                        onChange={(event) => setValues((current) => ({ ...current, [field.key]: event.target.value }))}
                      />
                    )}
                  </>
                )}
              </label>
            ))}
          </div>
          <div className="config-form-actions">
            <button className="btn btn-primary" disabled={saving}>{saving ? 'Guardando…' : 'Guardar cambios'}</button>
            <span className="muted small">* Campos obligatorios</span>
          </div>
        </form>
      )}

      <section className="card config-list">
        <div className="config-list-header">
          <div><h3>Registros</h3><span className="muted small">{loading ? 'Cargando…' : `${visible.length} de ${items.length}`}</span></div>
          <input className="config-search" type="search" placeholder={`Buscar ${singular.toLowerCase()}…`} value={query} onChange={(event) => setQuery(event.target.value)} />
        </div>
        {error && items.length === 0 && !loading && <div className="config-empty"><p>No fue posible mostrar los registros.</p><button className="btn btn-outline" onClick={refresh}>Reintentar</button></div>}
        {!error && loading && <p className="muted config-empty">Cargando registros…</p>}
        {!error && !loading && items.length === 0 && <p className="muted config-empty">Aún no hay registros. Puedes crear el primero.</p>}
        {!loading && items.length > 0 && visible.length === 0 && <p className="muted config-empty">No se encontraron coincidencias.</p>}
        {!loading && visible.length > 0 && (
          <div className="table-wrap">
            <table className="config-table rtable">
              <thead><tr>{columns(metadata).map((column) => <th key={column.label}>{column.label}</th>)}<th className="config-actions-heading">Acciones</th></tr></thead>
              <tbody>
                {visible.map((item) => (
                  <tr key={item.id}>
                    {columns(metadata).map((column, index) => <td key={column.label} className={index === 0 ? 'titulo' : undefined} data-label={column.label}>{column.render ? column.render(item) : item[column.key] || '—'}</td>)}
                    <td className="config-actions acciones">
                      <button className="btn btn-outline config-action" onClick={() => openEdit(item)}>Editar</button>
                      {rowActions?.(item).map((action) => (
                        <button className="btn btn-outline config-action" key={action.label} onClick={() => performAction(item, action)}>{action.label}</button>
                      ))}
                      {canDelete(item) && <button className="btn btn-outline-danger config-action" aria-label={`Eliminar ${singular.toLowerCase()}`} onClick={() => deleteItem(item)}><IconTrash width={15} height={15} />Eliminar</button>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </section>
  )
}

function Users() {
  const load = useCallback(async () => {
    const [users, roles] = await Promise.all([api.usuarios(), api.roles()])
    return { items: users, metadata: { roles: list(roles) } }
  }, [])
  const fields = useCallback(({ roles = [] }) => [
    { key: 'employee_number', label: 'Número de empleado', required: true, maxLength: 50 },
    { key: 'full_name', label: 'Nombre completo', required: true, maxLength: 150 },
    { key: 'role_id', label: 'Rol', type: 'select', required: true, options: roles.map((role) => ({ value: role.id, label: role.name })) },
    { key: 'password_hash', label: 'Contraseña', type: 'password', required: true, optionalOnEdit: true, minLength: 8, placeholder: 'Mínimo 8 caracteres' },
    { key: 'is_active', label: 'Usuario activo', type: 'checkbox', defaultValue: true },
  ], [])
  const columns = useCallback(({ roles = [] }) => [
    { label: 'Empleado', key: 'employee_number' },
    { label: 'Nombre', key: 'full_name' },
    { label: 'Rol', key: 'role_id', render: (item) => roles.find((role) => role.id === item.role_id)?.name || '—' },
    { label: 'Estado', key: 'is_active', render: (item) => <span className={`pill ${item.is_active ? 'pill-ok' : 'pill-warn'}`}>{item.is_active ? 'Activo' : 'Inactivo'}</span> },
  ], [])

  return <ResourceManager
    title="Usuarios"
    description="Administra las cuentas, roles y acceso al sistema."
    singular="Usuario"
    load={load}
    fields={fields}
    columns={columns}
    create={api.crearUsuario}
    update={api.actualizarUsuario}
    serialize={(values) => ({ ...values, role_id: Number(values.role_id), ...(values.password_hash ? {} : { password_hash: undefined }) })}
  />
}

function Catalogs() {
  const [entity, setEntity] = useState('clientes')

  return (
    <>
      <div className="config-subnav" aria-label="Catálogos">
        <button className={entity === 'clientes' ? 'active' : ''} onClick={() => setEntity('clientes')}>Clientes</button>
        <button className={entity === 'partes' ? 'active' : ''} onClick={() => setEntity('partes')}>Números de parte</button>
      </div>
      {entity === 'clientes' ? <Clients /> : <Parts />}
    </>
  )
}

function Clients() {
  const load = useCallback(async () => ({ items: await api.clientes() }), [])
  const fields = useCallback(() => [
    { key: 'name', label: 'Nombre del cliente', required: true, maxLength: 150 },
    { key: 'contact_email', label: 'Correo de contacto', type: 'email', maxLength: 100, placeholder: 'contacto@empresa.com' },
  ], [])
  const columns = useCallback(() => [
    { label: 'Cliente', key: 'name' },
    { label: 'Correo de contacto', key: 'contact_email' },
  ], [])

  return <ResourceManager
    title="Clientes"
    description="Mantén el catálogo de clientes al día."
    singular="Cliente"
    load={load}
    fields={fields}
    columns={columns}
    create={api.crearCliente}
    update={api.actualizarCliente}
  />
}

function Parts() {
  const load = useCallback(async () => {
    const [parts, clients] = await Promise.all([api.partes(), api.clientes()])
    return { items: parts, metadata: { clients: list(clients) } }
  }, [])
  const fields = useCallback(({ clients = [] }) => [
    { key: 'client_id', label: 'Cliente', type: 'select', required: true, options: clients.map((client) => ({ value: client.id, label: client.name })) },
    { key: 'part_number', label: 'Número de parte', required: true, maxLength: 100 },
    { key: 'description', label: 'Descripción', type: 'textarea', maxLength: 255 },
  ], [])
  const columns = useCallback(({ clients = [] }) => [
    { label: 'Número de parte', key: 'part_number' },
    { label: 'Descripción', key: 'description' },
    { label: 'Cliente', key: 'client_id', render: (item) => clients.find((client) => client.id === item.client_id)?.name || '—' },
  ], [])

  return <ResourceManager
    title="Números de parte"
    description="Relaciona cada número de parte con su cliente."
    singular="Número de parte"
    load={load}
    fields={fields}
    columns={columns}
    create={(data) => api.crearParte({ ...data, client_id: Number(data.client_id) })}
    update={(id, data) => api.actualizarParte(id, { ...data, client_id: Number(data.client_id) })}
  />
}

function Defects() {
  const load = useCallback(async () => ({ items: await api.defectosCatalogo() }), [])
  const fields = useCallback(() => [
    { key: 'code', label: 'Código', required: true, maxLength: 50, placeholder: 'DEF-001' },
    { key: 'description', label: 'Descripción', required: true, maxLength: 255 },
    { key: 'category', label: 'Categoría', type: 'select', required: true, defaultValue: 'COSMETIC', options: [{ value: 'COSMETIC', label: 'Cosmético' }, { value: 'MECHANICAL', label: 'Mecánico' }, { value: 'FUNCTIONAL', label: 'Funcional' }, { value: 'PACKAGING', label: 'Empaque' }] },
    { key: 'is_active', label: 'Defecto activo', type: 'checkbox', defaultValue: true },
  ], [])
  const columns = useCallback(() => [
    { label: 'Código', key: 'code' },
    { label: 'Descripción', key: 'description' },
    { label: 'Categoría', key: 'category', render: (item) => ({ COSMETIC: 'Cosmético', MECHANICAL: 'Mecánico', FUNCTIONAL: 'Funcional', PACKAGING: 'Empaque' }[item.category] || item.category) },
    { label: 'Estado', key: 'is_active', render: (item) => <span className={`pill ${item.is_active ? 'pill-ok' : 'pill-warn'}`}>{item.is_active ? 'Activo' : 'Inactivo'}</span> },
  ], [])

  return <ResourceManager
    title="Catálogo de defectos"
    description="Configura los defectos disponibles para las inspecciones."
    singular="Defecto"
    load={load}
    fields={fields}
    columns={columns}
    create={api.crearDefecto}
    update={(id, data) => api.actualizarDefecto(id, data)}
  />
}

export default function Configuration({ route, navigate, user }) {
  // Solo se muestran (y se abren) las secciones permitidas para el nivel del usuario
  const nivel = nivelDe(user)
  const visibleSections = SECTIONS.filter((item) => puede(nivel, item.path))
  const sectionId = route.split('/')[2]
  const section = visibleSections.find((item) => item.id === sectionId) || visibleSections[0] || SECTIONS[0]

  return (
    <div className="configuration">
      <div className="config-page-title">
        <div className="config-title-icon">{section.id === 'usuarios' ? <IconUser /> : <IconDoc />}</div>
        <div><p className="eyebrow">Administración</p><h1 className="page-title">Configuración</h1></div>
      </div>
      <p className="muted config-intro">Gestiona los catálogos y documentos que utiliza el control de calidad.</p>
      <nav className="config-tabs" aria-label="Secciones de configuración">
        {visibleSections.map((item) => (
          <button key={item.id} className={section.id === item.id ? 'active' : ''} onClick={() => navigate(item.path)}>{item.label}</button>
        ))}
      </nav>
      {section.id === 'instrucciones' && <Instrucciones />}
      {section.id === 'usuarios' && <Users />}
      {section.id === 'qr' && <CredencialesQR />}
      {section.id === 'catalogos' && <Catalogs />}
      {section.id === 'defectos' && <Defects />}
      {section.id === 'bitacora' && <Bitacora />}
    </div>
  )
}

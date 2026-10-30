export const ORDERS = [
  { id: 1, cliente: 'TechSolutions Inc.', so: 'SO-45872', parte: 'MB-7301-AX', descripcion: 'Carcasa superior - Ensamble', meta: 1200, estado: 'Abierta', instruccion: { codigo: 'WI-MB-7301-AX · Rev. 3', titulo: 'Ensamble y revisión de carcasa superior', puntos: ['Verificar alineación de tornillos.', 'Torque: 1.2 – 1.5 Nm.', 'Revisar ausencia de rayones, grietas o deformaciones.'] } },
  { id: 2, cliente: 'Global Motors', so: 'SO-45874', parte: 'MB-9710 de control - Fi', descripcion: 'Módulo de control - Final', meta: 850, estado: 'Abierta' },
  { id: 3, cliente: 'NorthTech', so: 'MB-6203-GR', parte: 'MB-8203-GR', descripcion: 'Sensor de posición', meta: 600, estado: 'Pausada' },
  { id: 4, cliente: 'AutoParts Co.', so: 'SO-45875', parte: 'MB-6650-TR', descripcion: 'Soporte de montaje', meta: 1500, estado: 'Abierta' },
  { id: 5, cliente: 'Vision Tech', so: 'SO-45876', parte: 'MB-9021-CV', descripcion: 'Conjunto de engranes', meta: 950, estado: 'Abierta' },
  { id: 6, cliente: 'ElectroLabs', so: 'SO-45877', parte: 'MB-3342-PL', descripcion: 'Placa electrónica', meta: 720, estado: 'Pausada' },
]

export const WORK_INSTRUCTION = {
  codigo: 'WI-MB-7301-AX · Rev. 3',
  titulo: 'Ensamble y revisión de carcasa superior',
  puntos: [
    'Verificar alineación de tornillos.',
    'Torque: 1.2 – 1.5 Nm.',
    'Revisar ausencia de rayones, grietas o deformaciones.',
  ],
}

export const DEFECTOS = ['Rayón en carcasa', 'Tornillo barrido', 'Falta de tornillo', 'Pieza dañada', 'Desalineación', 'Otro']

export const ESCANEOS_INICIALES = [
  { serie: 'SN-45872-005', resultado: 'OK', hora: '14:26' },
  { serie: 'SN-45872-004', resultado: 'OK', hora: '14:28' },
  { serie: 'SN-45872-003', resultado: 'NG', hora: '14:29' },
  { serie: 'SN-45872-002', resultado: 'OK', hora: '14:31' },
  { serie: 'SN-45872-001', resultado: 'OK', hora: '14:32' },
].sort((a, b) => b.hora.localeCompare(a.hora))

export const PARETO = [
  { defecto: 'Tornillo barrido', cantidad: 68 },
  { defecto: 'Rayón en carcasa', cantidad: 52 },
  { defecto: 'Falta de tornillo', cantidad: 38 },
  { defecto: 'Pieza dañada', cantidad: 24 },
  { defecto: 'Desalineación', cantidad: 16 },
  { defecto: 'Otros', cantidad: 9 },
]

export const KPIS = {
  eficiencia: { valor: 93.5, delta: '+2.4% vs. periodo anterior' },
  retrabajos: { valor: 47, detalle: '3.1% del total' },
  scrap: { valor: 12, detalle: '0.8% del total' },
}
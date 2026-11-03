export const ORDERS = [
  { id: 1, so: 'SO-45872', cliente: 'TechSolutions Inc.', parte: 'MB-7301-AX', descripcion: 'Carcasa superior - Ensamble', meta: 1200, inspeccionadas: 248, rechazadas: 16, estado: 'OPEN', turno: '8:00 AM - 4:00 PM', wi: { codigo: 'WI-MB-7301-AX', revision: '3', titulo: 'Ensamble y revisión de carcasa superior' } },
  { id: 2, so: 'SO-45874', cliente: 'Global Motors', parte: 'MB-9710-CT', descripcion: 'Módulo de control - Final', meta: 850, inspeccionadas: 310, rechazadas: 15, estado: 'OPEN', turno: '8:00 AM - 4:00 PM', wi: { codigo: 'WI-MB-9710-CT', revision: '1', titulo: 'Inspección final de módulo de control' } },
  { id: 4, so: 'SO-45875', cliente: 'AutoParts Co.', parte: 'MB-6650-TR', descripcion: 'Soporte de montaje', meta: 1500, inspeccionadas: 0, rechazadas: 0, estado: 'OPEN', turno: '8:00 AM - 4:00 PM', wi: { codigo: 'WI-MB-6650-TR', revision: '1', titulo: 'Revisión de soporte de montaje' } },
  { id: 5, so: 'SO-45876', cliente: 'Vision Tech', parte: 'MB-9021-CV', descripcion: 'Conjunto de engranes', meta: 950, inspeccionadas: 85, rechazadas: 3, estado: 'OPEN', turno: '8:00 AM - 4:00 PM', wi: { codigo: 'WI-MB-9021-CV', revision: '4', titulo: 'Revisión de conjunto de engranes' } },
  { id: 3, so: 'SO-45873', cliente: 'NorthTech', parte: 'MB-8203-GR', descripcion: 'Sensor de posición', meta: 600, inspeccionadas: 120, rechazadas: 10, estado: 'PAUSED', turno: '8:00 AM - 4:00 PM', wi: { codigo: 'WI-MB-8203-GR', revision: '2', titulo: 'Inspección de sensor de posición' } },
  { id: 6, so: 'SO-45877', cliente: 'ElectroLabs', parte: 'MB-3342-PL', descripcion: 'Placa electrónica', meta: 720, inspeccionadas: 0, rechazadas: 0, estado: 'PAUSED', turno: '8:00 AM - 4:00 PM', wi: { codigo: 'WI-MB-3342-PL', revision: '2', titulo: 'Inspección visual de placa electrónica' } },
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
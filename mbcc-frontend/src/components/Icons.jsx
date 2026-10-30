const base = {
  width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none',
  stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round',
  'aria-hidden': true,
}
const makeIcon = (children) => (props) => <svg {...base} {...props}>{children}</svg>

export const IconHome = makeIcon(<><path d="M3 11l9-8 9 8" /><path d="M5 10v10h14V10" /></>)
export const IconChart = makeIcon(<><path d="M4 20V10M10 20V4M16 20v-8M22 20H2" /></>)
export const IconClock = makeIcon(<><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>)
export const IconGear = makeIcon(<><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3h0a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5h0a1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l.1.1a1.7 1.7 0 00-.3 1.8v0a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z" /></>)
export const IconUser = makeIcon(<><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 4-6 8-6s8 2 8 6" /></>)
export const IconLock = makeIcon(<><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 018 0v4" /></>)
export const IconMoon = makeIcon(<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" />)
export const IconEye = makeIcon(<><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" /><circle cx="12" cy="12" r="3" /></>)
export const IconEyeOff = makeIcon(<><path d="M17.9 17.9A10.9 10.9 0 0112 20C5 20 1 12 1 12a18 18 0 015.1-5.9M9.9 4.2A10.9 10.9 0 0112 4c7 0 11 8 11 8a18 18 0 01-2.2 3.2M1 1l22 22" /></>)
export const IconArrow = makeIcon(<><path d="M5 12h14M13 6l6 6-6 6" /></>)
export const IconSearch = makeIcon(<><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></>)
export const IconFilter = makeIcon(<path d="M3 4h18l-7 8v7l-4 2v-9L3 4z" />)
export const IconPlay = makeIcon(<path d="M6 4l14 8-14 8V4z" fill="currentColor" />)
export const IconChevron = makeIcon(<path d="M6 9l6 6 6-6" />)
export const IconLogout = makeIcon(<><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" /></>)
export const IconMenu = makeIcon(<path d="M3 6h18M3 12h18M3 18h18" />)
export const IconDoc = makeIcon(<><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z" /><path d="M14 3v5h5M9 13h6M9 17h6" /></>)
export const IconScan = makeIcon(<><path d="M4 7V5a1 1 0 011-1h2M17 4h2a1 1 0 011 1v2M20 17v2a1 1 0 01-1 1h-2M7 20H5a1 1 0 01-1-1v-2M8 8v8M12 8v8M16 8v8" /></>)
export const IconCheck = makeIcon(<path d="M5 12l5 5 9-10" strokeWidth={3} />)
export const IconX = makeIcon(<path d="M6 6l12 12M18 6L6 18" strokeWidth={3} />)
export const IconTable = makeIcon(<><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18M9 5v14" /></>)
export const IconCalendar = makeIcon(<><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></>)
export const IconTrash = makeIcon(<><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15" /></>)
export const IconTool = makeIcon(<path d="M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6z" />)
export const IconGauge = makeIcon(<><path d="M12 21a9 9 0 119-9" /><path d="M12 12l4-4" /></>)
export const IconImage = makeIcon(<><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="9" cy="10" r="2" /><path d="M21 16l-5-5-8 8" /></>)
export const IconDownload = makeIcon(<><path d="M12 3v12M7 10l5 5 5-5M4 21h16" /></>)
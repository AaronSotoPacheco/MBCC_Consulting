export default function Logo({ size = 'md' }) {
  return (
    <div className={`logo logo-${size}`}>
      <svg viewBox="0 0 48 40" className="logo-mark" aria-hidden="true">
        <path d="M2 38L16 4h8l-6 16 10 18h-8L12 26 8 38z" fill="#2f5bff" />
        <path d="M24 38L36 8h8L32 38z" fill="#4c7dff" />
      </svg>
      <div className="logo-text"><strong>MBCC</strong><span>Consulting</span></div>
    </div>
  )
}
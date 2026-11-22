import logo from '../assets/logo-mbcc.svg'

export default function Logo({ size = 'md' }) {
  return (
    <div className={`logo logo-${size}`}>
      <img src={logo} alt="MBCC Consulting" className="logo-img" width="1614" height="600" />
    </div>
  )
}

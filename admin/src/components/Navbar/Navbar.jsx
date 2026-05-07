import React, { useContext } from 'react'
import './Navbar.css'
import { assets } from './../../assets/assets'
import { AdminContext } from '../../context/AdminContext'

const Navbar = () => {
  const { logout, user } = useContext(AdminContext)

  return (
    <header className="navbar admin-panel">
      <div className="navbar-brand">
        <img className="logo" src={assets.logo} alt="ardent admin logo" />
        <div className="navbar-copy">
          <span>Ardent hotel Admin</span>
          <p>Manage menu items, orders, stock levels, and delivery updates.</p>
        </div>
      </div>

      <div className="navbar-status">
        <div className="navbar-badge">
          <strong>Live dashboard</strong>
          <span>{user?.email || 'Admin session'}</span>
        </div>
        <img src={assets.profile_image} alt="Admin profile" className="profile" />
        <button className="navbar-logout" onClick={logout}>
          Sign out
        </button>
      </div>
    </header>
  )
}

export default Navbar

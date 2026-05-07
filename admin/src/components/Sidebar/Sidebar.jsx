import React from 'react'
import './Sidebar.css'
import { assets } from '../../assets/assets'
import { NavLink } from 'react-router-dom'

const Sidebar = () => {
  return (
    <aside className="sidebar admin-panel">
      <div className="sidebar-copy">
        <span className="admin-eyebrow">Admin tools</span>
        <h2>Control center</h2>
        <p>Switch between menu creation, full catalog editing, inventory overview, and live order handling.</p>
      </div>

      <div className="sidebar-options">
        <NavLink to="/add" className="sidebar-option">
          <img src={assets.add_icon} alt="" />
          <div>
            <p>Add items</p>
            <span>Create new menu dishes</span>
          </div>
        </NavLink>
        <NavLink to="/list" className="sidebar-option">
          <img src={assets.order_icon} alt="" />
          <div>
            <p>Food list</p>
            <span>Review, edit, and remove items</span>
          </div>
        </NavLink>
        <NavLink to="/orders" className="sidebar-option">
          <img src={assets.parcel_icon} alt="" />
          <div>
            <p>Orders</p>
            <span>Track guest delivery status</span>
          </div>
        </NavLink>
        <NavLink to="/room-bookings" className="sidebar-option">
          <img src={assets.parcel_icon} alt="" />
          <div>
            <p>Room bookings</p>
            <span>Handle hotel stay reservations</span>
          </div>
        </NavLink>
      </div>
    </aside>
  )
}

export default Sidebar

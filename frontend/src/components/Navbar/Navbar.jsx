import React, { useContext, useState } from 'react'
import './Navbar.css'
import { assets } from './../../assets/assets';
import {Link, useNavigate} from 'react-router-dom'
import { StoreContext } from './../context/StoreContext';

const Navbar = ({setShowLogin}) => {

  const [menu, setMenu] = useState('home');

  const {
    adminUrl,
    favoriteCount,
    getTotalCartAmount,
    isAdmin,
    logout,
    token,
    user,
  } =
    useContext(StoreContext)

  const navigate = useNavigate();

  const scrollToSection = (sectionId, nextMenu) => {
    setMenu(nextMenu)

    const performScroll = () => {
      document
        .getElementById(sectionId)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }

    if (window.location.pathname !== '/') {
      navigate('/')
      window.setTimeout(performScroll, 120)
      return
    }

    performScroll()
  }

  return (
    <div className='navbar panel'>
       <Link to='/' className='navbar-brand' onClick={()=> setMenu('home')}>
        <img src={assets.logo} alt="ardent logo" className='logo' />
        <div>
          <span>Ardent</span>
          <p>Fresh dishes, fast delivery, smooth checkout.</p>
        </div>
       </Link>
        <ul className="navbar-menu">
            <Link to='/' onClick={()=> setMenu('home')} className={menu === 'home'?'active':''}>Home</Link>
            <Link to='/rooms' onClick={()=> setMenu('rooms')} className={menu === 'rooms'?'active':''}>Rooms</Link>
            <button type="button" onClick={() => scrollToSection('explore-menu', 'menu')} className={menu === 'menu'?'active':''}>Menu</button>
            <button type="button" onClick={() => scrollToSection('location-tracker', 'tracking')} className={menu === 'tracking'?'active':''}>Tracking</button>
            <button type="button" onClick={() => scrollToSection('footer', 'contact-us')} className={menu === 'contact-us'?'active':''}>Contact</button>
        </ul>
        <div className="navbar-right">
            <button
              type="button"
              className="navbar-search"
              onClick={() => scrollToSection('food-display', 'menu')}
            >
              <img src={assets.search_icon} alt="" />
              <span>Search menu</span>
            </button>
            <div className="navbar-search-icon">
                <Link to='/cart'><img src={assets.basket_icon} alt="Cart" /></Link>
                <div className={getTotalCartAmount()===0?'':'dot'}></div>
            </div>
            {!token?<button onClick={()=> setShowLogin(true)}>Sign in</button>
            :<div className='navbar-profile'>
              <img src={assets.profile_icon} alt="Profile" />
              <ul className="nav-profile-dropdown">
                <li>
                  <p>{user?.name || 'Signed in'}</p>
                </li>
                <hr />
                <li onClick={()=> navigate('/profile')}><img src={assets.profile_icon} alt="" /><p>Profile</p></li>
                <li onClick={()=> navigate('/wishlist')}><img src={assets.bag_icon} alt="" /><p>Wishlist ({favoriteCount})</p></li>
                <li onClick={()=> navigate('/myorders')}><img src={assets.bag_icon} alt="" /><p>Orders</p></li>
                <li onClick={()=> navigate('/my-room-bookings')}><img src={assets.parcel_icon} alt="" /><p>Room bookings</p></li>
                {isAdmin ? (
                  <li onClick={() => window.open(adminUrl, '_blank', 'noopener,noreferrer')}>
                    <img src={assets.bag_icon} alt="" />
                    <p>Admin panel</p>
                  </li>
                ) : null}
                <hr />  
                <li onClick={() => {
                  logout()
                  navigate("/")
                }}><img src={assets.logout_icon} alt="" /><p>Logout</p></li>
              </ul>
            </div>
            }
              </div>
    </div>
  )
}

export default Navbar

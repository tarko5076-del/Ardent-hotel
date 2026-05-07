import React from 'react'
import './Header.css'

const Header = () => {
  return (
    <div className='header'>
        <div className="header-contents">
            <span className="eyebrow">Fast, fresh food delivery</span>
            <h1>Chef-made meals, quick delivery.</h1>
            <p>
              Explore popular comfort food, lighter favorites, desserts and late-night bites with a smooth ordering experience on every screen.
            </p>
            <div className="header-actions">
              <a href="#explore-menu" className="action-button">Browse menu</a>
              <a href="#food-display" className="ghost-button">Popular dishes</a>
            </div>
            <div className="header-highlights">
              <div>
                <strong>25 min</strong>
                <span>average delivery</span>
              </div>
              <div>
                <strong>4.9/5</strong>
                <span>guest satisfaction</span>
              </div>
              <div>
                <strong>Fresh daily</strong>
                <span>made-to-order quality</span>
              </div>
            </div>
        </div>
    </div>
  )
}

export default Header

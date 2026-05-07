import React, { useContext } from 'react'
import { useNavigate } from 'react-router-dom'
import './ExploreMenu.css'
import { menu_list } from '../../assets/assets'
import { StoreContext } from '../context/StoreContext'

const ExploreMenu = ({ category, setCategory }) => {
  const { food_list, addToCart } = useContext(StoreContext)
  const navigate = useNavigate()

  const toggleCategory = (menuName) => {
    setCategory((prev) => (prev === menuName ? 'All' : menuName))
  }

  return (
    <div className="explore-menu" id="explore-menu">
      <div className="explore-menu-copy">
        <span className="eyebrow">Browse by category</span>
        <h2 className="section-title">Find the right mood, meal, and moment.</h2>
        <p className="explore-menu-text">
          From light salads to comforting pasta and desserts, Ardent  helps
          customers quickly discover dishes that feel personal, timely and
          satisfying.
        </p>
      </div>

      <div className="explore-menu-list">
        {menu_list.map((item, index) => {
          const featuredFood = food_list.find(
            (food) => food.category === item.menu_name
          )
          const featuredId = featuredFood?._id || String(featuredFood?.id || '')

          return (
            <div
              onClick={() => toggleCategory(item.menu_name)}
              key={index}
              className={`explore-menu-list-item ${
                category === item.menu_name ? 'selected' : ''
              }`}
            >
              <div className="explore-menu-list-item-top">
                <img
                  className={category === item.menu_name ? 'active' : ''}
                  src={item.menu_image}
                  alt={item.menu_name}
                />
                <div className="explore-menu-list-item-copy">
                  <p>{item.menu_name}</p>
                  <span>
                    {category === item.menu_name ? 'Selected category' : 'Tap to explore'}
                  </span>
                </div>
              </div>

              {featuredFood ? (
                <div className="explore-menu-preview">
                  <strong>{featuredFood.name}</strong>
                  <small>{featuredFood.description}</small>
                  <div className="explore-menu-preview-footer">
                    <b>${featuredFood.price}</b>
                    {featuredFood.isAvailable ? (
                      <button
                        type="button"
                        onClick={async (event) => {
                          event.stopPropagation()
                          const itemWasAdded = await addToCart(featuredId)

                          if (itemWasAdded) {
                            navigate('/cart')
                          }
                        }}
                      >
                        Add to cart
                      </button>
                    ) : (
                      <button type="button" disabled className="soldout-button">
                        Sold out
                      </button>
                    )}
                  </div>
                </div>
              ) : (
                <div className="explore-menu-preview empty">
                  <strong>Coming soon</strong>
                  <small>We are preparing dishes for this category.</small>
                </div>
              )}
            </div>
          )
        })}
      </div>
      <hr />
    </div>
  )
}

export default ExploreMenu

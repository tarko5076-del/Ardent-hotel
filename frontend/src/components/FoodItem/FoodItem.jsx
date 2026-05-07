import React, { useContext } from 'react'
import { useNavigate } from 'react-router-dom'
import { assets } from '../../assets/assets'
import { StoreContext } from '../context/StoreContext'
import './FoodItem.css'

const FoodItem = ({
  id,
  name,
  price,
  description,
  image,
  stockQuantity,
  isAvailable,
  onOpen,
  redirectToCart = false,
}) => {
  const {
    cartItems,
    addToCart,
    isFavorite,
    removeFromCart,
    toggleFavorite,
    url,
  } = useContext(StoreContext)
  const navigate = useNavigate()

  const handleAddToCart = async (event, shouldNavigate = false) => {
    event.stopPropagation()
    const itemWasAdded = await addToCart(id)

    if (itemWasAdded && shouldNavigate) {
      navigate('/cart')
    }
  }

  const handleFavoriteToggle = async (event) => {
    event.stopPropagation()
    await toggleFavorite(id)
  }

  return (
    <article
      className={`food-item ${!isAvailable ? 'sold-out' : ''}`}
      onClick={onOpen}
      role="button"
      tabIndex={0}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          onOpen()
        }
      }}
    >
      <div className="food-item-img-container">
        <img className="food-item-image" src={`${url}/images/${image}`} alt={name} />
        <button
          type="button"
          className={`food-item-save ${isFavorite(id) ? 'saved' : ''}`}
          onClick={handleFavoriteToggle}
        >
          {isFavorite(id) ? 'Saved' : 'Save'}
        </button>
        <span className="food-item-tag">
          {isAvailable ? "Chef's choice" : 'Sold out'}
        </span>
        {!isAvailable ? (
          <div className="food-item-soldout-badge">Unavailable</div>
        ) : !cartItems[id] ? (
          <img
            className="add"
            src={assets.add_icon_white}
            alt="Add to cart"
            onClick={(event) => handleAddToCart(event, redirectToCart)}
          />
        ) : (
          <div className="food-item-counter">
            <img
              onClick={(event) => {
                event.stopPropagation()
                removeFromCart(id)
              }}
              src={assets.remove_icon_red}
              alt="Remove"
            />
            <p>{cartItems[id]}</p>
            <img
              onClick={(event) => handleAddToCart(event)}
              src={assets.add_icon_green}
              alt="Add"
            />
          </div>
        )}
      </div>

      <div className="food-item-info">
        <div className="food-item-name-rating">
          <p>{name}</p>
          <img src={assets.rating_starts} alt="Rating" />
        </div>
        <p className="food-item-desc">{description}</p>
        <div className="food-item-footer">
          <p className="food-item-price">${price}</p>
          <span>{isAvailable ? `${stockQuantity} left` : 'Tap for details'}</span>
        </div>
      </div>
    </article>
  )
}

export default FoodItem

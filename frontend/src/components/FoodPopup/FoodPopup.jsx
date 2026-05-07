import React, { useContext } from 'react'
import { useNavigate } from 'react-router-dom'
import './FoodPopup.css'
import { assets } from '../../assets/assets'
import { StoreContext } from '../context/StoreContext'

const FoodPopup = ({ item, onClose, redirectToCart = false }) => {
  const {
    addToCart,
    cartItems,
    isFavorite,
    removeFromCart,
    toggleFavorite,
    url,
  } = useContext(StoreContext)
  const navigate = useNavigate()

  if (!item) {
    return null
  }

  const itemId = item._id || String(item.id)

  const handleAddToCart = async (shouldNavigate = false) => {
    const itemWasAdded = await addToCart(itemId)

    if (itemWasAdded && shouldNavigate) {
      onClose()
      navigate('/cart')
    }
  }

  return (
    <div className="food-popup" onClick={onClose}>
      <div className="food-popup-card" onClick={(event) => event.stopPropagation()}>
        <button type="button" className="food-popup-close" onClick={onClose}>
          <img src={assets.cross_icon} alt="Close" />
        </button>

        <div className="food-popup-media">
          <img src={`${url}/images/${item.image}`} alt={item.name} />
          <span>{item.isAvailable ? "Chef's special" : 'Currently sold out'}</span>
        </div>

        <div className="food-popup-content">
          <div className="food-popup-heading">
            <div>
              <h3>{item.name}</h3>
              <p>{item.category}</p>
            </div>
            <div className="food-popup-heading-actions">
              <img src={assets.rating_starts} alt="Rating" />
              <button
                type="button"
                className={`food-popup-save ${isFavorite(itemId) ? 'saved' : ''}`}
                onClick={() => toggleFavorite(itemId)}
              >
                {isFavorite(itemId) ? 'Saved to wishlist' : 'Save to wishlist'}
              </button>
            </div>
          </div>

          <p className="food-popup-description">{item.description}</p>

          <div className="food-popup-meta">
            <strong>${item.price}</strong>
            <span>
              {item.isAvailable
                ? `${item.stock_quantity} available right now`
                : 'This dish will return once stock is updated'}
            </span>
          </div>

          {!item.isAvailable ? (
            <button type="button" className="food-popup-action soldout" disabled>
              Sold out
            </button>
          ) : !cartItems[itemId] ? (
            <button
              type="button"
              className="food-popup-action"
              onClick={() => handleAddToCart(redirectToCart)}
            >
              Add to cart
            </button>
          ) : (
            <div className="food-popup-counter">
              <button type="button" onClick={() => removeFromCart(itemId)}>
                -
              </button>
              <p>{cartItems[itemId]}</p>
              <button type="button" onClick={() => handleAddToCart()}>
                +
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default FoodPopup

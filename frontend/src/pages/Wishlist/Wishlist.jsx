import React, { useContext, useState } from 'react'
import { Link } from 'react-router-dom'
import FoodItem from '../../components/FoodItem/FoodItem'
import FoodPopup from '../../components/FoodPopup/FoodPopup'
import { StoreContext } from '../../components/context/StoreContext'
import './Wishlist.css'

const Wishlist = () => {
  const { favoriteItems, token } = useContext(StoreContext)
  const [selectedFood, setSelectedFood] = useState(null)

  if (!token) {
    return (
      <section className="wishlist-page">
        <div className="wishlist-empty">
          <h2>Sign in to use your wishlist.</h2>
          <p>
            Save dishes you want to come back to later, then open them here
            from any signed-in session.
          </p>
          <Link to="/" className="action-button">
            Back to menu
          </Link>
        </div>
      </section>
    )
  }

  return (
    <section className="wishlist-page">
      <FoodPopup
        item={selectedFood}
        onClose={() => setSelectedFood(null)}
        redirectToCart
      />

      <div className="wishlist-copy">
        <span className="eyebrow">Saved dishes</span>
        <h1 className="section-title">Your wishlist</h1>
        <p>
          Keep track of favorites you want to reorder later and jump back into
          the cart whenever you are ready.
        </p>
      </div>

      {!favoriteItems.length ? (
        <div className="wishlist-empty">
          <h2>Your wishlist is still empty.</h2>
          <p>
            Tap the save button on any dish card to keep it here for later.
          </p>
          <Link to="/" className="action-button">
            Explore the menu
          </Link>
        </div>
      ) : (
        <>
          <div className="wishlist-summary">
            <strong>{favoriteItems.length}</strong>
            <span>saved dish{favoriteItems.length === 1 ? '' : 'es'}</span>
          </div>
          <div className="wishlist-grid">
            {favoriteItems.map((item) => (
              <FoodItem
                key={item._id || String(item.id)}
                id={item._id || String(item.id)}
                name={item.name}
                description={item.description}
                price={item.price}
                image={item.image}
                stockQuantity={item.stock_quantity}
                isAvailable={item.isAvailable}
                item={item}
                onOpen={() => setSelectedFood(item)}
                redirectToCart
              />
            ))}
          </div>
        </>
      )}
    </section>
  )
}

export default Wishlist

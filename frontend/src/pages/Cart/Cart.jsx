import React, { useContext } from 'react'
import { useNavigate } from 'react-router-dom'
import { StoreContext } from '../../components/context/StoreContext'
import './Cart.css'

const Cart = () => {
  const {
    addToCart,
    cartItems,
    food_list,
    getTotalCartAmount,
    paymentConfig,
    token,
    updateCartItemQuantity,
    url,
  } = useContext(StoreContext)

  const navigate = useNavigate()
  const subtotal = getTotalCartAmount()
  const deliveryFee = subtotal === 0 ? 0 : Number(paymentConfig.deliveryFee || 0)
  const total = subtotal + deliveryFee
  const cartLineItems = food_list.filter((item) => Number(cartItems[item._id]) > 0)

  return (
    <div className="cart">
      <div className="cart-hero">
        <span className="eyebrow">Your selections</span>
        <h1 className="section-title">Review your cart before checkout.</h1>
        <p>
          Adjust quantities, apply your promo code, and move into a smooth
          payment flow when everything looks right.
        </p>
      </div>

      <div className="cart-items">
        <div className="cart-items-title">
          <p>Items</p>
          <p>Title</p>
          <p>Price</p>
          <p>Quantity</p>
          <p>Total</p>
          <p>Remove</p>
        </div>
        <br />
        <hr />
        {!cartLineItems.length ? (
          <div className="cart-empty">
            <h3>Your cart is still empty.</h3>
            <p>Add a few dishes from the home page to start building your order.</p>
          </div>
        ) : (
          cartLineItems.map((item) => {
            return (
              <div key={item._id}>
                <div className="cart-items-title cart-items-item">
                  <img src={url + '/images/' + item.image} alt={item.name} />
                  <p>{item.name}</p>
                  <p>${item.price}</p>
                  <div className="cart-qty-controls">
                    <button onClick={() => updateCartItemQuantity(item._id, cartItems[item._id] - 1)}>
                      -
                    </button>
                    <span>{cartItems[item._id]}</span>
                    <button onClick={() => addToCart(item._id)}>+</button>
                  </div>
                  <p>${item.price * cartItems[item._id]}</p>
                  <button
                    onClick={() => updateCartItemQuantity(item._id, 0)}
                    className="cart-remove-button"
                  >
                    Remove
                  </button>
                </div>
                <hr />
              </div>
            )
          })
        )}
      </div>

      <div className="cart-bottom">
        <div className="cart-total">
          <h2>Cart Total</h2>
          <div>
            <div className="cart-total-detail">
              <p>Subtotal</p>
              <p>${subtotal}</p>
            </div>
            <hr />
            <div className="cart-total-detail">
              <p>Delivery Fee</p>
              <p>${deliveryFee}</p>
            </div>
            <hr />
            <div className="cart-total-detail">
              <b>Total</b>
              <b>${total}</b>
            </div>
          </div>
          <button
            onClick={() => {
              if (!token) {
                window.alert('Please sign in from the top bar before checkout.')
                return
              }

              navigate('/order')
            }}
          >
            {token ? 'Proceed to checkout' : 'Sign in to checkout'}
          </button>
        </div>

        <div className="cart-promocode">
          <div>
            <p>Checkout support</p>
            <div className="cart-note">
              <strong>Delivery is calculated automatically.</strong>
              <span>
                Sign in to sync your cart, choose cash on delivery or Chapa checkout,
                and track every order after checkout.
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Cart

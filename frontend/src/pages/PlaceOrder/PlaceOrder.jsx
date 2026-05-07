import axios from 'axios'
import React, { useContext, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useNavigate } from 'react-router-dom'
import { StoreContext } from '../../components/context/StoreContext'
import './PlaceOrder.css'

const PlaceOrder = () => {
  const {
    getTotalCartAmount,
    paymentConfig,
    refreshCatalog,
    refreshCart,
    token,
    user,
    url,
  } = useContext(StoreContext)

  const [data, setData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    street: '',
    city: '',
    state: '',
    zipcode: '',
    country: '',
    phone: '',
  })
  const [paymentMethod, setPaymentMethod] = useState('COD')

  const subtotal = getTotalCartAmount()
  const deliveryFee = subtotal === 0 ? 0 : Number(paymentConfig.deliveryFee || 0)
  const total = subtotal + deliveryFee
  const navigate = useNavigate()

  const onChangeHandler = (event) => {
    const name = event.target.name
    const value = event.target.value
    setData((prev) => ({ ...prev, [name]: value }))
  }

  const placeOrder = async (event) => {
    event.preventDefault()

    try {
      const response = await axios.post(
        url + '/api/order/place',
        {
          address: data,
          paymentMethod,
        },
        {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        }
      )

      if (response.data.success && response.data.session_url) {
        window.location.replace(response.data.session_url)
      } else if (response.data.success) {
        await Promise.all([refreshCart(), refreshCatalog()])
        navigate('/myorders')
      } else {
        alert(response.data.message || 'Error starting payment')
      }
    } catch (error) {
      console.error('Order Error:', error)
      alert('Error starting payment, please try again.')
    }
  }

  useEffect(() => {
    if (!token || subtotal === 0) {
      navigate('/cart')
    }
  }, [token, subtotal, navigate])

  useEffect(() => {
    if (!user) {
      return
    }

    const savedAddress = user.defaultAddress || {}
    const [firstName = '', ...lastNameParts] = (user.name || '').split(' ')
    setData((previousData) => ({
      ...previousData,
      firstName:
        previousData.firstName || savedAddress.firstName || firstName,
      lastName:
        previousData.lastName ||
        savedAddress.lastName ||
        lastNameParts.join(' '),
      email: previousData.email || savedAddress.email || user.email || '',
      street: previousData.street || savedAddress.street || '',
      city: previousData.city || savedAddress.city || '',
      state: previousData.state || savedAddress.state || '',
      zipcode: previousData.zipcode || savedAddress.zipcode || '',
      country: previousData.country || savedAddress.country || '',
      phone: previousData.phone || savedAddress.phone || user.phone || '',
    }))
  }, [user])

  return (
    <form onSubmit={placeOrder} className="place-order">
      <div className="place-order-left">
        <span className="eyebrow">Checkout</span>
        <p className="title">Delivery Information</p>
        <p className="place-order-subtitle">
          Tell us where to send your order so we can deliver it quickly and
          accurately.
        </p>
        <div className="place-order-profile-note">
          <strong>Saved profile support</strong>
          <span>
            Your checkout form reuses the delivery details from your profile
            whenever they are available.
          </span>
          <Link to="/profile">Manage saved details</Link>
        </div>

        <div className="multi-fields">
          <input
            required
            name="firstName"
            onChange={onChangeHandler}
            value={data.firstName}
            type="text"
            placeholder="First Name"
          />
          <input
            required
            name="lastName"
            onChange={onChangeHandler}
            value={data.lastName}
            type="text"
            placeholder="Last Name"
          />
        </div>
        <input
          required
          name="email"
          onChange={onChangeHandler}
          value={data.email}
          type="email"
          placeholder="Email address"
        />
        <input
          required
          name="street"
          onChange={onChangeHandler}
          value={data.street}
          type="text"
          placeholder="Street"
        />
        <div className="multi-fields">
          <input
            required
            name="city"
            onChange={onChangeHandler}
            value={data.city}
            type="text"
            placeholder="City"
          />
          <input
            required
            name="state"
            onChange={onChangeHandler}
            value={data.state}
            type="text"
            placeholder="State"
          />
        </div>
        <div className="multi-fields">
          <input
            required
            name="zipcode"
            onChange={onChangeHandler}
            value={data.zipcode}
            type="text"
            placeholder="Zip code"
          />
          <input
            required
            name="country"
            onChange={onChangeHandler}
            value={data.country}
            type="text"
            placeholder="Country"
          />
        </div>
        <input
          required
          name="phone"
          onChange={onChangeHandler}
          value={data.phone}
          type="text"
          placeholder="Phone"
        />

        <div className="payment-methods">
          <p className="payment-methods-title">Payment method</p>
          <label className={`payment-option ${paymentMethod === 'COD' ? 'active' : ''}`}>
            <input
              type="radio"
              name="paymentMethod"
              value="COD"
              checked={paymentMethod === 'COD'}
              onChange={() => setPaymentMethod('COD')}
            />
            <div>
              <strong>Cash on delivery</strong>
              <span>Pay when your order arrives at the door.</span>
            </div>
          </label>

          <label
            className={`payment-option ${paymentMethod === 'CARD' ? 'active' : ''} ${
              !paymentConfig.paymentMethods.card ? 'disabled' : ''
            }`}
          >
            <input
              type="radio"
              name="paymentMethod"
              value="CARD"
              checked={paymentMethod === 'CARD'}
              onChange={() => setPaymentMethod('CARD')}
              disabled={!paymentConfig.paymentMethods.card}
            />
            <div>
              <strong>Card payment</strong>
              <span>
                {paymentConfig.paymentMethods.card
                  ? 'Complete payment securely through Stripe checkout.'
                  : 'Card checkout is not configured yet for this deployment.'}
              </span>
            </div>
          </label>

          <label
            className={`payment-option ${paymentMethod === 'CHAPA' ? 'active' : ''} ${
              !paymentConfig.paymentMethods.chapa ? 'disabled' : ''
            }`}
          >
            <input
              type="radio"
              name="paymentMethod"
              value="CHAPA"
              checked={paymentMethod === 'CHAPA'}
              onChange={() => setPaymentMethod('CHAPA')}
              disabled={!paymentConfig.paymentMethods.chapa}
            />
            <div>
              <strong>Chapa checkout</strong>
              <span>
                {paymentConfig.paymentMethods.chapa
                  ? 'Pay securely with Chapa and return here when it is done.'
                  : 'Chapa checkout is not configured yet for this deployment.'}
              </span>
            </div>
          </label>
        </div>
      </div>

      <div className="place-order-right">
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
          <button type="submit">
            {paymentMethod === 'COD' ? 'Place order' : 'Proceed to payment'}
          </button>
        </div>
      </div>
    </form>
  )
}

export default PlaceOrder

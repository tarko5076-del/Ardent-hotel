import React, { useContext, useEffect, useState } from 'react'
import './MyOrders.css'
import axios from 'axios'
import { assets } from './../../assets/assets'
import { StoreContext } from './../../components/context/StoreContext'

const MyOrders = () => {
  const { refreshCatalog, token, url } = useContext(StoreContext)
  const [data, setData] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [feedbackMessage, setFeedbackMessage] = useState('')
  const [cancellingOrderId, setCancellingOrderId] = useState('')

  const fetchOrders = async () => {
    if (!token) {
      return
    }

    try {
      setIsLoading(true)
      const response = await axios.get(url + '/api/order/mine', {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })

      if (response.data.success) {
        setData(response.data.data)
        setFeedbackMessage('')
      }
    } catch (error) {
      setFeedbackMessage(
        error.response?.data?.message || 'Unable to fetch your orders right now.'
      )
    } finally {
      setIsLoading(false)
    }
  }

  const canCancelOrder = (order) =>
    order.status === 'Food Processing' && !order.payment

  const cancelOrder = async (orderId) => {
    try {
      setCancellingOrderId(orderId)
      const response = await axios.post(
        `${url}/api/order/${orderId}/cancel`,
        {},
        {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        }
      )

      if (response.data.success) {
        setFeedbackMessage(response.data.message)
        await Promise.all([fetchOrders(), refreshCatalog()])
      } else {
        setFeedbackMessage(response.data.message || 'Unable to cancel that order.')
      }
    } catch (error) {
      setFeedbackMessage(
        error.response?.data?.message || 'Unable to cancel that order.'
      )
    } finally {
      setCancellingOrderId('')
    }
  }

  useEffect(() => {
    if (token) {
      fetchOrders()
    }
  }, [token])

  return (
    <div className="my-orders">
      <div className="my-orders-copy">
        <span className="eyebrow">Order tracking</span>
        <h2 className="section-title">Your recent orders</h2>
        <p>
          See what you ordered, when the order was placed, whether payment was
          completed, and the current delivery status in one clean view.
        </p>
      </div>
      {feedbackMessage ? (
        <div className="my-orders-message">{feedbackMessage}</div>
      ) : null}
      <div className="container">
        {isLoading ? (
          <div className="my-orders-empty">
            <h3>Loading your orders...</h3>
            <p>We are refreshing the latest status updates for your deliveries.</p>
          </div>
        ) : !data.length ? (
          <div className="my-orders-empty">
            <h3>No orders yet.</h3>
            <p>Your completed and active deliveries will appear here once you checkout.</p>
          </div>
        ) : (
          data.map((order) => (
            <div key={order._id} className="my-orders-order">
              <img src={assets.parcel_icon} alt="Parcel" />
              <div className="my-orders-order-summary">
                <p>
                  {order.items.map((item, itemIndex) => {
                    if (itemIndex === order.items.length - 1) {
                      return item.name + ' x ' + item.quantity
                    }
                    return item.name + ' x ' + item.quantity + ', '
                  })}
                </p>
                <span>
                  {order.orderNumber} - Ordered on{' '}
                  {new Date(order.order_date).toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                  })}
                </span>
              </div>
              <p>${Number(order.amount).toFixed(2)}</p>
              <p>Items: {order.items.length}</p>
              <div className="my-orders-statuses">
                <p>
                  <span>&#x25cf;</span> <b>{order.status}</b>
                </p>
                <small className={order.payment ? 'payment-badge paid' : 'payment-badge unpaid'}>
                  {order.payment ? 'Paid' : order.payment_method || 'Unpaid'}
                </small>
              </div>
              <div className="my-orders-actions">
                <button onClick={fetchOrders}>Refresh status</button>
                {canCancelOrder(order) ? (
                  <button
                    onClick={() => cancelOrder(order._id)}
                    className="cancel-order-button"
                    disabled={cancellingOrderId === order._id}
                  >
                    {cancellingOrderId === order._id ? 'Cancelling...' : 'Cancel order'}
                  </button>
                ) : null}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  )
}

export default MyOrders

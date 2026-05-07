import React, { useContext, useEffect, useState } from 'react'
import './Orders.css'
import axios from 'axios'
import { toast } from 'react-toastify'
import { assets } from '../../assets/assets'
import { AdminContext } from '../../context/AdminContext'

const Orders = () => {
  const { getAuthConfig, url } = useContext(AdminContext)
  const [orders, setOrders] = useState([])

  const fetchAllOrders = async () => {
    try {
      const response = await axios.get(url + '/api/order/list', getAuthConfig())
      if (response.data.success) {
        setOrders(response.data.data)
      } else {
        toast.error('Unable to fetch orders.')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to fetch orders.')
    }
  }

  const statusHandler = async (event, orderId) => {
    try {
      const response = await axios.post(
        url + '/api/order/status',
        {
          orderId,
          status: event.target.value,
        },
        getAuthConfig()
      )
      if (response.data.success) {
        await fetchAllOrders()
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to update order status.')
    }
  }

  useEffect(() => {
    fetchAllOrders()
  }, [])

  const orderIsLocked = (order) =>
    order.status === 'Delivered' || order.status === 'Cancelled'

  return (
    <section className="orders-page">
      <div className="admin-heading">
        <span className="admin-eyebrow">Fulfillment desk</span>
        <h1>Monitor active guest orders</h1>
        <p>
          View customer details, delivery destinations, item counts, and update
          order progress in real time.
        </p>
      </div>

      <div className="orders-summary admin-card">
        <div>
          <strong>{orders.length}</strong>
          <span>Total orders</span>
        </div>
        <div>
          <strong>Live</strong>
          <span>Status updates enabled</span>
        </div>
      </div>

      <div className="order-list">
        {orders.map((order) => (
          <article key={order._id} className="order-item admin-card">
            <img src={assets.parcel_icon} alt="Parcel" />
            <div>
              <p className="order-item-food">
                {order.items.map((item, index) => {
                  if (index === order.items.length - 1) {
                    return item.name + ' x ' + item.quantity
                  }
                  return item.name + ' x ' + item.quantity + ', '
                })}
              </p>
              <p className="order-item-name">
                {order.address.firstName + ' ' + order.address.lastName}
              </p>
              <p className="order-item-email">{order.customer_email}</p>
              <div className="order-item-address">
                <p>{order.address.street}</p>
                <p>
                  {order.address.city +
                    ', ' +
                    order.address.state +
                    ', ' +
                    order.address.country +
                    ', ' +
                    order.address.zipcode}
                </p>
              </div>
              <p className="order-item-phone">{order.address.phone}</p>
              {order.status === 'Cancelled' ? (
                <p className="order-item-note">
                  Cancelled before dispatch. Stock has already been restored.
                </p>
              ) : null}
            </div>
            <div className="order-meta">
              <p>{order.orderNumber}</p>
              <p>Items: {order.items.length}</p>
              <p>${Number(order.amount).toFixed(2)}</p>
            </div>
            <select
              onChange={(event) => statusHandler(event, order._id)}
              value={order.status}
              disabled={orderIsLocked(order)}
            >
              <option value="Food Processing">Food Processing</option>
              <option value="Out for delivery">Out for delivery</option>
              <option value="Delivered">Delivered</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </article>
        ))}
      </div>
    </section>
  )
}

export default Orders

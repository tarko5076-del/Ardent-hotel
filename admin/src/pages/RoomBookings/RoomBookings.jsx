import React, { useContext, useEffect, useMemo, useState } from 'react'
import './RoomBookings.css'
import axios from 'axios'
import { toast } from 'react-toastify'
import { assets } from '../../assets/assets'
import { AdminContext } from '../../context/AdminContext'

const statusOptions = ['Pending', 'Confirmed', 'Completed', 'Cancelled']

const formatDate = (value) =>
  new Date(value).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })

const RoomBookings = () => {
  const { getAuthConfig, url } = useContext(AdminContext)
  const [bookings, setBookings] = useState([])
  const [draftNotes, setDraftNotes] = useState({})
  const [savingBookingId, setSavingBookingId] = useState('')

  const summary = useMemo(() => {
    const pending = bookings.filter((booking) => booking.status === 'Pending').length
    const confirmed = bookings.filter((booking) => booking.status === 'Confirmed').length
    return {
      total: bookings.length,
      pending,
      confirmed,
    }
  }, [bookings])

  const fetchBookings = async () => {
    try {
      const response = await axios.get(`${url}/api/rooms/bookings`, getAuthConfig())

      if (response.data.success) {
        const nextBookings = response.data.data || []
        setBookings(nextBookings)
        setDraftNotes(
          nextBookings.reduce((accumulator, booking) => {
            accumulator[booking._id] = booking.admin_note || ''
            return accumulator
          }, {})
        )
      } else {
        toast.error('Unable to fetch room bookings.')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to fetch room bookings.')
    }
  }

  const updateStatus = async (bookingId, status) => {
    try {
      setSavingBookingId(bookingId)
      const response = await axios.patch(
        `${url}/api/rooms/bookings/${bookingId}/status`,
        {
          status,
          adminNote: draftNotes[bookingId] || '',
        },
        getAuthConfig()
      )

      if (response.data.success) {
        toast.success('Room booking updated.')
        await fetchBookings()
      } else {
        toast.error(response.data.message || 'Unable to update room booking.')
      }
    } catch (error) {
      toast.error(
        error.response?.data?.message || 'Unable to update room booking.'
      )
    } finally {
      setSavingBookingId('')
    }
  }

  useEffect(() => {
    fetchBookings()
  }, [])

  return (
    <section className="room-bookings-page">
      <div className="admin-heading">
        <span className="admin-eyebrow">Stay reservations</span>
        <h1>Manage hotel room bookings</h1>
        <p>
          Review guest reservations, keep room occupancy accurate, and update
          booking status with notes for your front desk team.
        </p>
      </div>

      <div className="room-bookings-summary admin-card">
        <div>
          <strong>{summary.total}</strong>
          <span>Total bookings</span>
        </div>
        <div>
          <strong>{summary.pending}</strong>
          <span>Pending review</span>
        </div>
        <div>
          <strong>{summary.confirmed}</strong>
          <span>Confirmed stays</span>
        </div>
      </div>

      <div className="room-bookings-list">
        {bookings.map((booking) => (
          <article key={booking._id} className="room-booking-item admin-card">
            <div className="room-booking-lead">
              <img src={assets.parcel_icon} alt="Room booking" />
              <div>
                <p className="room-booking-title">{booking.room?.name}</p>
                <p className="room-booking-subtitle">
                  Room {booking.room?.room_number} • {booking.room?.room_type}
                </p>
              </div>
            </div>

            <div className="room-booking-details">
              <p className="room-booking-customer">{booking.customer_name}</p>
              <p>{booking.customer_email}</p>
              <p>Phone: {booking.contact_phone || 'Not provided'}</p>
              <p>
                Stay: {formatDate(booking.check_in)} to {formatDate(booking.check_out)}
              </p>
              <p>
                Guests: {booking.guests} • Total: $
                {Number(booking.total_amount).toFixed(2)}
              </p>
              {booking.special_request ? (
                <p className="room-booking-note">
                  Request: {booking.special_request}
                </p>
              ) : null}
            </div>

            <div className="room-booking-controls">
              <select
                value={booking.status}
                onChange={(event) => updateStatus(booking._id, event.target.value)}
                disabled={savingBookingId === booking._id}
              >
                {statusOptions.map((status) => (
                  <option key={status} value={status}>
                    {status}
                  </option>
                ))}
              </select>

              <textarea
                value={draftNotes[booking._id] || ''}
                onChange={(event) =>
                  setDraftNotes((previousValue) => ({
                    ...previousValue,
                    [booking._id]: event.target.value,
                  }))
                }
                placeholder="Add front desk note"
              />

              <button
                type="button"
                onClick={() => updateStatus(booking._id, booking.status)}
                disabled={savingBookingId === booking._id}
              >
                {savingBookingId === booking._id ? 'Saving...' : 'Save note'}
              </button>
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}

export default RoomBookings

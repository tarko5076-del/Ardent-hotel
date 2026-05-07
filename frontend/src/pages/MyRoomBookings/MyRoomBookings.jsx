import axios from 'axios'
import React, { useContext, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { assets } from '../../assets/assets'
import { StoreContext } from '../../components/context/StoreContext'
import './MyRoomBookings.css'

const cancellableStatuses = ['Pending', 'Confirmed']

const countNights = (checkIn, checkOut) => {
  const start = new Date(checkIn)
  const end = new Date(checkOut)
  const diff = end.getTime() - start.getTime()
  return Math.max(1, Math.round(diff / (1000 * 60 * 60 * 24)))
}

const formatDate = (value) =>
  new Date(value).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })

const MyRoomBookings = () => {
  const { authReady, token, url } = useContext(StoreContext)
  const [bookings, setBookings] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [feedbackMessage, setFeedbackMessage] = useState('')
  const [cancellingBookingId, setCancellingBookingId] = useState('')

  const summary = useMemo(() => {
    const active = bookings.filter((booking) =>
      ['Pending', 'Confirmed'].includes(booking.status)
    ).length
    const completed = bookings.filter((booking) => booking.status === 'Completed').length
    return {
      total: bookings.length,
      active,
      completed,
    }
  }, [bookings])

  const fetchBookings = async () => {
    if (!token) {
      setBookings([])
      return
    }

    try {
      setIsLoading(true)
      const response = await axios.get(`${url}/api/rooms/bookings/mine`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })

      if (response.data.success) {
        setBookings(response.data.data || [])
        setFeedbackMessage('')
      } else {
        setFeedbackMessage('Unable to load your room bookings right now.')
      }
    } catch (error) {
      setFeedbackMessage(
        error.response?.data?.message || 'Unable to load your room bookings right now.'
      )
    } finally {
      setIsLoading(false)
    }
  }

  const cancelBooking = async (bookingId) => {
    try {
      setCancellingBookingId(bookingId)
      const response = await axios.post(
        `${url}/api/rooms/bookings/${bookingId}/cancel`,
        {},
        {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        }
      )

      if (response.data.success) {
        setFeedbackMessage(response.data.message || 'Booking cancelled successfully.')
        await fetchBookings()
      } else {
        setFeedbackMessage(response.data.message || 'Unable to cancel this booking.')
      }
    } catch (error) {
      setFeedbackMessage(
        error.response?.data?.message || 'Unable to cancel this booking.'
      )
    } finally {
      setCancellingBookingId('')
    }
  }

  useEffect(() => {
    if (token) {
      fetchBookings()
    }
  }, [token])

  if (!authReady) {
    return (
      <section className="my-room-bookings">
        <div className="my-room-bookings-empty">Loading your bookings...</div>
      </section>
    )
  }

  if (!token) {
    return (
      <section className="my-room-bookings">
        <div className="my-room-bookings-copy">
          <span className="eyebrow">Stay management</span>
          <h1 className="section-title">Your room bookings live here.</h1>
          <p>
            Sign in to review upcoming stays, booking status, and any room
            reservations you have already placed.
          </p>
        </div>
        <div className="my-room-bookings-empty">
          <h3>Please sign in first.</h3>
          <p>Your room reservations are only available after login.</p>
        </div>
      </section>
    )
  }

  return (
    <section className="my-room-bookings">
      <div className="my-room-bookings-copy">
        <span className="eyebrow">Stay management</span>
        <h1 className="section-title">Track every hotel stay you reserve.</h1>
        <p>
          Review your room details, arrival dates, guest counts, and booking
          status without leaving the delivery app.
        </p>
      </div>

      <div className="my-room-bookings-summary">
        <article className="panel">
          <strong>{summary.total}</strong>
          <span>Total bookings</span>
        </article>
        <article className="panel">
          <strong>{summary.active}</strong>
          <span>Upcoming stays</span>
        </article>
        <article className="panel">
          <strong>{summary.completed}</strong>
          <span>Completed stays</span>
        </article>
      </div>

      {feedbackMessage ? (
        <div className="my-room-bookings-feedback">{feedbackMessage}</div>
      ) : null}

      {isLoading ? (
        <div className="my-room-bookings-empty">
          <h3>Loading your room bookings...</h3>
          <p>We are checking the latest reservation details for you.</p>
        </div>
      ) : !bookings.length ? (
        <div className="my-room-bookings-empty">
          <h3>No room bookings yet.</h3>
          <p>Once you reserve a stay, it will appear here with live status updates.</p>
          <Link to="/rooms" className="action-button">
            Browse rooms
          </Link>
        </div>
      ) : (
        <div className="my-room-bookings-list">
          {bookings.map((booking) => {
            const nights = countNights(booking.check_in, booking.check_out)
            const canCancel = cancellableStatuses.includes(booking.status)

            return (
              <article key={booking._id} className="my-room-booking-card">
                <div className="my-room-booking-room">
                  <img src={assets.parcel_icon} alt="Room booking" />
                  <div>
                    <h3>{booking.room?.name || 'Hotel room'}</h3>
                    <p>
                      Room {booking.room?.room_number} • {booking.room?.room_type}
                    </p>
                  </div>
                </div>

                <div className="my-room-booking-grid">
                  <div>
                    <span>Check-in</span>
                    <strong>{formatDate(booking.check_in)}</strong>
                  </div>
                  <div>
                    <span>Check-out</span>
                    <strong>{formatDate(booking.check_out)}</strong>
                  </div>
                  <div>
                    <span>Guests</span>
                    <strong>{booking.guests}</strong>
                  </div>
                  <div>
                    <span>Nights</span>
                    <strong>{nights}</strong>
                  </div>
                  <div>
                    <span>Total amount</span>
                    <strong>${Number(booking.total_amount).toFixed(2)}</strong>
                  </div>
                  <div>
                    <span>Status</span>
                    <strong className={`booking-status ${booking.status.toLowerCase()}`}>
                      {booking.status}
                    </strong>
                  </div>
                </div>

                <div className="my-room-booking-meta">
                  <p>Booked on {formatDate(booking.created_at)}</p>
                  {booking.contact_phone ? <p>Phone: {booking.contact_phone}</p> : null}
                  {booking.admin_note ? <p>Admin note: {booking.admin_note}</p> : null}
                </div>

                <div className="my-room-booking-actions">
                  <button type="button" className="ghost-button" onClick={fetchBookings}>
                    Refresh booking
                  </button>
                  {canCancel ? (
                    <button
                      type="button"
                      className="cancel-booking-button"
                      onClick={() => cancelBooking(booking._id)}
                      disabled={cancellingBookingId === booking._id}
                    >
                      {cancellingBookingId === booking._id
                        ? 'Cancelling...'
                        : 'Cancel booking'}
                    </button>
                  ) : null}
                </div>
              </article>
            )
          })}
        </div>
      )}
    </section>
  )
}

export default MyRoomBookings

import axios from 'axios'
import React, { useContext, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { StoreContext } from '../../components/context/StoreContext'
import './Rooms.css'

const getTodayDate = () => new Date().toISOString().slice(0, 10)

const getTomorrowDate = () => {
  const nextDay = new Date()
  nextDay.setDate(nextDay.getDate() + 1)
  return nextDay.toISOString().slice(0, 10)
}

const Rooms = () => {
  const { token, url, user } = useContext(StoreContext)
  const navigate = useNavigate()
  const [searchTerm, setSearchTerm] = useState('')
  const [roomType, setRoomType] = useState('All')
  const [guests, setGuests] = useState(1)
  const [checkIn, setCheckIn] = useState(getTodayDate())
  const [checkOut, setCheckOut] = useState(getTomorrowDate())
  const [rooms, setRooms] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [bookingRoomId, setBookingRoomId] = useState('')
  const [feedbackMessage, setFeedbackMessage] = useState('')

  const roomTypes = useMemo(
    () => ['All', ...new Set(rooms.map((room) => room.room_type))],
    [rooms]
  )

  const fetchRooms = async () => {
    try {
      setIsLoading(true)
      const response = await axios.get(`${url}/api/rooms/list`, {
        params: {
          search: searchTerm.trim() || undefined,
          roomType: roomType === 'All' ? undefined : roomType,
          guests,
          checkIn,
          checkOut,
          activeOnly: 'true',
        },
      })

      if (response.data.success) {
        setRooms(response.data.data || [])
        setFeedbackMessage('')
      } else {
        setRooms([])
        setFeedbackMessage('Unable to load room listings right now.')
      }
    } catch (error) {
      setRooms([])
      setFeedbackMessage('Unable to load room listings right now.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    const timeoutId = window.setTimeout(fetchRooms, 240)
    return () => window.clearTimeout(timeoutId)
  }, [searchTerm, roomType, guests, checkIn, checkOut])

  const bookRoom = async (room) => {
    if (!token) {
      window.alert('Please sign in before booking a room.')
      return
    }

    if (!checkIn || !checkOut) {
      window.alert('Please select both check-in and check-out dates.')
      return
    }

    try {
      setBookingRoomId(room._id)
      const response = await axios.post(
        `${url}/api/rooms/book`,
        {
          roomId: room._id,
          checkIn,
          checkOut,
          guests,
          contactPhone: user?.phone || '',
          specialRequest: '',
        },
        {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        }
      )

      if (response.data.success) {
        window.alert('Room booked successfully.')
        navigate('/my-room-bookings')
        return
      }

      window.alert(response.data.message || 'Unable to place your booking.')
    } catch (error) {
      window.alert(
        error.response?.data?.message || 'Unable to place your booking.'
      )
    } finally {
      setBookingRoomId('')
    }
  }

  return (
    <section className="rooms-page">
      <div className="rooms-copy">
        <span className="eyebrow">Hotel rooms</span>
        <h1 className="section-title">Book your room at Ardent Hotel</h1>
        <p>
          Browse available room categories, pick your travel dates, and reserve
          your stay directly from the same app you use for food delivery.
        </p>
      </div>

      <div className="rooms-filter panel">
        <label>
          <span>Search room</span>
          <input
            type="text"
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
            placeholder="Deluxe, suite, panorama..."
          />
        </label>
        <label>
          <span>Check-in</span>
          <input
            type="date"
            value={checkIn}
            min={getTodayDate()}
            onChange={(event) => setCheckIn(event.target.value)}
          />
        </label>
        <label>
          <span>Check-out</span>
          <input
            type="date"
            value={checkOut}
            min={checkIn || getTodayDate()}
            onChange={(event) => setCheckOut(event.target.value)}
          />
        </label>
        <label>
          <span>Guests</span>
          <input
            type="number"
            min="1"
            value={guests}
            onChange={(event) =>
              setGuests(Math.max(1, Number(event.target.value || 1)))
            }
          />
        </label>
        <label>
          <span>Room type</span>
          <select
            value={roomType}
            onChange={(event) => setRoomType(event.target.value)}
          >
            {roomTypes.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="rooms-status">
        <p>
          {isLoading
            ? 'Checking room availability...'
            : `${rooms.length} room option${rooms.length === 1 ? '' : 's'} available`}
        </p>
        <span>
          Stay dates: {checkIn || '--'} to {checkOut || '--'}
        </span>
      </div>

      {feedbackMessage ? (
        <div className="rooms-feedback">{feedbackMessage}</div>
      ) : null}

      {!isLoading && !rooms.length ? (
        <div className="rooms-empty">
          <h3>No rooms matched your dates or filters.</h3>
          <p>Try different dates, fewer guests, or a broader room type.</p>
        </div>
      ) : (
        <div className="rooms-grid">
          {rooms.map((room) => (
            <article key={room._id} className="room-card">
              <div className="room-card-image">
                {room.image ? (
                  <img src={room.image} alt={room.name} />
                ) : (
                  <div className="room-card-placeholder">
                    <span>{room.room_type}</span>
                  </div>
                )}
                <span className="room-badge">Room {room.room_number}</span>
              </div>
              <div className="room-card-content">
                <div className="room-card-head">
                  <h3>{room.name}</h3>
                  <strong>${Number(room.price_per_night).toFixed(2)} / night</strong>
                </div>
                <p>{room.description}</p>
                <div className="room-meta">
                  <span>Type: {room.room_type}</span>
                  <span>Capacity: {room.capacity} guest(s)</span>
                </div>
                <div className="room-amenities">
                  {(room.amenities || []).slice(0, 4).map((amenity) => (
                    <small key={amenity}>{amenity}</small>
                  ))}
                </div>
                <button
                  className="action-button"
                  type="button"
                  onClick={() => bookRoom(room)}
                  disabled={bookingRoomId === room._id}
                >
                  {bookingRoomId === room._id ? 'Booking...' : 'Book this room'}
                </button>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}

export default Rooms

import React, { useEffect, useMemo, useRef, useState } from 'react'
import './Footer.css'
import { assets } from '../../assets/assets'

const TRACKER_STORAGE_KEY = 'food-delivery-location-tracker'

const parseNumericEnv = (value, fallback) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

const hotelLocation = {
  label: import.meta.env.VITE_HOTEL_NAME || 'Ardent Hotel',
  address:
    import.meta.env.VITE_HOTEL_ADDRESS || 'Ardent Hotel, Nairobi CBD, Kenya',
  lat: parseNumericEnv(import.meta.env.VITE_HOTEL_LAT, -1.286389),
  lng: parseNumericEnv(import.meta.env.VITE_HOTEL_LNG, 36.817223),
  arrivalRadiusMeters: Math.max(
    30,
    parseNumericEnv(import.meta.env.VITE_HOTEL_ARRIVAL_RADIUS_METERS, 120)
  ),
}

const readStoredTracker = () => {
  try {
    return (
      JSON.parse(localStorage.getItem(TRACKER_STORAGE_KEY)) || {
        location: null,
      }
    )
  } catch (error) {
    return {
      location: null,
    }
  }
}

const clamp = (value, min, max) => Math.min(max, Math.max(min, value))

const getDistanceKm = (origin, destination) => {
  const toRadians = (value) => (value * Math.PI) / 180
  const earthRadiusKm = 6371
  const deltaLat = toRadians(destination.lat - origin.lat)
  const deltaLng = toRadians(destination.lng - origin.lng)
  const a =
    Math.sin(deltaLat / 2) * Math.sin(deltaLat / 2) +
    Math.cos(toRadians(origin.lat)) *
      Math.cos(toRadians(destination.lat)) *
      Math.sin(deltaLng / 2) *
      Math.sin(deltaLng / 2)

  return earthRadiusKm * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)))
}

const getBearingDegrees = (origin, destination) => {
  const toRadians = (value) => (value * Math.PI) / 180
  const toDegrees = (value) => (value * 180) / Math.PI
  const originLat = toRadians(origin.lat)
  const destinationLat = toRadians(destination.lat)
  const deltaLng = toRadians(destination.lng - origin.lng)
  const y = Math.sin(deltaLng) * Math.cos(destinationLat)
  const x =
    Math.cos(originLat) * Math.sin(destinationLat) -
    Math.sin(originLat) * Math.cos(destinationLat) * Math.cos(deltaLng)
  const bearing = toDegrees(Math.atan2(y, x))
  return (bearing + 360) % 360
}

const getDirectionLabel = (bearing) => {
  const directions = [
    'North',
    'North East',
    'East',
    'South East',
    'South',
    'South West',
    'West',
    'North West',
  ]

  return directions[Math.round(bearing / 45) % 8]
}

const formatDistance = (distanceKm) => {
  if (distanceKm == null) {
    return '--'
  }

  if (distanceKm < 1) {
    return `${Math.round(distanceKm * 1000)} m`
  }

  return `${distanceKm.toFixed(distanceKm < 10 ? 1 : 0)} km`
}

const buildRoutePreview = (bearing, distanceKm) => {
  const canvasWidth = 260
  const canvasHeight = 170
  const hotelPoint = { x: 198, y: 44 }

  if (bearing == null || distanceKm == null) {
    const userPoint = { x: 54, y: 134 }
    const path = `M ${userPoint.x} ${userPoint.y} Q 126 116 ${hotelPoint.x} ${hotelPoint.y}`
    return { canvasHeight, canvasWidth, hotelPoint, path, userPoint }
  }

  const distanceFactor = Math.min(distanceKm / 25, 1)
  const radius = 46 + distanceFactor * 56
  const angleRadians = (bearing * Math.PI) / 180
  const rawUserX = hotelPoint.x - Math.sin(angleRadians) * radius
  const rawUserY = hotelPoint.y + Math.cos(angleRadians) * radius
  const userPoint = {
    x: clamp(rawUserX, 18, canvasWidth - 18),
    y: clamp(rawUserY, 18, canvasHeight - 18),
  }
  const curveX = (userPoint.x + hotelPoint.x) / 2 + 18
  const curveY = (userPoint.y + hotelPoint.y) / 2 - 20
  const path = `M ${userPoint.x} ${userPoint.y} Q ${curveX} ${curveY} ${hotelPoint.x} ${hotelPoint.y}`

  return { canvasHeight, canvasWidth, hotelPoint, path, userPoint }
}

const getInitialTrackerCopy = (storedLocation) => {
  if (!storedLocation) {
    return {
      message:
        'Use live location and we will keep your route updated until you arrive at the hotel entrance.',
      state: 'idle',
    }
  }

  const distanceKm = getDistanceKm(storedLocation, hotelLocation)
  if (distanceKm * 1000 <= hotelLocation.arrivalRadiusMeters) {
    return {
      message: `You already look close enough to ${hotelLocation.label}. Start live tracking if you want to verify again.`,
      state: 'arrived',
    }
  }

  return {
    message:
      'Last known route loaded. Start live tracking for updated directions from your current position.',
    state: 'ready',
  }
}

const trackerStateLabels = {
  arrived: 'Arrived',
  error: 'Needs access',
  idle: 'Ready',
  locating: 'Finding you',
  ready: 'Route ready',
  tracking: 'Live',
}

const Footer = () => {
  const [storedTracker] = useState(() => readStoredTracker())
  const initialTrackerCopy = getInitialTrackerCopy(storedTracker.location)
  const watchIdRef = useRef(null)
  const [currentLocation, setCurrentLocation] = useState(
    storedTracker.location || null
  )
  const [trackerMessage, setTrackerMessage] = useState(
    initialTrackerCopy.message
  )
  const [trackerState, setTrackerState] = useState(initialTrackerCopy.state)
  const [isWatching, setIsWatching] = useState(false)

  useEffect(() => {
    localStorage.setItem(
      TRACKER_STORAGE_KEY,
      JSON.stringify({
        location: currentLocation,
      })
    )
  }, [currentLocation])

  const clearLocationWatch = () => {
    if (watchIdRef.current != null && navigator.geolocation) {
      navigator.geolocation.clearWatch(watchIdRef.current)
      watchIdRef.current = null
    }

    setIsWatching(false)
  }

  useEffect(
    () => () => {
      if (watchIdRef.current != null && navigator.geolocation) {
        navigator.geolocation.clearWatch(watchIdRef.current)
      }
    },
    []
  )

  const applyLocationUpdate = (position, mode) => {
    const nextLocation = {
      accuracy: Math.round(position.coords.accuracy || 0),
      lat: Number(position.coords.latitude.toFixed(5)),
      lng: Number(position.coords.longitude.toFixed(5)),
      updatedAt: new Date().toISOString(),
    }

    const nextDistanceKm = getDistanceKm(nextLocation, hotelLocation)
    const nextDirectionLabel = getDirectionLabel(
      getBearingDegrees(nextLocation, hotelLocation)
    )

    setCurrentLocation(nextLocation)

    if (nextDistanceKm * 1000 <= hotelLocation.arrivalRadiusMeters) {
      clearLocationWatch()
      setTrackerState('arrived')
      setTrackerMessage(
        `You have arrived at ${hotelLocation.label}. You can still open Google Maps if you want the exact entrance view.`
      )
      return
    }

    setTrackerState(mode === 'live' ? 'tracking' : 'ready')
    setTrackerMessage(
      mode === 'live'
        ? `Live tracking is on. Head ${nextDirectionLabel} for about ${formatDistance(nextDistanceKm)} to reach ${hotelLocation.label}.`
        : `Location updated. Head ${nextDirectionLabel} for about ${formatDistance(nextDistanceKm)} to reach ${hotelLocation.label}.`
    )
  }

  const handleLocationError = (error) => {
    clearLocationWatch()
    setTrackerState('error')
    setTrackerMessage(
      error.code === 1
        ? 'Location permission was denied. Allow location access to get live directions to the hotel.'
        : 'Unable to get your live location right now. Please try again in a moment.'
    )
  }

  const startLiveTracking = () => {
    if (!navigator.geolocation) {
      setTrackerState('error')
      setTrackerMessage('This browser cannot provide your current location.')
      return
    }

    clearLocationWatch()
    setIsWatching(true)
    setTrackerState('locating')
    setTrackerMessage('Getting your live location and drawing the route...')

    watchIdRef.current = navigator.geolocation.watchPosition(
      (position) => applyLocationUpdate(position, 'live'),
      handleLocationError,
      {
        enableHighAccuracy: true,
        maximumAge: 5000,
        timeout: 10000,
      }
    )
  }

  const stopLiveTracking = () => {
    clearLocationWatch()

    if (currentLocation) {
      setTrackerState('ready')
      setTrackerMessage(
        'Live tracking paused. Your last known route is still shown below.'
      )
      return
    }

    setTrackerState('idle')
    setTrackerMessage(
      'Use live location and we will keep your route updated until you arrive at the hotel entrance.'
    )
  }

  const distanceKm =
    currentLocation != null ? getDistanceKm(currentLocation, hotelLocation) : null
  const bearing =
    currentLocation != null
      ? getBearingDegrees(currentLocation, hotelLocation)
      : null
  const directionLabel = bearing == null ? '--' : getDirectionLabel(bearing)
  const distanceLabel = formatDistance(distanceKm)
  const locationAccuracy =
    currentLocation?.accuracy != null ? `${currentLocation.accuracy} m` : '--'
  const arrivalRadiusLabel = `${hotelLocation.arrivalRadiusMeters} m`
  const routePreview = useMemo(
    () => buildRoutePreview(bearing, distanceKm),
    [bearing, distanceKm]
  )

  const routeLink = currentLocation
    ? `https://www.google.com/maps/dir/?api=1&origin=${currentLocation.lat},${currentLocation.lng}&destination=${hotelLocation.lat},${hotelLocation.lng}`
    : `https://www.google.com/maps/search/?api=1&query=${hotelLocation.lat},${hotelLocation.lng}`

  const locationUpdatedTime = currentLocation?.updatedAt
    ? new Date(currentLocation.updatedAt).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
      })
    : '--'

  const primaryActionLabel = isWatching
    ? 'Stop live tracking'
    : currentLocation
      ? 'Start live tracking'
      : 'Use my live location'
  const badgeLabel = trackerStateLabels[trackerState] || trackerStateLabels.idle

  return (
    <footer className="footer" id="footer">
      <div className="footer-tracker panel" id="location-tracker">
        <div className="footer-tracker-copy">
          <span className="eyebrow">Hotel navigation</span>
          <h3 className="footer-route-title">Get guided straight to Ardent Hotel</h3>
          <p>
            Tap one button to use your live location, see how far away you are,
            and open turn by turn directions in Google Maps whenever you need
            them.
          </p>
        </div>

        <div className="footer-tracker-card">
          <div className="footer-destination-card">
            <div className="footer-destination-copy">
              <span className="footer-destination-label">Destination</span>
              <strong>{hotelLocation.label}</strong>
              <p>{hotelLocation.address}</p>
            </div>
            <span className={`footer-tracker-badge ${trackerState}`}>
              {badgeLabel}
            </span>
          </div>

          <div className="footer-tracker-actions">
            <button
              type="button"
              className={isWatching ? 'is-stop' : ''}
              onClick={isWatching ? stopLiveTracking : startLiveTracking}
            >
              {primaryActionLabel}
            </button>
            <a href={routeLink} target="_blank" rel="noreferrer">
              Open in Google Maps
            </a>
          </div>

          <div className="footer-route-visual">
            <svg
              viewBox={`0 0 ${routePreview.canvasWidth} ${routePreview.canvasHeight}`}
              aria-label="Route line to hotel"
            >
              <defs>
                <linearGradient
                  id="routeGradient"
                  x1="0%"
                  y1="0%"
                  x2="100%"
                  y2="0%"
                >
                  <stop offset="0%" stopColor="#f8c97c" />
                  <stop offset="100%" stopColor="#ef8b48" />
                </linearGradient>
              </defs>
              <path d={routePreview.path} className="route-line-glow" />
              <path d={routePreview.path} className="route-line-main" />
              <circle
                className="route-point route-point-user"
                cx={routePreview.userPoint.x}
                cy={routePreview.userPoint.y}
                r="7"
              />
              <circle
                className="route-point route-point-hotel"
                cx={routePreview.hotelPoint.x}
                cy={routePreview.hotelPoint.y}
                r="8"
              />
              <text
                className="route-label"
                x={routePreview.userPoint.x - 24}
                y={routePreview.userPoint.y + 22}
              >
                You
              </text>
              <text
                className="route-label"
                x={routePreview.hotelPoint.x - 40}
                y={routePreview.hotelPoint.y - 14}
              >
                Hotel
              </text>
            </svg>
          </div>

          <div className="footer-tracker-stats">
            <div>
              <strong>{distanceLabel}</strong>
              <span>Distance to hotel</span>
            </div>
            <div>
              <strong>{directionLabel}</strong>
              <span>Head toward</span>
            </div>
            <div>
              <strong>{locationAccuracy}</strong>
              <span>GPS accuracy</span>
            </div>
            <div>
              <strong>{locationUpdatedTime}</strong>
              <span>Last update</span>
            </div>
          </div>

          <p
            className={`footer-tracker-note ${
              trackerState === 'error' ? 'error' : ''
            }`}
          >
            {trackerMessage}
          </p>
          <small>
            Address: {hotelLocation.address} | Arrival zone: {arrivalRadiusLabel}
          </small>
        </div>
      </div>

      <div className="footer-content">
        <div className="footer-brand">
          <img src={assets.logo} alt="Ardent Hotel logo" />
          <div>
            <h2>Ardent Hotel</h2>
            <p>Hotel dining, room bookings, and clear arrival guidance in one place.</p>
          </div>
        </div>

        <div className="footer-contact">
          <a href="tel:+251 9-08-65-95-19">+1 (251) 9-08-65-95-19</a>
          <a href="support@ardenthotel.com">support@ardenthotel.com</a>
        </div>

        <div className="footer-social-icons">
          <img src={assets.facebook_icon} alt="Facebook" />
          <img src={assets.twitter_icon} alt="Twitter" />
          <img src={assets.linkedin_icon} alt="LinkedIn" />
        </div>
      </div>

      <p className="footer-copyright">Copyright 2026 Ardent Hotel.</p>
    </footer>
  )
}

export default Footer

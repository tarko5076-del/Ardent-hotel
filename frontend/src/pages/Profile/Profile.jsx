import React, { useContext, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { StoreContext } from '../../components/context/StoreContext'
import './Profile.css'

const emptyAddress = {
  firstName: '',
  lastName: '',
  email: '',
  street: '',
  city: '',
  state: '',
  zipcode: '',
  country: '',
  deliveryPhone: '',
}

const buildProfileForm = (user) => ({
  name: user?.name || '',
  phone: user?.phone || '',
  ...emptyAddress,
  ...(user?.defaultAddress
    ? {
        firstName: user.defaultAddress.firstName || '',
        lastName: user.defaultAddress.lastName || '',
        email: user.defaultAddress.email || '',
        street: user.defaultAddress.street || '',
        city: user.defaultAddress.city || '',
        state: user.defaultAddress.state || '',
        zipcode: user.defaultAddress.zipcode || '',
        country: user.defaultAddress.country || '',
        deliveryPhone: user.defaultAddress.phone || user.phone || '',
      }
    : {}),
})

const Profile = () => {
  const { authReady, token, updateProfile, user } = useContext(StoreContext)
  const navigate = useNavigate()
  const [formData, setFormData] = useState(buildProfileForm(user))
  const [isSaving, setIsSaving] = useState(false)
  const [feedbackMessage, setFeedbackMessage] = useState('')

  useEffect(() => {
    setFormData(buildProfileForm(user))
  }, [user])

  useEffect(() => {
    if (authReady && !token) {
      navigate('/')
    }
  }, [authReady, navigate, token])

  const onChangeHandler = (event) => {
    const { name, value } = event.target
    setFormData((previousValue) => ({
      ...previousValue,
      [name]: value,
    }))
  }

  const onSubmitHandler = async (event) => {
    event.preventDefault()
    setIsSaving(true)

    const result = await updateProfile({
      name: formData.name,
      phone: formData.phone,
      defaultAddress: {
        firstName: formData.firstName,
        lastName: formData.lastName,
        email: formData.email,
        street: formData.street,
        city: formData.city,
        state: formData.state,
        zipcode: formData.zipcode,
        country: formData.country,
        phone: formData.deliveryPhone,
      },
    })

    setFeedbackMessage(
      result.message || 'Your profile details were updated successfully.'
    )
    setIsSaving(false)
  }

  if (!authReady) {
    return (
      <section className="profile-page">
        <div className="profile-card">Loading your profile...</div>
      </section>
    )
  }

  if (!token) {
    return null
  }

  return (
    <section className="profile-page">
      <div className="profile-copy">
        <span className="eyebrow">Profile settings</span>
        <h1 className="section-title">Save your delivery details once.</h1>
        <p>
          Update your main profile, store a preferred delivery address, and let
          checkout reuse those details automatically the next time you order.
        </p>
      </div>

      {feedbackMessage ? (
        <div className="profile-feedback">{feedbackMessage}</div>
      ) : null}

      <form className="profile-card" onSubmit={onSubmitHandler}>
        <div className="profile-section">
          <div className="profile-section-copy">
            <h2>Account information</h2>
            <p>Your account name and phone number appear across the app.</p>
          </div>
          <div className="profile-grid two-columns">
            <label>
              <span>Full name</span>
              <input
                type="text"
                name="name"
                value={formData.name}
                onChange={onChangeHandler}
                required
              />
            </label>
            <label>
              <span>Account phone</span>
              <input
                type="text"
                name="phone"
                value={formData.phone}
                onChange={onChangeHandler}
              />
            </label>
            <label className="full-width">
              <span>Email address</span>
              <input type="email" value={user?.email || ''} readOnly />
            </label>
          </div>
        </div>

        <div className="profile-section">
          <div className="profile-section-copy">
            <h2>Default delivery details</h2>
            <p>
              These fields fill your checkout form automatically. Leave them
              blank if you prefer to enter delivery details manually each time.
            </p>
          </div>
          <div className="profile-grid two-columns">
            <label>
              <span>First name</span>
              <input
                type="text"
                name="firstName"
                value={formData.firstName}
                onChange={onChangeHandler}
              />
            </label>
            <label>
              <span>Last name</span>
              <input
                type="text"
                name="lastName"
                value={formData.lastName}
                onChange={onChangeHandler}
              />
            </label>
            <label className="full-width">
              <span>Delivery email</span>
              <input
                type="email"
                name="email"
                value={formData.email}
                onChange={onChangeHandler}
              />
            </label>
            <label className="full-width">
              <span>Street</span>
              <input
                type="text"
                name="street"
                value={formData.street}
                onChange={onChangeHandler}
              />
            </label>
            <label>
              <span>City</span>
              <input
                type="text"
                name="city"
                value={formData.city}
                onChange={onChangeHandler}
              />
            </label>
            <label>
              <span>State</span>
              <input
                type="text"
                name="state"
                value={formData.state}
                onChange={onChangeHandler}
              />
            </label>
            <label>
              <span>Zip code</span>
              <input
                type="text"
                name="zipcode"
                value={formData.zipcode}
                onChange={onChangeHandler}
              />
            </label>
            <label>
              <span>Country</span>
              <input
                type="text"
                name="country"
                value={formData.country}
                onChange={onChangeHandler}
              />
            </label>
            <label className="full-width">
              <span>Delivery phone</span>
              <input
                type="text"
                name="deliveryPhone"
                value={formData.deliveryPhone}
                onChange={onChangeHandler}
              />
            </label>
          </div>
        </div>

        <div className="profile-actions">
          <button type="submit" className="action-button" disabled={isSaving}>
            {isSaving ? 'Saving...' : 'Save profile'}
          </button>
          <Link to="/order" className="ghost-button">
            Go to checkout
          </Link>
        </div>
      </form>
    </section>
  )
}

export default Profile

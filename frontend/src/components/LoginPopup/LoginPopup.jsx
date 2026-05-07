import React, { useContext, useState } from 'react'
import './LoginPopup.css'
import { assets } from '../../assets/assets'
import { StoreContext } from './../context/StoreContext'
import axios from 'axios'

const LoginPopup = ({ setShowLogin }) => {
  const { login, url } = useContext(StoreContext)

  const [currentState, setCurrentState] = useState('Login')
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState({
    name: '',
    email: '',
    password: '',
  })

  const onChangeHandler = (event) => {
    const name = event.target.name
    const value = event.target.value
    setData((prev) => ({ ...prev, [name]: value }))
  }

  const onLogin = async (event) => {
    event.preventDefault()
    setLoading(true)

    let newUrl = url
    if (currentState === 'Login') {
      newUrl += '/api/user/login'
    } else {
      newUrl += '/api/user/register'
    }

    try {
      const response = await axios.post(newUrl, data)

      if (response.data.success) {
        await login(response.data)
        setShowLogin(false)
      } else {
        alert(response.data.message)
      }
    } catch (error) {
      alert(
        error.response?.data?.message ||
          "We couldn't complete your request. Please try again."
      )
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="login-popup">
      <form onSubmit={onLogin} className="login-popup-container">
        <div className="login-popup-copy">
          <span className="eyebrow">Welcome back</span>
          <p>
            Sign in to save your cart, place secure orders, and track delivery
            updates in one place.
          </p>
        </div>
        <div className="login-popup-title">
          <h2>
            {currentState === 'Login'
              ? 'Access your food account'
              : 'Create your food account'}
          </h2>
          <img
            onClick={() => setShowLogin(false)}
            src={assets.cross_icon}
            alt="Close"
          />
        </div>
        <div className="login-popup-inputs">
          {currentState === 'Login' ? null : (
            <input
              name="name"
              onChange={onChangeHandler}
              value={data.name}
              type="text"
              placeholder="Your name"
              required
            />
          )}

          <input
            name="email"
            onChange={onChangeHandler}
            value={data.email}
            type="email"
            placeholder="Your email"
            required
          />
          <input
            name="password"
            onChange={onChangeHandler}
            value={data.password}
            type="password"
            placeholder="Password"
            required
          />
        </div>

        <button type="submit" disabled={loading}>
          {loading
            ? 'Please wait...'
            : currentState === 'Sign Up'
              ? 'Create account'
              : 'Login'}
        </button>
        <div className="login-popup-condition">
          <input type="checkbox" required />
          <p>By continuing, I agree to the terms of use and privacy policy</p>
        </div>
        {currentState === 'Login' ? (
          <p className="login-popup-switch">
            Create a new account?{' '}
            <span onClick={() => setCurrentState('Sign Up')}>Sign up here</span>
          </p>
        ) : (
          <p className="login-popup-switch">
            Already have an account?{' '}
            <span onClick={() => setCurrentState('Login')}>Login here</span>
          </p>
        )}
      </form>
    </div>
  )
}

export default LoginPopup

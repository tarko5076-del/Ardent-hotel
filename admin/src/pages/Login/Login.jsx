import React, { useContext, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'react-toastify'
import { assets } from '../../assets/assets'
import { AdminContext } from '../../context/AdminContext'
import './Login.css'

const Login = () => {
  const navigate = useNavigate()
  const { login } = useContext(AdminContext)
  const [credentials, setCredentials] = useState({
    email: '',
    password: '',
  })
  const [submitting, setSubmitting] = useState(false)

  const handleChange = (event) => {
    const { name, value } = event.target
    setCredentials((previousValue) => ({
      ...previousValue,
      [name]: value,
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSubmitting(true)

    try {
      await login(credentials)
      toast.success('Welcome back to the admin dashboard.')
      navigate('/orders')
    } catch (error) {
      toast.error(error.message || 'Unable to sign in.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="admin-login-shell">
      <section className="admin-login-card admin-panel">
        <div className="admin-login-brand">
          <img src={assets.logo} alt="SwiftBite admin" />
          <div>
            <span className="admin-eyebrow">Protected admin access</span>
            <h2>Sign in to manage Ardent operations</h2>
            <p>
              Use an admin account to manage menu items, update live order
              statuses, and keep inventory accurate across the platform.
            </p>
          </div>
        </div>

        <form className="admin-login-form" onSubmit={handleSubmit}>
          <label>
            <span>Email address</span>
            <input
              type="email"
              name="email"
              value={credentials.email}
              onChange={handleChange}
              placeholder="admin@example.com"
              required
            />
          </label>

          <label>
            <span>Password</span>
            <input
              type="password"
              name="password"
              value={credentials.password}
              onChange={handleChange}
              placeholder="Enter your password"
              required
            />
          </label>

          <button type="submit" disabled={submitting}>
            {submitting ? 'Signing in...' : 'Sign in'}
          </button>
        </form>
      </section>
    </div>
  )
}

export default Login

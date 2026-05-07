import axios from 'axios'
import { createContext, useEffect, useState } from 'react'

export const AdminContext = createContext(null)

const STORAGE_KEY = 'food-delivery-admin-auth'

const readStoredSession = () => {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY)) || { token: '', user: null }
  } catch (error) {
    return { token: '', user: null }
  }
}

const writeStoredSession = (value) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
}

const AdminProvider = ({ children }) => {
  const storedSession = readStoredSession()
  const [token, setToken] = useState(storedSession.token || '')
  const [user, setUser] = useState(storedSession.user || null)
  const [authReady, setAuthReady] = useState(false)
  const url = import.meta.env.VITE_API_URL || 'http://localhost:5001'

  const getAuthConfig = (nextToken = token) => ({
    headers: {
      Authorization: `Bearer ${nextToken}`,
    },
  })

  const persistSession = (nextToken, nextUser) => {
    setToken(nextToken)
    setUser(nextUser)
    writeStoredSession({
      token: nextToken,
      user: nextUser,
    })
  }

  const clearSession = () => {
    localStorage.removeItem(STORAGE_KEY)
    setToken('')
    setUser(null)
  }

  const validateAdminSession = async (nextToken) => {
    const response = await axios.get(`${url}/api/user/me`, getAuthConfig(nextToken))

    if (!response.data.success || response.data.user?.role !== 'admin') {
      throw new Error('Admin access is required.')
    }

    persistSession(nextToken, response.data.user)
    return response.data.user
  }

  const login = async (credentials) => {
    const response = await axios.post(`${url}/api/user/login`, credentials)

    if (!response.data.success) {
      throw new Error(response.data.message || 'Unable to log in.')
    }

    if (response.data.user?.role !== 'admin') {
      throw new Error('This account does not have admin access.')
    }

    persistSession(response.data.token, response.data.user)
    return response.data.user
  }

  const logout = () => {
    clearSession()
  }

  useEffect(() => {
    const bootstrapAdminSession = async () => {
      try {
        if (storedSession.token) {
          await validateAdminSession(storedSession.token)
        }
      } catch (error) {
        clearSession()
      } finally {
        setAuthReady(true)
      }
    }

    bootstrapAdminSession()
  }, [])

  return (
    <AdminContext.Provider
      value={{
        authReady,
        getAuthConfig,
        login,
        logout,
        token,
        url,
        user,
      }}
    >
      {children}
    </AdminContext.Provider>
  )
}

export default AdminProvider

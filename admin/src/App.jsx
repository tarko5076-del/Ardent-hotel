import React, { useContext } from 'react'
import Navbar from './components/Navbar/Navbar'
import Sidebar from './components/Sidebar/Sidebar'
import { Navigate, Route, Routes } from 'react-router-dom'
import Add from './pages/Add/Add'
import Edit from './pages/Edit/Edit'
import Orders from './pages/Orders/Orders'
import RoomBookings from './pages/RoomBookings/RoomBookings'
import List from './pages/List/List'
import Login from './pages/Login/Login'
import { ToastContainer } from 'react-toastify'
import 'react-toastify/dist/ReactToastify.css'
import { AdminContext } from './context/AdminContext'

const App = () => {
  const { authReady, token } = useContext(AdminContext)

  if (!authReady) {
    return (
      <div className="admin-shell">
        <div className="admin-card" style={{ padding: '28px' }}>
          Loading admin dashboard...
        </div>
      </div>
    )
  }

  if (!token) {
    return (
      <>
        <ToastContainer />
        <Login />
      </>
    )
  }

  return (
    <div className="admin-shell">
      <ToastContainer />
      <Navbar />
      <div className="admin-layout">
        <Sidebar />
        <main className="admin-page">
          <Routes>
            <Route path="/" element={<Navigate to="/orders" replace />} />
            <Route path="/add" element={<Add />} />
            <Route path="/list" element={<List />} />
            <Route path="/edit/:id" element={<Edit />} />
            <Route path="/orders" element={<Orders />} />
            <Route path="/room-bookings" element={<RoomBookings />} />
            <Route path="*" element={<Navigate to="/orders" replace />} />
          </Routes>
        </main>
      </div>
    </div>
  )
}

export default App

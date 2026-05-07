import React, { useContext, useEffect, useState } from 'react'
import './List.css'
import axios from 'axios'
import { toast } from 'react-toastify'
import { Link } from 'react-router-dom'
import { AdminContext } from '../../context/AdminContext'

const List = () => {
  const { getAuthConfig, url } = useContext(AdminContext)
  const [list, setList] = useState([])
  const [stockValues, setStockValues] = useState({})

  const fetchList = async () => {
    try {
      const response = await axios.get(`${url}/api/food/list`)

      if (response.data.success) {
        setList(response.data.data)
        const nextStockValues = {}
        response.data.data.forEach((item) => {
          nextStockValues[item._id] = item.stock_quantity ?? 0
        })
        setStockValues(nextStockValues)
      } else {
        toast.error('Unable to fetch menu items.')
      }
    } catch (error) {
      toast.error('Unable to fetch menu items.')
    }
  }

  const removeFood = async (foodId) => {
    try {
      const response = await axios.post(
        `${url}/api/food/remove`,
        { id: foodId },
        getAuthConfig()
      )
      await fetchList()
      if (response.data.success) {
        toast.success(response.data.message)
      } else {
        toast.error('Unable to remove the item.')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to remove the item.')
    }
  }

  const updateStock = async (foodId) => {
    try {
      const response = await axios.post(
        `${url}/api/food/stock`,
        {
          id: foodId,
          stockQuantity: stockValues[foodId],
        },
        getAuthConfig()
      )

      if (response.data.success) {
        toast.success(response.data.message)
        await fetchList()
      } else {
        toast.error(response.data.message || 'Error updating stock')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to update stock.')
    }
  }

  useEffect(() => {
    fetchList()
  }, [])

  return (
    <section className="list-page">
      <div className="admin-heading">
        <span className="admin-eyebrow">Catalog overview</span>
        <h1>Manage all listed dishes</h1>
        <p>
          Review your menu lineup, update stock availability, and remove items
          that should no longer appear in the customer app.
        </p>
      </div>

      <div className="list admin-card">
        <div className="list-header">
          <h2>All foods list</h2>
          <span>{list.length} items</span>
        </div>

        <div className="list-table">
          <div className="list-table-format title">
            <b>Image</b>
            <b>Name</b>
            <b>Category</b>
            <b>Price</b>
            <b>Stock</b>
            <b>Actions</b>
          </div>
          {list.map((item) => (
            <div key={item._id} className="list-table-format">
              <img src={`${url}/images/${item.image}`} alt={item.name} />
              <div className="list-name-block">
                <p>{item.name}</p>
                <span className={item.isAvailable ? 'stock-badge live' : 'stock-badge soldout'}>
                  {item.isAvailable ? 'In stock' : 'Sold out'}
                </span>
              </div>
              <p>{item.category}</p>
              <p>${item.price}</p>
              <div className="stock-editor">
                <input
                  type="number"
                  min="0"
                  value={stockValues[item._id] ?? 0}
                  onChange={(event) =>
                    setStockValues((prev) => ({
                      ...prev,
                      [item._id]: event.target.value,
                    }))
                  }
                />
                <button onClick={() => updateStock(item._id)} className="stock-save">
                  Save
                </button>
              </div>
              <div className="list-actions">
                <Link to={`/edit/${item._id}`} className="list-edit">
                  Edit
                </Link>
                <button onClick={() => removeFood(item._id)} className="list-remove">
                  Remove
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export default List

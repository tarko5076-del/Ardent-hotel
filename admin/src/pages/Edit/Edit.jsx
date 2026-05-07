import axios from 'axios'
import React, { useContext, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'react-toastify'
import { assets } from '../../assets/assets'
import { AdminContext } from '../../context/AdminContext'
import '../Add/Add.css'

const Edit = () => {
  const { getAuthConfig, url } = useContext(AdminContext)
  const { id } = useParams()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [image, setImage] = useState(null)
  const [currentImage, setCurrentImage] = useState('')
  const [data, setData] = useState({
    name: '',
    description: '',
    price: '',
    category: 'Salad',
    stockQuantity: '',
  })

  const onChangeHandler = (event) => {
    const { name, value } = event.target
    setData((previousValue) => ({
      ...previousValue,
      [name]: value,
    }))
  }

  const loadFoodItem = async () => {
    try {
      const response = await axios.get(`${url}/api/food/${id}`)

      if (!response.data.success) {
        toast.error(response.data.message || 'Unable to load this menu item.')
        navigate('/list')
        return
      }

      const foodItem = response.data.data
      setData({
        name: foodItem.name,
        description: foodItem.description,
        price: foodItem.price,
        category: foodItem.category,
        stockQuantity: foodItem.stock_quantity,
      })
      setCurrentImage(foodItem.image)
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to load this menu item.')
      navigate('/list')
    } finally {
      setLoading(false)
    }
  }

  const onSubmitHandler = async (event) => {
    event.preventDefault()
    setSubmitting(true)

    try {
      const formData = new FormData()
      formData.append('name', data.name)
      formData.append('description', data.description)
      formData.append('price', Number(data.price))
      formData.append('category', data.category)
      formData.append('stockQuantity', Number(data.stockQuantity || 0))

      if (image) {
        formData.append('image', image)
      }

      const response = await axios.post(
        `${url}/api/food/${id}/update`,
        formData,
        getAuthConfig()
      )

      if (response.data.success) {
        toast.success(response.data.message)
        navigate('/list')
      } else {
        toast.error(response.data.message || 'Unable to update the menu item.')
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Unable to update the menu item.')
    } finally {
      setSubmitting(false)
    }
  }

  useEffect(() => {
    loadFoodItem()
  }, [id])

  if (loading) {
    return (
      <section className="add-page">
        <div className="admin-card add">Loading menu item...</div>
      </section>
    )
  }

  return (
    <section className="add-page">
      <div className="admin-heading">
        <span className="admin-eyebrow">Catalog editing</span>
        <h1>Update menu item details</h1>
        <p>
          Refresh imagery, tune pricing, change category placement, or correct
          stock values without removing and recreating the item.
        </p>
      </div>

      <div className="add admin-card">
        <form className="flex-col" onSubmit={onSubmitHandler}>
          <div className="add-grid">
            <div className="add-img-upload flex-col">
              <p>Update image</p>
              <label htmlFor="image">
                <img
                  src={
                    image
                      ? URL.createObjectURL(image)
                      : currentImage
                        ? `${url}/images/${currentImage}`
                        : assets.upload_area
                  }
                  alt="Upload area"
                />
              </label>
              <input
                onChange={(event) => setImage(event.target.files[0])}
                type="file"
                id="image"
                hidden
              />
            </div>

            <div className="add-form-fields">
              <div className="add-product-name flex-col">
                <p>Product name</p>
                <input
                  onChange={onChangeHandler}
                  value={data.name}
                  type="text"
                  name="name"
                  placeholder="Type here"
                  required
                />
              </div>

              <div className="add-product-description flex-col">
                <p>Product description</p>
                <textarea
                  onChange={onChangeHandler}
                  value={data.description}
                  name="description"
                  rows="6"
                  placeholder="Write content here"
                  required
                />
              </div>

              <div className="add-category-price">
                <div className="add-category flex-col">
                  <p>Product category</p>
                  <select onChange={onChangeHandler} value={data.category} name="category">
                    <option value="Salad">Salad</option>
                    <option value="Rolls">Rolls</option>
                    <option value="Deserts">Deserts</option>
                    <option value="Sandwich">Sandwich</option>
                    <option value="Cake">Cake</option>
                    <option value="Pure Veg">Pure Veg</option>
                    <option value="Pasta">Pasta</option>
                    <option value="Noodles">Noodles</option>
                  </select>
                </div>
                <div className="add-price flex-col">
                  <p>Product price</p>
                  <input
                    onChange={onChangeHandler}
                    value={data.price}
                    type="number"
                    name="price"
                    placeholder="$20"
                    required
                  />
                </div>
                <div className="add-stock flex-col">
                  <p>Stock quantity</p>
                  <input
                    onChange={onChangeHandler}
                    value={data.stockQuantity}
                    type="number"
                    min="0"
                    name="stockQuantity"
                    placeholder="0"
                    required
                  />
                </div>
              </div>
            </div>
          </div>

          <button type="submit" className="add-btn" disabled={submitting}>
            {submitting ? 'Saving changes...' : 'Save changes'}
          </button>
        </form>
      </div>
    </section>
  )
}

export default Edit

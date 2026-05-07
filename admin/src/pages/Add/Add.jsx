import React, { useContext, useState } from 'react'
import './Add.css'
import { assets } from '../../assets/assets'
import axios from 'axios'
import { toast } from 'react-toastify'
import { AdminContext } from '../../context/AdminContext'

const Add = () => {
  const { getAuthConfig, url } = useContext(AdminContext)
  const [image, setImage] = useState(false)
  const [data, setData] = useState({
    name: '',
    description: '',
    price: '',
    category: 'Salad',
    stockQuantity: '',
  })

  const onChangeHandler = (event) => {
    const name = event.target.name
    const value = event.target.value
    setData((prev) => ({ ...prev, [name]: value }))
  }

  const onSubmitHandler = async (event) => {
    event.preventDefault()
    const formData = new FormData()
    formData.append('name', data.name)
    formData.append('description', data.description)
    formData.append('price', Number(data.price))
    formData.append('category', data.category)
    formData.append('stockQuantity', Number(data.stockQuantity || 0))
    formData.append('image', image)

    try {
      const response = await axios.post(
        `${url}/api/food/add`,
        formData,
        getAuthConfig()
      )

      if (response.data.success) {
        setData({
          name: '',
          description: '',
          price: '',
          category: 'Salad',
          stockQuantity: '',
        })
        setImage(false)
        toast.success(response.data.message)
      } else {
        toast.error(response.data.message)
      }
    } catch (error) {
      toast.error(
        error.response?.data?.message ||
          error.message ||
          'Unable to add the menu item.'
      )
    }
  }

  return (
    <section className="add-page">
      <div className="admin-heading">
        <span className="admin-eyebrow">Menu management</span>
        <h1>Add a new dish to Ardent</h1>
        <p>
          Upload a strong product image, define the category, set the stock,
          and publish a polished listing that looks good on the customer app.
        </p>
      </div>

      <div className="add admin-card">
        <form className="flex-col" onSubmit={onSubmitHandler}>
          <div className="add-grid">
            <div className="add-img-upload flex-col">
              <p>Upload image</p>
              <label htmlFor="image">
                <img
                  src={image ? URL.createObjectURL(image) : assets.upload_area}
                  alt="Upload area"
                />
              </label>
              <input
                onChange={(e) => setImage(e.target.files[0])}
                type="file"
                id="image"
                hidden
                required
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

          <button type="submit" className="add-btn">
            Publish item
          </button>
        </form>
      </div>
    </section>
  )
}

export default Add

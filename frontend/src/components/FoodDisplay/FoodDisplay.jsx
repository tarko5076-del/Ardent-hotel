import axios from 'axios'
import React, { useContext, useEffect, useState } from 'react'
import './FoodDisplay.css'
import { StoreContext } from '../context/StoreContext'
import FoodItem from '../FoodItem/FoodItem'
import FoodPopup from '../FoodPopup/FoodPopup'

const FoodDisplay = ({
  category,
  searchTerm,
  setSearchTerm,
  availableOnly,
  setAvailableOnly,
  setCategory,
}) => {
    const { url } = useContext(StoreContext)
    const [selectedFood, setSelectedFood] = useState(null)
    const [displayItems, setDisplayItems] = useState([])
    const [isLoading, setIsLoading] = useState(true)
    const [errorMessage, setErrorMessage] = useState('')

    useEffect(() => {
      let isMounted = true
      const timeoutId = window.setTimeout(async () => {
        try {
          setIsLoading(true)
          const params = {}

          if (category !== 'All') {
            params.category = category
          }

          if (searchTerm.trim()) {
            params.search = searchTerm.trim()
          }

          if (availableOnly) {
            params.availableOnly = 'true'
          }

          const response = await axios.get(`${url}/api/food/list`, { params })

          if (!isMounted) {
            return
          }

          if (response.data.success) {
            setDisplayItems(response.data.data || [])
            setErrorMessage('')
          } else {
            setDisplayItems([])
            setErrorMessage('Unable to load dishes right now.')
          }
        } catch (error) {
          if (!isMounted) {
            return
          }

          setDisplayItems([])
          setErrorMessage('Unable to load dishes right now.')
        } finally {
          if (isMounted) {
            setIsLoading(false)
          }
        }
      }, searchTerm.trim() ? 260 : 0)

      return () => {
        isMounted = false
        window.clearTimeout(timeoutId)
      }
    }, [availableOnly, category, searchTerm, url])

    const activeFilterCount =
      Number(category !== 'All') +
      Number(Boolean(searchTerm.trim())) +
      Number(availableOnly)

  return (
    <div className='food-display' id='food-display'>
        <FoodPopup
          item={selectedFood}
          onClose={() => setSelectedFood(null)}
          redirectToCart
        />
        <div className="food-display-copy">
          <span className="eyebrow">Guest favorites</span>
          <h2 className="section-title">Top dishes near you</h2>
          <p>Freshly prepared selections designed for hotel comfort, quick checkout, and a memorable dining experience.</p>
        </div>
        <div className="food-display-toolbar panel">
          <div className="food-display-search">
            <label htmlFor="menu-search">Search the menu</label>
            <input
              id="menu-search"
              type="text"
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              placeholder="Search by dish name or description"
            />
          </div>
          <label
            className={`food-display-toggle ${
              availableOnly ? 'active' : ''
            }`}
          >
            <input
              type="checkbox"
              checked={availableOnly}
              onChange={(event) => setAvailableOnly(event.target.checked)}
            />
            <span>Available now only</span>
          </label>
          <button
            type="button"
            className="food-display-clear"
            onClick={() => {
              setCategory('All')
              setSearchTerm('')
              setAvailableOnly(false)
            }}
            disabled={!activeFilterCount}
          >
            Clear filters
          </button>
        </div>
        <div className="food-display-status">
          <p>
            {isLoading
              ? 'Refreshing dishes...'
              : `${displayItems.length} dish${
                  displayItems.length === 1 ? '' : 'es'
                } found`}
          </p>
          <span>
            {category === 'All' ? 'All categories' : category}
            {searchTerm.trim() ? ` - "${searchTerm.trim()}"` : ''}
          </span>
        </div>
        {errorMessage ? (
          <div className="food-display-feedback error">{errorMessage}</div>
        ) : null}
        {!errorMessage && !isLoading && !displayItems.length ? (
          <div className="food-display-feedback empty">
            <h3>No dishes matched those filters.</h3>
            <p>Try a different search phrase, switch categories, or turn off the availability filter.</p>
          </div>
        ) : null}
        <div className="food-display-list">
            {displayItems.map((item) => (
              <FoodItem
                key={item._id || String(item.id)}
                id={item._id || String(item.id)}
                name={item.name}
                description={item.description}
                price={item.price}
                image={item.image}
                stockQuantity={item.stock_quantity}
                isAvailable={item.isAvailable}
                item={item}
                onOpen={() => setSelectedFood(item)}
                redirectToCart
              />
            ))}
        </div>
    </div>
  )
}

export default FoodDisplay

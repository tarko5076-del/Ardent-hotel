import React, { useState } from 'react'
import './Home.css'
import Header from '../../components/Header/Header'
import ExploreMenu from '../../components/ExploreMenu/ExploreMenu'
import FoodDisplay from '../../components/FoodDisplay/FoodDisplay'

const Home = () => {
  const [category, setCategory] = useState('All')
  const [searchTerm, setSearchTerm] = useState('')
  const [availableOnly, setAvailableOnly] = useState(false)

  return (
    <div className="home-page">
      <Header/>
      <ExploreMenu category={category} setCategory={setCategory}/>
      <FoodDisplay
        availableOnly={availableOnly}
        category={category}
        searchTerm={searchTerm}
        setAvailableOnly={setAvailableOnly}
        setCategory={setCategory}
        setSearchTerm={setSearchTerm}
      />
    </div>
  )
}

export default Home

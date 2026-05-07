import axios from 'axios'
import { createContext, useEffect, useState } from 'react'

export const StoreContext = createContext(null)

const AUTH_STORAGE_KEY = 'food-delivery-auth'
const GUEST_CART_STORAGE_KEY = 'food-delivery-guest-cart'

const defaultPaymentConfig = {
  paymentMethods: {
    cod: true,
    card: false,
    chapa: false,
  },
  deliveryFee: 2,
}

const readJsonStorage = (key, fallbackValue) => {
  try {
    const storedValue = localStorage.getItem(key)
    return storedValue ? JSON.parse(storedValue) : fallbackValue
  } catch (error) {
    return fallbackValue
  }
}

const writeJsonStorage = (key, value) => {
  localStorage.setItem(key, JSON.stringify(value))
}

const buildAuthConfig = (token) => ({
  headers: {
    Authorization: `Bearer ${token}`,
  },
})

const extractFavoriteIds = (responseData) => {
  if (Array.isArray(responseData.favoriteIds)) {
    return responseData.favoriteIds.map((itemId) => String(itemId))
  }

  if (Array.isArray(responseData.data)) {
    return responseData.data.map((item) => String(item._id || item.id))
  }

  return []
}

const StoreContextProvider = (props) => {
  const storedAuth = readJsonStorage(AUTH_STORAGE_KEY, { token: '', user: null })
  const [cartItems, setCartItems] = useState(
    readJsonStorage(GUEST_CART_STORAGE_KEY, {})
  )
  const [token, setToken] = useState(storedAuth.token || '')
  const [user, setUser] = useState(storedAuth.user || null)
  const [food_list, setFoodList] = useState([])
  const [favoriteIds, setFavoriteIds] = useState([])
  const [paymentConfig, setPaymentConfig] = useState(defaultPaymentConfig)
  const [authReady, setAuthReady] = useState(false)
  const url = import.meta.env.VITE_API_URL || 'http://localhost:5001'
  const adminUrl = import.meta.env.VITE_ADMIN_APP_URL || 'http://localhost:5174'

  const persistGuestCart = (nextCart) => {
    writeJsonStorage(GUEST_CART_STORAGE_KEY, nextCart)
  }

  const applyCartData = (cartData) => {
    setCartItems(cartData || {})
  }

  const persistAuth = (nextToken, nextUser) => {
    setToken(nextToken)
    setUser(nextUser)
    writeJsonStorage(AUTH_STORAGE_KEY, {
      token: nextToken,
      user: nextUser,
    })
  }

  const clearStoredAuth = () => {
    localStorage.removeItem(AUTH_STORAGE_KEY)
  }

  const fetchFoodList = async () => {
    const response = await axios.get(`${url}/api/food/list`)
    setFoodList(response.data.data || [])
  }

  const fetchFavorites = async (authToken = token) => {
    if (!authToken) {
      setFavoriteIds([])
      return []
    }

    const response = await axios.get(
      `${url}/api/favorites`,
      buildAuthConfig(authToken)
    )

    if (response.data.success) {
      const nextFavoriteIds = extractFavoriteIds(response.data)
      setFavoriteIds(nextFavoriteIds)
      return response.data.data || []
    }

    setFavoriteIds([])
    return []
  }

  const fetchOrderConfig = async () => {
    const response = await axios.get(`${url}/api/order/config`)

    if (response.data.success) {
      setPaymentConfig({
        paymentMethods: {
          cod: Boolean(response.data.paymentMethods?.cod),
          card: Boolean(response.data.paymentMethods?.card),
          chapa: Boolean(response.data.paymentMethods?.chapa),
        },
        deliveryFee: Number(response.data.deliveryFee || 0),
      })
    }
  }

  const loadCurrentUser = async (authToken) => {
    const response = await axios.get(`${url}/api/user/me`, buildAuthConfig(authToken))

    if (response.data.success) {
      persistAuth(authToken, response.data.user)
    }
  }

  const loadServerCart = async (authToken) => {
    const response = await axios.post(
      `${url}/api/cart/get`,
      {},
      buildAuthConfig(authToken)
    )

    if (response.data.success) {
      applyCartData(response.data.cartData)
      localStorage.removeItem(GUEST_CART_STORAGE_KEY)
    }
  }

  const syncGuestCart = async (authToken) => {
    const guestCart = readJsonStorage(GUEST_CART_STORAGE_KEY, {})

    if (!Object.keys(guestCart).length) {
      return false
    }

    const response = await axios.post(
      `${url}/api/cart/sync`,
      { items: guestCart },
      buildAuthConfig(authToken)
    )

    if (response.data.success) {
      applyCartData(response.data.cartData)
      localStorage.removeItem(GUEST_CART_STORAGE_KEY)
      return true
    }

    return false
  }

  const clearAuth = () => {
    persistGuestCart(cartItems)
    clearStoredAuth()
    setToken('')
    setUser(null)
    setFavoriteIds([])
  }

  const refreshCart = async () => {
    if (!token) {
      const guestCart = readJsonStorage(GUEST_CART_STORAGE_KEY, {})
      applyCartData(guestCart)
      return
    }

    await loadServerCart(token)
  }

  const login = async ({ token: nextToken, user: nextUser }) => {
    persistAuth(nextToken, nextUser)
    const synced = await syncGuestCart(nextToken)

    if (!synced) {
      await loadServerCart(nextToken)
    }

    await fetchFavorites(nextToken)
  }

  const logout = () => {
    clearAuth()
  }

  const updateGuestCart = (updater) => {
    setCartItems((previousCart) => {
      const nextCart =
        typeof updater === 'function' ? updater(previousCart) : updater
      persistGuestCart(nextCart)
      return nextCart
    })
  }

  const addToCart = async (itemId) => {
    const selectedFood = food_list.find(
      (item) => String(item._id || item.id) === String(itemId)
    )

    if (!selectedFood?.isAvailable) {
      window.alert('This item is currently unavailable.')
      return false
    }

    if (!token) {
      let itemWasAdded = false

      updateGuestCart((previousCart) => {
        const currentQuantity = Number(previousCart[itemId] || 0)
        const availableStock =
          selectedFood.stock_quantity == null
            ? Infinity
            : Number(selectedFood.stock_quantity)

        if (currentQuantity >= availableStock) {
          window.alert('Requested quantity exceeds available stock.')
          return previousCart
        }

        itemWasAdded = true
        return {
          ...previousCart,
          [itemId]: currentQuantity + 1,
        }
      })

      return itemWasAdded
    }

    const response = await axios.post(
      `${url}/api/cart/add`,
      { itemId },
      buildAuthConfig(token)
    )

    if (response.data.success) {
      applyCartData(response.data.cartData)
      return true
    }

    window.alert(response.data.message || 'Unable to add the item to your cart.')
    return false
  }

  const removeFromCart = async (itemId) => {
    if (!token) {
      updateGuestCart((previousCart) => {
        const currentQuantity = Number(previousCart[itemId] || 0)

        if (currentQuantity <= 1) {
          const nextCart = { ...previousCart }
          delete nextCart[itemId]
          return nextCart
        }

        return {
          ...previousCart,
          [itemId]: currentQuantity - 1,
        }
      })
      return
    }

    const response = await axios.post(
      `${url}/api/cart/remove`,
      { itemId },
      buildAuthConfig(token)
    )

    if (response.data.success) {
      applyCartData(response.data.cartData)
      return
    }

    window.alert(response.data.message || 'Unable to update your cart.')
  }

  const updateCartItemQuantity = async (itemId, quantity) => {
    if (!token) {
      updateGuestCart((previousCart) => {
        if (quantity <= 0) {
          const nextCart = { ...previousCart }
          delete nextCart[itemId]
          return nextCart
        }

        return {
          ...previousCart,
          [itemId]: quantity,
        }
      })
      return
    }

    const response = await axios.patch(
      `${url}/api/cart/items/${itemId}`,
      { quantity },
      buildAuthConfig(token)
    )

    if (response.data.success) {
      applyCartData(response.data.cartData)
      return
    }

    window.alert(response.data.message || 'Unable to update your cart.')
  }

  const removeAllFromCart = async () => {
    if (!token) {
      updateGuestCart({})
      return
    }

    const response = await axios.post(
      `${url}/api/cart/clear`,
      {},
      buildAuthConfig(token)
    )

    if (response.data.success) {
      applyCartData({})
      return
    }

    window.alert(response.data.message || 'Unable to clear your cart.')
  }

  const updateProfile = async (profileData) => {
    if (!token) {
      return {
        success: false,
        message: 'Please sign in again before updating your profile.',
      }
    }

    try {
      const response = await axios.put(
        `${url}/api/user/me`,
        profileData,
        buildAuthConfig(token)
      )

      if (response.data.success) {
        persistAuth(token, response.data.user)
      }

      return {
        success: Boolean(response.data.success),
        message: response.data.message,
        user: response.data.user || null,
      }
    } catch (error) {
      return {
        success: false,
        message:
          error.response?.data?.message ||
          'Unable to update your profile right now.',
      }
    }
  }

  const toggleFavorite = async (itemId) => {
    if (!token) {
      window.alert('Please sign in to save items to your wishlist.')
      return false
    }

    const selectedId = String(itemId)
    const itemIsFavorite = favoriteIds.includes(selectedId)

    try {
      const response = itemIsFavorite
        ? await axios.delete(
            `${url}/api/favorites/${selectedId}`,
            buildAuthConfig(token)
          )
        : await axios.post(
            `${url}/api/favorites/${selectedId}`,
            {},
            buildAuthConfig(token)
          )

      if (response.data.success) {
        setFavoriteIds(extractFavoriteIds(response.data))
        return !itemIsFavorite
      }

      window.alert(response.data.message || 'Unable to update your wishlist.')
      return itemIsFavorite
    } catch (error) {
      window.alert(
        error.response?.data?.message || 'Unable to update your wishlist.'
      )
      return itemIsFavorite
    }
  }

  const getTotalCartAmount = () => {
    let totalAmount = 0

    for (const itemId in cartItems) {
      if (cartItems[itemId] > 0) {
        const itemInfo = food_list.find(
          (product) => String(product._id || product.id) === String(itemId)
        )

        if (itemInfo) {
          totalAmount += Number(itemInfo.price) * Number(cartItems[itemId])
        }
      }
    }

    return totalAmount
  }

  useEffect(() => {
    const loadData = async () => {
      try {
        await Promise.all([fetchFoodList(), fetchOrderConfig()])

        if (storedAuth.token) {
          try {
            await loadCurrentUser(storedAuth.token)
            const synced = await syncGuestCart(storedAuth.token)

            if (!synced) {
              await loadServerCart(storedAuth.token)
            }

            await fetchFavorites(storedAuth.token)
          } catch (error) {
            clearStoredAuth()
            setToken('')
            setUser(null)
            setFavoriteIds([])
          }
        } else {
          setFavoriteIds([])
        }
      } finally {
        setAuthReady(true)
      }
    }

    loadData()
  }, [])

  const contextValue = {
    addToCart,
    adminUrl,
    authReady,
    cartItems,
    favoriteCount: favoriteIds.length,
    favoriteIds,
    favoriteItems: food_list.filter((item) =>
      favoriteIds.includes(String(item._id || item.id))
    ),
    food_list,
    fetchFavorites,
    getTotalCartAmount,
    isAdmin: user?.role === 'admin',
    isFavorite: (itemId) => favoriteIds.includes(String(itemId)),
    login,
    logout,
    paymentConfig,
    refreshCatalog: fetchFoodList,
    refreshCart,
    removeAllFromCart,
    removeFromCart,
    token,
    toggleFavorite,
    updateProfile,
    updateCartItemQuantity,
    url,
    user,
  }

  return (
    <StoreContext.Provider value={contextValue}>
      {props.children}
    </StoreContext.Provider>
  )
}

export default StoreContextProvider

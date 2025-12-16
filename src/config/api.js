// API Configuration
// Dynamically determines the API base URL based on the current hostname
// This allows the app to work when accessed via network IP (e.g., from mobile devices)

const getApiBaseUrl = () => {
  // Production API URL
  return 'https://arpege.atwebpages.com/api'
}

export const API_BASE_URL = getApiBaseUrl()

// Helper function to build full API URLs
export const getApiUrl = (endpoint) => {
  // Remove leading slash if present
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint
  return `${API_BASE_URL}/${cleanEndpoint}`
}

// Export commonly used API endpoints
export const API_ENDPOINTS = {
  USER: 'user.php',
  TABLE: 'crud_table.php',
  PRODUCT: 'crud_product.php',
  ORDER: 'order.php',
  ORDER_HISTORY: 'order_history.php',
  CATEGORY: 'crud_category.php',
  USER_CRUD: 'crud_user.php',
  STOCK: 'stock.php',
  PRODUCT_CONSUMPTION: 'product_consumption.php',
  INGREDIENT_USAGE: 'ingredient_usage.php'
}


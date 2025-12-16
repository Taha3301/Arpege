// API Configuration
// Dynamically determines the API base URL based on the current hostname
// This allows the app to work when accessed via network IP (e.g., from mobile devices)

const getApiBaseUrl = () => {
  // Get the current hostname (e.g., 'localhost', '192.168.1.202', etc.)
  const hostname = window.location.hostname
  
  // If accessing via localhost or 127.0.0.1, use localhost
  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return 'http://localhost/Backend/api'
  }
  
  // Otherwise, use the current hostname (network IP)
  // This assumes your backend is accessible at the same IP on port 80
  return `http://${hostname}/Backend/api`
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


<template>
  <div class="order-container">
    <header class="header">
      <button @click="goBack" class="back-btn">← Retour</button>
      <h1>Commande - Table {{ selectedTable?.table_number }}</h1>
      <div></div>
    </header>

    <div class="disclaimer">
      Chaque erreur dans la sélection des produits est sous votre responsabilité. Vous paierez pour vos erreurs.
    </div>

    <div class="content">

      <div class="order-layout">
        <!-- Products Section -->
        <div class="products-section">
          <h2>Produits</h2>
          
          <!-- Category Filter -->
          <div class="category-filter">
            <button 
              @click="selectedCategory = null"
              :class="['filter-btn', { active: selectedCategory === null }]"
            >
              Tous
            </button>
            <button 
              v-for="category in categories"
              :key="category.id"
              @click="selectedCategory = category.id"
              :class="['filter-btn', { active: selectedCategory === category.id }]"
            >
              {{ category.name }}
            </button>
          </div>

          <!-- Products Grid -->
          <div v-if="loadingProducts" class="loading">Chargement des produits...</div>
          <div v-else class="products-grid">
            <div 
              v-for="product in filteredProducts"
              :key="product.id"
              class="product-card"
              :class="{ 'out-of-stock': getMaxAvailableForProduct(product) === 0 }"
            >
              <div class="product-info">
                <h3>{{ product.name }}</h3>
                <p class="product-price">{{ formatPrice(props.priceMode === 'menu1' ? product.price : product.price_strangers) }}</p>
                <p class="product-category">{{ product.category_name || 'Sans catégorie' }}</p>
                <p v-if="getMaxAvailableForProduct(product) === 0" class="stock-warning">Rupture de stock</p>
                <p v-else-if="getMaxAvailableForProduct(product) !== Infinity" class="stock-info">
                  Stock possible: {{ getMaxAvailableForProduct(product) }}
                </p>
              </div>
              <div class="product-actions">
                <div class="quantity-controls">
                  <button 
                    @click="decreaseQuantity(product.id)"
                    :disabled="!getProductQuantity(product.id)"
                    class="qty-btn"
                  >
                    -
                  </button>
                  <span class="quantity">{{ getProductQuantity(product.id) }}</span>
                  <button 
                    @click="increaseQuantity(product.id)"
                    :disabled="getMaxAvailableForProduct(product) === 0"
                    class="qty-btn"
                  >
                    +
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="order-summary">
          <h2>Résumé de la commande</h2>
          
          <div v-if="orderItems.length === 0" class="empty-cart">
            <p>Aucun produit sélectionné</p>
          </div>
          
          <div v-else class="order-items">
            <div 
              v-for="item in orderItems"
              :key="item.product.id"
              class="order-item"
            >
              <div class="item-info">
                <span class="item-name">{{ item.product.name }}</span>
                <span class="item-price">{{ formatPrice(props.priceMode === 'menu1' ? item.product.price : item.product.price_strangers) }} × {{ item.quantity }}</span>
              </div>
              <div class="item-total">
                <div>
                  {{ formatPrice((props.priceMode === 'menu1' ? item.product.price : item.product.price_strangers) * item.quantity) }}
                </div>
              </div>
              <button @click="removeItem(item.product.id)" class="remove-btn">×</button>
            </div>
          </div>

          <div class="order-total">
            <div class="total-row subtotal">
              <span>Total :</span>
              <span class="subtotal-amount">{{ formatPrice(totalBeforeDecrease) }}</span>
            </div>
            <div class="total-row">
              <span>Total à payer :</span>
              <span class="total-amount">{{ formatPrice(totalAmount) }}</span>
            </div>
          </div>

          <button 
            @click="openConfirmModal"
            :disabled="orderItems.length === 0 || submitting"
            class="submit-btn"
          >
            {{ submitting ? 'Envoi...' : 'Valider la commande' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Confirm Modal -->
    <div v-if="showConfirmModal" class="confirm-overlay">
      <div class="confirm-card">
        <h3 class="confirm-title">Confirmer la commande</h3>
        <p class="confirm-subtitle">Veuillez vérifier les produits et quantités avant de valider.</p>

        <div class="confirm-items">
          <div
            v-for="item in orderItems"
            :key="item.product.id"
            class="confirm-item"
          >
            <span class="confirm-item-name">{{ item.product.name }}</span>
            <span class="confirm-item-qty">× {{ item.quantity }}</span>
            <span class="confirm-item-total">{{ formatPrice((priceMode === 'menu1' ? item.product.price : item.product.price_strangers) * item.quantity) }}</span>
          </div>
        </div>

        <div class="confirm-total-row">
          <span>Total à payer</span>
          <span class="confirm-total-amount">{{ formatPrice(totalAmount) }}</span>
        </div>

        <div class="confirm-actions">
          <button type="button" class="btn-secondary" @click="cancelConfirm" :disabled="submitting">
            Annuler
          </button>
          <button type="button" class="btn-primary" @click="confirmSubmit" :disabled="submitting">
            {{ submitting ? 'Validation...' : 'Confirmer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Message -->
    <div v-if="message" :class="['message', messageType]">
      {{ message }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { getApiUrl, API_ENDPOINTS } from '../../config/api.js'

const props = defineProps({
  selectedTable: {
    type: Object,
    required: true
  },
  employeeId: {
    type: Number,
    default: null
  },
  priceMode: {
    type: String,
    default: 'menu1'
  }
})

const emit = defineEmits(['go-back', 'order-submitted'])

const API_URL = getApiUrl(API_ENDPOINTS.PRODUCT)
const ORDER_API_URL = getApiUrl(API_ENDPOINTS.ORDER)

const products = ref([])
const categories = ref([])
const loadingProducts = ref(false)
const selectedCategory = ref(null)
const orderItems = ref([]) // Array of { product, quantity }
const submitting = ref(false)
const currentOrderId = ref(null)
const message = ref('')
const messageType = ref('')

const showConfirmModal = ref(false)

const filteredProducts = computed(() => {
  if (!selectedCategory.value) {
    return products.value
  }
  return products.value.filter(p => p.category_id === selectedCategory.value)
})

const totalBeforeDecrease = computed(() => {
  return orderItems.value.reduce((sum, item) => {
    const price = props.priceMode === 'menu1' ? item.product.price : item.product.price_strangers
    return sum + (price * item.quantity)
  }, 0)
})

const totalAmount = computed(() => {
  return totalBeforeDecrease.value
})

const fetchProducts = async () => {
  loadingProducts.value = true
  try {
    const response = await fetch(API_URL)
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
    const data = await response.json()
    if (data.success) {
      products.value = Array.isArray(data.data) ? data.data : []
    }
  } catch (error) {
    showMessage('Erreur lors du chargement des produits', 'error')
    products.value = []
  } finally {
    loadingProducts.value = false
  }
}

// Compute maximum available order quantity for a product based on stock mapping
const getMaxAvailableForProduct = (product) => {
  const mappings = product?.stock_ingredients
  if (!Array.isArray(mappings) || mappings.length === 0) {
    // No stock mapping => no stock limit from this table
    return Infinity
  }

  let maxUnits = Infinity
  for (const m of mappings) {
    const unitQty = Number(m.quantity) || 0      // stock consumed per 1 product
    const stockQty = Number(m.stock_quantity) || 0
    if (unitQty <= 0) continue
    const possible = Math.floor(stockQty / unitQty)
    if (possible < maxUnits) {
      maxUnits = possible
    }
  }

  return maxUnits === Infinity ? 0 : maxUnits
}

const fetchCategories = async () => {
  try {
    const response = await fetch(`${API_URL}?type=categories`)
    if (response.ok) {
      const data = await response.json()
      if (data.success) {
        categories.value = Array.isArray(data.data) ? data.data : []
      }
    }
  } catch (error) {
    console.error('Error fetching categories:', error)
  }
}

const getProductQuantity = (productId) => {
  const item = orderItems.value.find(item => item.product.id === productId)
  return item ? item.quantity : 0
}

const increaseQuantity = (productId) => {
  const product = products.value.find(p => p.id === productId)
  if (!product) return

  const maxAvailable = getMaxAvailableForProduct(product)
  if (maxAvailable === 0) {
    showMessage('Stock insuffisant', 'error')
    return
  }

  const existingItem = orderItems.value.find(item => item.product.id === productId)
  const currentQty = existingItem ? existingItem.quantity : 0

  if (currentQty >= maxAvailable && maxAvailable !== Infinity) {
    showMessage('Stock insuffisant', 'error')
    return
  }

  if (existingItem) {
    existingItem.quantity++
  } else {
    orderItems.value.push({ product, quantity: 1 })
  }
}

const decreaseQuantity = (productId) => {
  const existingItem = orderItems.value.find(item => item.product.id === productId)
  if (existingItem) {
    existingItem.quantity--
    if (existingItem.quantity <= 0) {
      removeItem(productId)
    }
  }
}

const removeItem = (productId) => {
  const index = orderItems.value.findIndex(item => item.product.id === productId)
  if (index > -1) {
    orderItems.value.splice(index, 1)
  }
}

const loadExistingOrder = async () => {
  // Only load an existing order when the table is occupied
  const statusLower = props.selectedTable.status?.toLowerCase() || ''
  if (statusLower !== 'occupée' && statusLower !== 'occupied') {
    currentOrderId.value = null
    orderItems.value = []
    return
  }

  try {
    const response = await fetch(`${ORDER_API_URL}?table_id=${props.selectedTable.id}`)
    if (!response.ok) return

    const data = await response.json()
    if (data.success && data.data) {
      currentOrderId.value = data.data.id
      const items = Array.isArray(data.data.items) ? data.data.items : []

      // Map existing items to current products list
      orderItems.value = items.map(item => {
        const product = products.value.find(p => p.id === item.product_id)
        if (!product) return null
        return {
          product,
          quantity: Number(item.quantity) || 0
        }
      }).filter(Boolean)
    }
  } catch (error) {
    console.error('Error loading existing order:', error)
  }
}

const openConfirmModal = () => {
  if (orderItems.value.length === 0) return
  showConfirmModal.value = true
}

const cancelConfirm = () => {
  if (submitting.value) return
  showConfirmModal.value = false
}

const confirmSubmit = async () => {
  if (orderItems.value.length === 0) {
    showConfirm('Aucun produit sÃ©lectionnÃ© pour la commande.', 'error')
    showConfirmModal.value = false
    return
  }

  submitting.value = true
  try {
    // Debug: Log employeeId prop
    console.log('Employee ID prop:', props.employeeId, 'Type:', typeof props.employeeId)
    
    const orderData = {
      table_id: props.selectedTable.id,
      items: orderItems.value.map(item => {
        const price = props.priceMode === 'menu1' ? item.product.price : item.product.price_strangers
        return {
          product_id: item.product.id,
          quantity: item.quantity,
          price: price,
          percent_decrease: null,
          total_before_decrease: price * item.quantity
        }
      }),
      total: totalAmount.value,
      percent_decrease: null,
      total_before_decrease: totalBeforeDecrease.value
    }
    
    // Always include employee_id if it exists (even if 0, though unlikely)
    // Convert to number to ensure it's a valid integer
    if (props.employeeId !== null && props.employeeId !== undefined) {
      const empId = Number(props.employeeId)
      if (!isNaN(empId)) {
        orderData.employee_id = empId
        console.log('âœ… Adding employee_id to order:', orderData.employee_id, 'Type:', typeof orderData.employee_id)
      } else {
        console.warn('âš ï¸ Employee ID is not a valid number:', props.employeeId)
      }
    } else {
      console.warn('âš ï¸ Employee ID is null or undefined. Value:', props.employeeId)
    }
    
    console.log('ðŸ“¦ Order data being sent:', JSON.stringify(orderData, null, 2))

    const isUpdate = currentOrderId.value !== null
    const url = isUpdate ? `${ORDER_API_URL}?id=${currentOrderId.value}` : ORDER_API_URL
    const method = isUpdate ? 'PUT' : 'POST'

    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(orderData)
    })

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: 'Erreur serveur' }))
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }

    const data = await response.json()

    if (data.success) {
      showMessage('Commande validée avec succès!', 'success')
      showConfirmModal.value = false
      setTimeout(() => {
        emit('order-submitted')
        goBack()
      }, 1500)
    } else {
      showMessage(data.message || 'Erreur lors de la validation', 'error')
    }
  } catch (error) {
    showMessage('Erreur: ' + (error.message || 'Erreur de connexion'), 'error')
  } finally {
    submitting.value = false
  }
}

const formatPrice = (price) => {
  const value = Number(price) || 0
  return `${value.toFixed(2)} DT`
}

const showMessage = (msg, type) => {
  message.value = msg
  messageType.value = type
  setTimeout(() => {
    message.value = ''
  }, 3000)
}

const goBack = () => {
  emit('go-back')
}

// Watch for employeeId prop changes
watch(() => props.employeeId, (newVal, oldVal) => {
  console.log('ðŸ” employeeId prop changed:', { old: oldVal, new: newVal, type: typeof newVal })
}, { immediate: true })

onMounted(async () => {
  console.log('ðŸ“„ OrderPage mounted')
  console.log('ðŸ‘¤ employeeId prop:', props.employeeId, 'Type:', typeof props.employeeId)
  console.log('ðŸ“‹ All props:', { selectedTable: props.selectedTable, employeeId: props.employeeId })
  await fetchProducts()
  await fetchCategories()
  await loadExistingOrder()
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

/* â”€â”€ ROOT â”€â”€ */
.order-container {
  min-height: 100vh;
  font-family: 'Inter', sans-serif;
  background-image:
    linear-gradient(160deg, rgba(14,12,10,0.97) 0%, rgba(25,18,8,0.95) 60%, rgba(14,12,10,0.98) 100%),
    url('../../assets/bguser.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  overflow-y: auto;
  overflow-x: hidden;
}

/* â”€â”€ HEADER â”€â”€ */
.header {
  background: rgba(255,255,255,0.04);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(212,168,67,0.18);
  padding: 1.1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header h1 {
  margin: 0;
  color: #f0e6c8;
  font-size: 1.35rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.back-btn {
  padding: 0.6rem 1.3rem;
  background: transparent;
  color: rgba(255,255,255,0.7);
  border: 1.5px solid rgba(255,255,255,0.2);
  border-radius: 10px;
  cursor: pointer;
  font-size: 0.9rem;
  font-family: inherit;
  transition: all 0.22s ease;
  font-weight: 500;
}

.back-btn:hover {
  background: rgba(255,255,255,0.08);
  border-color: rgba(255,255,255,0.4);
  color: #fff;
}

/* â”€â”€ DISCLAIMER â”€â”€ */
.disclaimer {
  margin: 0;
  padding: 0.7rem 2rem;
  background: rgba(212,168,67,0.1);
  color: #d4a843;
  font-size: 0.85rem;
  border-bottom: 1px solid rgba(212,168,67,0.2);
  text-align: center;
  font-weight: 500;
  letter-spacing: 0.01em;
}

/* â”€â”€ MENU SWITCHER â”€â”€ */
.menu-switcher {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1.75rem;
  justify-content: center;
}

.mode-btn {
  padding: 0.65rem 1.75rem;
  border: 1.5px solid rgba(212,168,67,0.35);
  background: transparent;
  color: rgba(212,168,67,0.75);
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  font-family: inherit;
  font-size: 0.92rem;
  transition: all 0.25s ease;
  letter-spacing: 0.02em;
}

.mode-btn.active {
  background: linear-gradient(135deg, #d4a843, #b8872a);
  color: #1a1208;
  border-color: transparent;
  box-shadow: 0 6px 20px rgba(212,168,67,0.35);
}

.mode-btn:hover:not(.active) {
  background: rgba(212,168,67,0.1);
  border-color: rgba(212,168,67,0.6);
  color: #d4a843;
}

/* â”€â”€ CONTENT â”€â”€ */
.content {
  max-width: 1440px;
  margin: 0 auto;
  padding: 2rem;
}

.order-layout {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 2rem;
}

/* â”€â”€ SECTIONS HEADINGS â”€â”€ */
.products-section h2,
.order-summary h2 {
  margin: 0 0 1.25rem 0;
  color: #d4a843;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid rgba(212,168,67,0.2);
}

/* â”€â”€ CATEGORY FILTER â”€â”€ */
.category-filter {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 0.45rem 1rem;
  border: 1.5px solid rgba(255,255,255,0.15);
  background: rgba(255,255,255,0.04);
  color: rgba(255,255,255,0.6);
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.22s ease;
  font-size: 0.87rem;
  font-family: inherit;
  font-weight: 500;
}

.filter-btn:hover {
  border-color: rgba(212,168,67,0.5);
  color: #d4a843;
}

.filter-btn.active {
  background: linear-gradient(135deg, #d4a843, #b8872a);
  color: #1a1208;
  border-color: transparent;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(212,168,67,0.3);
}

/* â”€â”€ PRODUCTS GRID â”€â”€ */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
  gap: 1rem;
}

.loading {
  text-align: center;
  padding: 3rem;
  color: rgba(255,255,255,0.4);
  font-size: 0.95rem;
  letter-spacing: 0.04em;
}

/* â”€â”€ PRODUCT CARD â”€â”€ */
.product-card {
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  padding: 1.1rem;
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(0,0,0,0.5);
  border-color: rgba(212,168,67,0.3);
}

.product-card.out-of-stock {
  opacity: 0.45;
  filter: grayscale(40%);
}

.product-info h3 {
  margin: 0 0 0.45rem 0;
  color: #f0e6c8;
  font-size: 1rem;
  font-weight: 600;
}

.product-price {
  font-size: 1.1rem;
  font-weight: 700;
  color: #d4a843;
  margin: 0.4rem 0;
}

.product-category {
  font-size: 0.78rem;
  color: rgba(255,255,255,0.4);
  margin: 0.2rem 0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.stock-warning {
  color: #e74c3c;
  font-size: 0.8rem;
  margin: 0.2rem 0;
  font-weight: 600;
}

.stock-info {
  color: rgba(255,255,255,0.4);
  font-size: 0.8rem;
  margin: 0.2rem 0;
}

/* â”€â”€ QTY CONTROLS â”€â”€ */
.quantity-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
  margin-top: 0.9rem;
}

.qty-btn {
  width: 34px;
  height: 34px;
  border: 1.5px solid rgba(212,168,67,0.35);
  background: rgba(212,168,67,0.08);
  color: #d4a843;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1.2rem;
  font-weight: 700;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qty-btn:hover:not(:disabled) {
  background: linear-gradient(135deg, #d4a843, #b8872a);
  color: #1a1208;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(212,168,67,0.35);
}

.qty-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.quantity {
  min-width: 28px;
  text-align: center;
  font-weight: 700;
  font-size: 1rem;
  color: #f0e6c8;
}

/* â”€â”€ ORDER SUMMARY â”€â”€ */
.order-summary {
  background: rgba(255,255,255,0.055);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(212,168,67,0.18);
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 8px 40px rgba(0,0,0,0.4);
  position: sticky;
  top: 5rem;
  max-height: calc(100vh - 8rem);
  overflow-y: auto;
}

.order-summary::-webkit-scrollbar { width: 4px; }
.order-summary::-webkit-scrollbar-track { background: transparent; }
.order-summary::-webkit-scrollbar-thumb { background: rgba(212,168,67,0.3); border-radius: 2px; }

.empty-cart {
  text-align: center;
  padding: 2rem;
  color: rgba(255,255,255,0.35);
  font-style: italic;
  font-size: 0.9rem;
}

/* â”€â”€ ORDER ITEMS â”€â”€ */
.order-items {
  margin-bottom: 1.5rem;
  max-height: 360px;
  overflow-y: auto;
}

.order-items::-webkit-scrollbar { width: 3px; }
.order-items::-webkit-scrollbar-thumb { background: rgba(212,168,67,0.3); border-radius: 2px; }

.order-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.9rem 0;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}

.item-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.item-name {
  font-weight: 600;
  color: #f0e6c8;
  font-size: 0.95rem;
}

.item-price {
  font-size: 0.8rem;
  color: rgba(255,255,255,0.45);
  margin-top: 0.2rem;
}

.item-total {
  font-weight: 700;
  color: #d4a843;
  margin: 0 0.75rem;
  font-size: 0.95rem;
}

.item-discount-toggle {
  margin-top: 0.4rem;
}

.switch-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
  font-size: 0.78rem;
  color: rgba(255,255,255,0.45);
}

.switch-label input { cursor: pointer; }

.discounted-price {
  color: #27ae60;
  font-weight: 700;
}

.item-original-price {
  font-size: 0.72rem;
  text-decoration: line-through;
  color: rgba(255,255,255,0.3);
  margin-bottom: 2px;
}

.remove-btn {
  background: rgba(231,76,60,0.15);
  color: #e74c3c;
  border: 1px solid rgba(231,76,60,0.3);
  border-radius: 50%;
  width: 28px;
  height: 28px;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-btn:hover {
  background: #e74c3c;
  color: #fff;
  border-color: #e74c3c;
}

/* â”€â”€ ORDER TOTAL â”€â”€ */
.order-total {
  border-top: 1px solid rgba(255,255,255,0.1);
  padding-top: 1rem;
  margin-bottom: 1.25rem;
}

.discount-settings {
  margin-bottom: 0.9rem;
  padding-bottom: 0.9rem;
  border-bottom: 1px dashed rgba(255,255,255,0.1);
}

.global-discount-toggle {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  cursor: pointer;
  font-size: 0.88rem;
  color: rgba(255,255,255,0.65);
  font-weight: 500;
}

.global-discount-toggle input {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #d4a843;
}

.total-row {
  display: flex;
  justify-content: space-between;
  font-size: 1.2rem;
  font-weight: 700;
  color: #f0e6c8;
  margin-top: 0.5rem;
}

.total-amount { color: #d4a843; }

.total-row.subtotal,
.total-row.discount {
  font-size: 0.88rem;
  font-weight: 500;
  margin-bottom: 0.2rem;
  color: rgba(255,255,255,0.45);
}

.total-row.discount .discount-amount { color: #e74c3c; }
.subtotal-amount { color: rgba(255,255,255,0.6); }

/* â”€â”€ SUBMIT BTN â”€â”€ */
.submit-btn {
  width: 100%;
  padding: 1rem;
  background: linear-gradient(135deg, #27ae60, #1e8449);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 1.05rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.25s ease;
  letter-spacing: 0.03em;
  box-shadow: 0 6px 20px rgba(39,174,96,0.3);
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(39,174,96,0.45);
}

.submit-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
}

/* â”€â”€ CONFIRM MODAL â”€â”€ */
.confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  padding: 1rem;
}

.confirm-card {
  background: #1a1612;
  border: 1px solid rgba(212,168,67,0.2);
  border-radius: 18px;
  padding: 2rem;
  max-width: 480px;
  width: 100%;
  box-shadow: 0 24px 80px rgba(0,0,0,0.8);
  animation: fadeUp 0.25s ease-out;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

.confirm-title {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
  color: #d4a843;
  font-weight: 700;
}

.confirm-subtitle {
  margin: 0 0 1.25rem 0;
  font-size: 0.88rem;
  color: rgba(255,255,255,0.5);
}

.confirm-items {
  max-height: 240px;
  overflow-y: auto;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  margin-bottom: 1.25rem;
  background: rgba(255,255,255,0.03);
}

.confirm-items::-webkit-scrollbar { width: 3px; }
.confirm-items::-webkit-scrollbar-thumb { background: rgba(212,168,67,0.3); }

.confirm-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.65rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}

.confirm-item:last-child { border-bottom: none; }

.confirm-item-name {
  flex: 1;
  font-weight: 500;
  color: #f0e6c8;
  font-size: 0.92rem;
}

.confirm-item-qty {
  margin: 0 0.75rem;
  color: rgba(255,255,255,0.4);
  font-size: 0.88rem;
}

.confirm-item-total {
  font-weight: 700;
  color: #d4a843;
  font-size: 0.92rem;
}

.confirm-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
  color: rgba(255,255,255,0.7);
}

.confirm-total-row.subtotal { font-size: 0.88rem; color: rgba(255,255,255,0.4); font-weight: 500; }
.confirm-total-row.discount { font-size: 0.88rem; color: rgba(231,76,60,0.8); font-weight: 500; }
.confirm-total-amount { color: #d4a843; font-size: 1.15rem; font-weight: 800; }

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.25rem;
}

.btn-secondary,
.btn-primary {
  min-width: 110px;
  padding: 0.65rem 1.25rem;
  border-radius: 10px;
  border: none;
  cursor: pointer;
  font-size: 0.92rem;
  font-weight: 600;
  font-family: inherit;
  transition: all 0.2s ease;
}

.btn-secondary {
  background: rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.7);
  border: 1px solid rgba(255,255,255,0.15);
}

.btn-secondary:hover:not(:disabled) {
  background: rgba(255,255,255,0.13);
  color: #fff;
}

.btn-primary {
  background: linear-gradient(135deg, #27ae60, #1e8449);
  color: #fff;
  box-shadow: 0 4px 14px rgba(39,174,96,0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(39,174,96,0.45);
}

.btn-secondary:disabled,
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

/* â”€â”€ TOAST â”€â”€ */
.message {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  padding: 1rem 1.5rem;
  border-radius: 12px;
  color: #fff;
  z-index: 2000;
  animation: slideIn 0.3s ease-out;
  box-shadow: 0 8px 32px rgba(0,0,0,0.5);
  font-weight: 600;
  font-size: 0.92rem;
  backdrop-filter: blur(8px);
}

.message.success { background: linear-gradient(135deg,#27ae60,#1e8449); }
.message.error   { background: linear-gradient(135deg,#e74c3c,#c0392b); }

@keyframes slideIn {
  from { transform: translateX(110%); opacity: 0; }
  to   { transform: translateX(0);    opacity: 1; }
}

/* â”€â”€ RESPONSIVE â”€â”€ */
@media (max-width: 1024px) {
  .order-layout { grid-template-columns: 1fr; }
  .order-summary { position: relative; top: 0; max-height: none; }
}

@media (max-width: 768px) {
  .header { padding: 1rem; flex-wrap: wrap; gap: 0.75rem; }
  .header h1 { font-size: 1.1rem; flex: 1; }
  .content { padding: 1rem; }
  .products-grid { grid-template-columns: repeat(auto-fill, minmax(145px,1fr)); gap: 0.75rem; }
  .order-summary { padding: 1rem; }
  .message { bottom: 1rem; right: 1rem; left: 1rem; }
}

@media (max-width: 480px) {
  .header { padding: 0.75rem; }
  .content { padding: 0.75rem; }
  .products-grid { grid-template-columns: repeat(2,1fr); gap: 0.5rem; }
  .product-card { padding: 0.75rem; }
}
</style>

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
                <p class="product-price">{{ formatPrice(product.price) }}</p>
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
                <span class="item-price">{{ formatPrice(item.product.price) }} × {{ item.quantity }}</span>
                
                <!-- Individual Discount Toggle (Only if global is off) -->
                <div v-if="!isGlobalDiscount" class="item-discount-toggle">
                  <label class="switch-label">
                    <input 
                      type="checkbox" 
                      v-model="item.hasDiscount"
                    >
                    <span class="switch-text">Remise 16.7%</span>
                  </label>
                </div>
              </div>
              <div class="item-total">
                <div v-if="item.hasDiscount && !isGlobalDiscount" class="item-original-price">
                  {{ formatPrice(item.product.price * item.quantity) }}
                </div>
                <div :class="{ 'discounted-price': item.hasDiscount && !isGlobalDiscount }">
                  {{ formatPrice(item.hasDiscount && !isGlobalDiscount ? (item.product.price * item.quantity * (1 - ORDER_PERCENTAGE / 100)) : (item.product.price * item.quantity)) }}
                </div>
              </div>
              <button @click="removeItem(item.product.id)" class="remove-btn">×</button>
            </div>
          </div>

          <div class="order-total">
            <div class="discount-settings">
              <label class="global-discount-toggle">
                <input type="checkbox" v-model="isGlobalDiscount">
                <span class="toggle-text">Appliquer remise 16.7% sur TOUTE la commande</span>
              </label>
            </div>
            
            <div class="total-row subtotal">
              <span>Total Brut:</span>
              <span class="subtotal-amount">{{ formatPrice(totalBeforeDecrease) }}</span>
            </div>
            <div v-if="isGlobalDiscount" class="total-row discount">
              <span>Remise Globale ({{ ORDER_PERCENTAGE }}%):</span>
              <span class="discount-amount">- {{ formatPrice(totalBeforeDecrease * (ORDER_PERCENTAGE / 100)) }}</span>
            </div>
            <div v-else-if="totalDiscountAmount > 0" class="total-row discount">
              <span>Total Remises:</span>
              <span class="discount-amount">- {{ formatPrice(totalDiscountAmount) }}</span>
            </div>
            <div class="total-row">
              <span>Total à payer:</span>
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
            <span class="confirm-item-total">{{ formatPrice(item.product.price * item.quantity) }}</span>
          </div>
        </div>

        <div class="confirm-total-row subtotal">
          <span>Total Brut</span>
          <span>{{ formatPrice(totalBeforeDecrease) }}</span>
        </div>
        <div v-if="isGlobalDiscount" class="confirm-total-row discount">
          <span>Remise Globale ({{ ORDER_PERCENTAGE }}%)</span>
          <span>- {{ formatPrice(totalBeforeDecrease * (ORDER_PERCENTAGE / 100)) }}</span>
        </div>
        <div v-else-if="totalDiscountAmount > 0" class="confirm-total-row discount">
          <span>Total Remises</span>
          <span>- {{ formatPrice(totalDiscountAmount) }}</span>
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
const ORDER_PERCENTAGE = 16.7
const isGlobalDiscount = ref(true)

const showConfirmModal = ref(false)

const filteredProducts = computed(() => {
  if (!selectedCategory.value) {
    return products.value
  }
  return products.value.filter(p => p.category_id === selectedCategory.value)
})

const totalBeforeDecrease = computed(() => {
  return orderItems.value.reduce((sum, item) => {
    return sum + (item.product.price * item.quantity)
  }, 0)
})

const totalDiscountAmount = computed(() => {
  if (isGlobalDiscount.value) {
    return totalBeforeDecrease.value * (ORDER_PERCENTAGE / 100)
  }
  return orderItems.value.reduce((sum, item) => {
    if (item.hasDiscount) {
      return sum + (item.product.price * item.quantity * (ORDER_PERCENTAGE / 100))
    }
    return sum
  }, 0)
})

const totalAmount = computed(() => {
  return totalBeforeDecrease.value - totalDiscountAmount.value
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
    orderItems.value.push({ product, quantity: 1, hasDiscount: true })
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
    showConfirm('Aucun produit sélectionné pour la commande.', 'error')
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
        const hasDiscount = isGlobalDiscount.value || item.hasDiscount
        return {
          product_id: item.product.id,
          quantity: item.quantity,
          price: hasDiscount ? (item.product.price * (1 - ORDER_PERCENTAGE / 100)) : item.product.price,
          percent_decrease: hasDiscount ? ORDER_PERCENTAGE : 0,
          total_before_decrease: item.product.price * item.quantity
        }
      }),
      total: totalAmount.value,
      percent_decrease: isGlobalDiscount.value ? ORDER_PERCENTAGE : 0,
      total_before_decrease: totalBeforeDecrease.value
    }
    
    // Always include employee_id if it exists (even if 0, though unlikely)
    // Convert to number to ensure it's a valid integer
    if (props.employeeId !== null && props.employeeId !== undefined) {
      const empId = Number(props.employeeId)
      if (!isNaN(empId)) {
        orderData.employee_id = empId
        console.log('✅ Adding employee_id to order:', orderData.employee_id, 'Type:', typeof orderData.employee_id)
      } else {
        console.warn('⚠️ Employee ID is not a valid number:', props.employeeId)
      }
    } else {
      console.warn('⚠️ Employee ID is null or undefined. Value:', props.employeeId)
    }
    
    console.log('📦 Order data being sent:', JSON.stringify(orderData, null, 2))

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
  console.log('🔍 employeeId prop changed:', { old: oldVal, new: newVal, type: typeof newVal })
}, { immediate: true })

onMounted(async () => {
  console.log('📄 OrderPage mounted')
  console.log('👤 employeeId prop:', props.employeeId, 'Type:', typeof props.employeeId)
  console.log('📋 All props:', { selectedTable: props.selectedTable, employeeId: props.employeeId })
  await fetchProducts()
  await fetchCategories()
  await loadExistingOrder()
})
</script>

<style scoped>
.order-container {
  min-height: 100vh;
  background-image: url('../../assets/bguser.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  overflow-y: auto;
  overflow-x: hidden;
}

.header {
  background: white;
  padding: 1.5rem 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header h1 {
  margin: 0;
  color: #2c3e50;
  font-size: 1.5rem;
}

.disclaimer {
  margin: 0;
  padding: 0.75rem 2rem;
  background: #fff3cd;
  color: #856404;
  font-size: 0.9rem;
  border-bottom: 1px solid #f1c40f;
  text-align: center;
  font-weight: 500;
}

.back-btn {
  padding: 0.75rem 1.5rem;
  background: #95a5a6;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.back-btn:hover {
  background: #7f8c8d;
}

.content {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}

.order-layout {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 2rem;
}

.products-section h2,
.order-summary h2 {
  margin: 0 0 1.5rem 0;
  color: #2c3e50;
}

.category-filter {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 0.5rem 1rem;
  border: 2px solid #ddd;
  background: white;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.9rem;
}

.filter-btn:hover {
  border-color: #3498db;
}

.filter-btn.active {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.product-card {
  background: white;
  border-radius: 8px;
  padding: 1rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.product-card:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.product-card.out-of-stock {
  opacity: 0.6;
}

.product-info h3 {
  margin: 0 0 0.5rem 0;
  color: #2c3e50;
  font-size: 1.1rem;
}

.product-price {
  font-size: 1.2rem;
  font-weight: bold;
  color: #27ae60;
  margin: 0.5rem 0;
}

.product-category {
  font-size: 0.85rem;
  color: #7f8c8d;
  margin: 0.25rem 0;
}

.stock-warning {
  color: #e74c3c;
  font-size: 0.85rem;
  margin: 0.25rem 0;
  font-weight: 600;
}

.stock-info {
  color: #7f8c8d;
  font-size: 0.85rem;
  margin: 0.25rem 0;
}

.quantity-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
  margin-top: 1rem;
}

.qty-btn {
  width: 32px;
  height: 32px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1.2rem;
  transition: all 0.3s ease;
}

.qty-btn:hover:not(:disabled) {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

.qty-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.quantity {
  min-width: 30px;
  text-align: center;
  font-weight: 600;
}

.order-summary {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 2rem;
  max-height: calc(100vh - 4rem);
  overflow-y: auto;
}

.empty-cart {
  text-align: center;
  padding: 2rem;
  color: #7f8c8d;
}

.order-items {
  margin-bottom: 1.5rem;
  max-height: 400px;
  overflow-y: auto;
}

.order-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid #e0e0e0;
}

.item-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.item-name {
  font-weight: 600;
  color: #2c3e50;
}

.item-price {
  font-size: 0.85rem;
  color: #7f8c8d;
}

.item-total {
  font-weight: 600;
  color: #27ae60;
  margin: 0 1rem;
}

.remove-btn {
  background: #e74c3c;
  color: white;
  border: none;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  cursor: pointer;
  font-size: 1.2rem;
  line-height: 1;
}

.remove-btn:hover {
  background: #c0392b;
}

.order-total {
  border-top: 2px solid #e0e0e0;
  padding-top: 1rem;
  margin-bottom: 1.5rem;
}

.total-row {
  display: flex;
  justify-content: space-between;
  font-size: 1.3rem;
  font-weight: bold;
  color: #2c3e50;
}

.total-amount {
  color: #27ae60;
}

.total-row.subtotal,
.total-row.discount {
  font-size: 0.95rem;
  font-weight: 500;
  margin-bottom: 0.25rem;
  color: #7f8c8d;
}

.total-row.discount .discount-amount {
  color: #e74c3c;
}

.discount-settings {
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px dashed #ddd;
}

.global-discount-toggle {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  font-size: 0.95rem;
  color: #2c3e50;
  font-weight: 600;
}

.global-discount-toggle input {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.item-discount-toggle {
  margin-top: 0.5rem;
}

.switch-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.8rem;
  color: #7f8c8d;
}

.switch-label input {
  cursor: pointer;
}

.discounted-price {
  color: #27ae60;
  font-weight: bold;
}

.item-original-price {
  font-size: 0.75rem;
  text-decoration: line-through;
  color: #95a5a6;
  margin-bottom: 2px;
}

.submit-btn {
  width: 100%;
  padding: 1rem;
  background: #27ae60;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.submit-btn:hover:not(:disabled) {
  background: #229954;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
}

.confirm-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 1.5rem 2rem;
  max-width: 480px;
  width: 90%;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.confirm-title {
  margin: 0 0 0.5rem 0;
  font-size: 1.3rem;
  color: #2c3e50;
}

.confirm-subtitle {
  margin: 0 0 1rem 0;
  font-size: 0.9rem;
  color: #7f8c8d;
}

.confirm-items {
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid #ecf0f1;
  border-radius: 8px;
  margin-bottom: 1rem;
}

.confirm-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.6rem 0.9rem;
  border-bottom: 1px solid #f2f2f2;
}

.confirm-item:last-child {
  border-bottom: none;
}

.confirm-item-name {
  flex: 1;
  font-weight: 500;
  color: #2c3e50;
}

.confirm-item-qty {
  margin: 0 0.5rem;
  color: #7f8c8d;
}

.confirm-item-total {
  font-weight: 600;
  color: #27ae60;
}

.confirm-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 1.2rem;
  color: #2c3e50;
}

.confirm-total-amount {
  color: #27ae60;
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-secondary,
.btn-primary {
  min-width: 110px;
  padding: 0.55rem 1.1rem;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 600;
  transition: all 0.2s ease;
}

.btn-secondary {
  background: #ecf0f1;
  color: #2c3e50;
}

.btn-secondary:hover:not(:disabled) {
  background: #d0d7de;
}

.btn-primary {
  background: #27ae60;
  color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
  background: #219150;
}

.btn-secondary:disabled,
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.loading {
  text-align: center;
  padding: 3rem;
  color: #7f8c8d;
}

.message {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  padding: 1rem 1.5rem;
  border-radius: 6px;
  color: white;
  z-index: 1001;
  animation: slideIn 0.3s ease-out;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  border-left: 4px solid rgba(0,0,0,0.15);
}

.message.success {
  background: #27ae60;
  border-left-color: #1e8449;
}

.message.error {
  background: #e74c3c;
  border-left-color: #c0392b;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@media (max-width: 1024px) {
  .order-layout {
    grid-template-columns: 1fr;
  }
  
  .order-summary {
    position: relative;
    top: 0;
    max-height: none;
  }
}

@media (max-width: 768px) {
  .order-container {
    min-height: 100vh;
  }

  .header {
    padding: 1rem;
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  .header h1 {
    font-size: 1.2rem;
    flex: 1;
    min-width: 200px;
  }

  .back-btn {
    padding: 0.6rem 1rem;
    font-size: 0.9rem;
  }

  .content {
    padding: 1rem;
  }

  .products-section h2,
  .order-summary h2 {
    font-size: 1.3rem;
    margin-bottom: 1rem;
  }

  .category-filter {
    gap: 0.4rem;
    margin-bottom: 1rem;
  }

  .filter-btn {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
  }

  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.75rem;
  }

  .product-card {
    padding: 0.75rem;
  }

  .product-info h3 {
    font-size: 1rem;
  }

  .product-price {
    font-size: 1rem;
  }

  .product-category,
  .stock-info,
  .stock-warning {
    font-size: 0.8rem;
  }

  .quantity-controls {
    margin-top: 0.75rem;
    gap: 0.4rem;
  }

  .qty-btn {
    width: 28px;
    height: 28px;
    font-size: 1rem;
  }

  .order-summary {
    padding: 1rem;
  }

  .order-items {
    max-height: 300px;
  }

  .order-item {
    padding: 0.75rem;
  }

  .item-name {
    font-size: 0.95rem;
  }

  .item-price {
    font-size: 0.8rem;
  }

  .item-total {
    font-size: 0.95rem;
    margin: 0 0.5rem;
  }

  .remove-btn {
    width: 24px;
    height: 24px;
    font-size: 1rem;
  }

  .total-row {
    font-size: 1.1rem;
  }

  .submit-btn {
    padding: 0.875rem;
    font-size: 1rem;
  }

  .message {
    bottom: 1rem;
    right: 1rem;
    left: 1rem;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
  }
}

@media (max-width: 480px) {
  .header {
    padding: 0.75rem;
  }

  .header h1 {
    font-size: 1rem;
  }

  .back-btn {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
  }

  .content {
    padding: 0.75rem;
  }

  .products-section h2,
  .order-summary h2 {
    font-size: 1.1rem;
  }

  .category-filter {
    gap: 0.3rem;
  }

  .filter-btn {
    padding: 0.35rem 0.7rem;
    font-size: 0.8rem;
  }

  .products-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
  }

  .product-card {
    padding: 0.6rem;
  }

  .product-info h3 {
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
  }

  .product-price {
    font-size: 0.9rem;
    margin: 0.3rem 0;
  }

  .qty-btn {
    width: 26px;
    height: 26px;
    font-size: 0.9rem;
  }

  .quantity {
    min-width: 25px;
    font-size: 0.9rem;
  }

  .order-summary {
    padding: 0.75rem;
  }

  .order-item {
    padding: 0.6rem;
    flex-wrap: wrap;
  }

  .item-info {
    flex: 1 1 100%;
    margin-bottom: 0.5rem;
  }

  .item-total {
    flex: 0 0 auto;
    margin: 0;
  }

  .remove-btn {
    flex: 0 0 auto;
  }

  .total-row {
    font-size: 1rem;
  }

  .submit-btn {
    padding: 0.75rem;
    font-size: 0.95rem;
  }
}
</style>


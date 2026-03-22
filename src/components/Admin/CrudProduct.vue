<template>
  <div class="crud-container">
    <!-- Tabs for Products and Ingredients -->
    <div class="tabs">
      <button 
        @click="activeTab = 'products'" 
        :class="['tab-btn', { active: activeTab === 'products' }]"
      >
        Produits
      </button>
    </div>

    <!-- Products Tab -->
    <div v-if="activeTab === 'products'" class="tab-content">
      <div class="crud-header">
        <div class="header-left">
          <button @click="openAddProductModal" class="btn btn-primary">
            <span>+</span> Ajouter Produit
          </button>
        </div>
        <div class="header-right">
          <div class="search-filters">
            <div class="search-box">
              <input 
                v-model="searchQuery" 
                type="text" 
                placeholder="Rechercher un produit..." 
                class="search-input"
              />
              <span class="search-icon">🔍</span>
            </div>
            <select v-model="selectedCategory" class="filter-select">
              <option value="">Toutes les catégories</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nom</th>
              <th>Prix Standard</th>
              <th>Prix Étranger</th>
              <th>Catégorie</th>
              <th>Ingrédient du Stock</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingProducts">
              <td colspan="6" class="loading">Chargement...</td>
            </tr>
            <tr v-else-if="filteredProducts.length === 0">
              <td colspan="7" class="empty">Aucun produit trouvé</td>
            </tr>
            <tr v-else v-for="product in paginatedProducts" :key="product.id">
              <td>{{ product.id }}</td>
              <td>{{ product.name }}</td>
              <td>{{ formatPrice(product.price) }}</td>
              <td>{{ formatPrice(product.price_strangers) }}</td>
              <td>{{ product.category_name || '-' }}</td>
              <td>
                <span v-if="product.stock_ingredients && product.stock_ingredients.length > 0">
                  <span v-for="(ing, index) in product.stock_ingredients" :key="index" class="ingredient-tag">
                    {{ ing.stock_name || ing.name }} ({{ ing.quantity }} {{ ing.unit || '' }})<span v-if="index < product.stock_ingredients.length - 1">, </span>
                  </span>
                </span>
                <span v-else class="ingredient-count">-</span>
              </td>
              <td class="actions">
                <button @click="openEditProductModal(product)" class="btn btn-edit">Modifier</button>
                <button @click="confirmDeleteProduct(product)" class="btn btn-delete">Supprimer</button>
              </td>
            </tr>
          </tbody>
        </table>
    </div>

    <!-- Cards Container -->
    <div class="cards-container">
      <div v-if="loadingProducts" class="loading">Chargement...</div>
      <div v-else-if="filteredProducts.length === 0" class="empty">Aucun produit trouvé</div>
      <div v-else v-for="product in paginatedProducts" :key="product.id" class="data-card">
        <div class="card-header">
          <div class="card-id-wrapper">
            <span class="card-label">ID:</span>
            <span class="card-id">#{{ product.id }}</span>
          </div>
          <span class="badge badge-category">{{ product.category_name || 'Sans catégorie' }}</span>
        </div>
        
        <div class="card-body">
          <div class="info-row">
            <span class="info-label">Nom</span>
            <span class="info-value">{{ product.name }}</span>
          </div>
          <div class="card-prices">
            <div class="info-row">
              <span class="info-label">Prix Std</span>
              <span class="info-value price">{{ formatPrice(product.price) }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Prix Étranger</span>
              <span class="info-value price">{{ formatPrice(product.price_strangers) }}</span>
            </div>
          </div>
          <div class="info-row" v-if="product.stock_ingredients && product.stock_ingredients.length > 0">
            <span class="info-label">Ingrédients</span>
            <div class="ingredient-tags">
              <span v-for="(ing, index) in product.stock_ingredients" :key="index" class="ingredient-tag">
                {{ ing.stock_name || ing.name }}
              </span>
            </div>
          </div>
        </div>
        
        <div class="card-actions">
          <button @click="openEditProductModal(product)" class="btn btn-edit">
            <span>✏️</span> Modifier
          </button>
          <button @click="confirmDeleteProduct(product)" class="btn btn-delete">
            <span>🗑️</span> Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination-container" v-if="totalPages > 1">
      <div class="pagination-info">
        Page {{ currentPage }} sur {{ totalPages }}
      </div>
      <div class="pagination-controls">
        <button 
          @click="setPage(currentPage - 1)" 
          :disabled="currentPage === 1" 
          class="page-btn prev-btn"
        >
          &laquo; Précédent
        </button>
        <div class="page-numbers">
          <button 
            v-for="page in totalPages" 
            :key="page" 
            @click="setPage(page)" 
            :class="['page-btn', { active: currentPage === page }]"
          >
            {{ page }}
          </button>
        </div>
        <button 
          @click="setPage(currentPage + 1)" 
          :disabled="currentPage === totalPages" 
          class="page-btn next-btn"
        >
          Suivant &raquo;
        </button>
      </div>
    </div>
    </div>

    <!-- Product Add/Edit Modal -->
    <div v-if="showProductModal" class="modal-overlay" @click="closeProductModal">
      <div class="modal-content large-modal" @click.stop>
        <div class="modal-header">
          <h2>{{ editingProduct ? 'Modifier Produit' : 'Ajouter Produit' }}</h2>
          <button @click="closeProductModal" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="saveProduct" class="modal-body">
          <div class="form-group">
            <label>Nom *</label>
            <input v-model="productForm.name" type="text" required />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Prix Standard *</label>
              <input v-model.number="productForm.price" type="number" step="0.01" min="0" required />
            </div>
            <div class="form-group">
              <label>Prix Étranger *</label>
              <input v-model.number="productForm.price_strangers" type="number" step="0.01" min="0" required />
            </div>
          </div>
          <div class="form-group">
            <label>Catégorie</label>
            <select v-model="productForm.category_id">
              <option value="">Aucune catégorie</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label class="checkbox-label">
                <input 
                  type="checkbox" 
                v-model="productForm.needsIngredient"
                @change="onNeedsIngredientChange"
                />
              <span>Ce produit nécessite un ingrédient du stock</span>
                </label>
            </div>
            
          <div v-if="productForm.needsIngredient" class="ingredient-stock-section">
              <div class="section-header">
              <p class="section-label">Ingrédients du stock:</p>
              <button type="button" @click="addStockIngredient" class="btn btn-small">
                + Ajouter un ingrédient
                </button>
              </div>
              
              <div 
              v-for="(stockIng, index) in productForm.stockIngredients" 
                :key="index"
              class="stock-ingredient-item"
            >
              <div class="form-row">
                <div class="form-group">
                  <label>Ingrédient *</label>
                  <select 
                    v-model="stockIng.stockIngredientId" 
                    required 
                    :disabled="loadingStock"
                    @change="onStockIngredientChange(index)"
                  >
                    <option value="">Sélectionner un ingrédient</option>
                    <option 
                      v-for="stockItem in getAvailableStockItems(stockIng.stockIngredientId)" 
                      :key="stockItem.id" 
                      :value="stockItem.id"
              >
                      {{ stockItem.name }} ({{ stockItem.quantity }} {{ stockItem.unit }})
                    </option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Quantité nécessaire *</label>
                <input 
                    v-model.number="stockIng.quantity" 
                  type="number" 
                  step="0.01"
                  min="0"
                    required
                    :disabled="!stockIng.stockIngredientId"
                    placeholder="Quantité"
                />
                </div>
                <div class="form-group remove-btn-group">
                  <label>&nbsp;</label>
                <button 
                  type="button" 
                    @click="removeStockIngredient(index)"
                  class="btn btn-small btn-remove"
                    :disabled="productForm.stockIngredients.length === 1"
                >
                    × Supprimer
                </button>
                </div>
              </div>
              </div>
              
            <p v-if="productForm.stockIngredients.length === 0" class="hint-text">
              Cliquez sur "+ Ajouter un ingrédient" pour ajouter un ingrédient du stock
              </p>
          </div>
          <div class="modal-footer">
            <button type="button" @click="closeProductModal" class="btn btn-cancel">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="savingProduct">
              {{ savingProduct ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modals -->
    <div v-if="showDeleteProductModal" class="modal-overlay" @click="cancelDeleteProduct">
      <div class="modal-content delete-modal" @click.stop>
        <h3>Confirmer la suppression</h3>
        <p>Êtes-vous sûr de vouloir supprimer le produit <strong>{{ productToDelete?.name }}</strong> ?</p>
        <div class="modal-footer">
          <button @click="cancelDeleteProduct" class="btn btn-cancel">Annuler</button>
          <button @click="deleteProduct" class="btn btn-delete" :disabled="deletingProduct">
            {{ deletingProduct ? 'Suppression...' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Message -->
    <div v-if="message" :class="['message', messageType]">
      {{ message }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { getApiUrl, API_ENDPOINTS } from '../../config/api.js'

const API_URL = getApiUrl(API_ENDPOINTS.PRODUCT)
const STOCK_API_URL = getApiUrl(API_ENDPOINTS.STOCK)

const activeTab = ref('products')
const products = ref([])
const ingredients = ref([])
const stockItems = ref([])
const categories = ref([])
const loadingProducts = ref(false)
const loadingIngredients = ref(false)
const loadingStock = ref(false)
const showProductModal = ref(false)
const showIngredientModal = ref(false)
const showDeleteProductModal = ref(false)
const showDeleteIngredientModal = ref(false)
const editingProduct = ref(null)
const editingIngredient = ref(null)
const productToDelete = ref(null)
const ingredientToDelete = ref(null)
const savingProduct = ref(false)
const savingIngredient = ref(false)
const deletingProduct = ref(false)
const deletingIngredient = ref(false)
const message = ref('')
const messageType = ref('')
const searchQuery = ref('')
const selectedCategory = ref('')
const currentPage = ref(1)
const itemsPerPage = 8

const productForm = ref({
  name: '',
  price: 0,
  price_strangers: 0,
  quantity: 0,
  category_id: '',
  needsIngredient: false,
  stockIngredients: [] // Array of {stockIngredientId, quantity}
})

const ingredientForm = ref({
  name: '',
  weight: 0
})

const filteredProducts = computed(() => {
  return products.value.filter(product => {
    const matchesSearch = product.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesCategory = !selectedCategory.value || Number(product.category_id) === Number(selectedCategory.value)
    return matchesSearch && matchesCategory
  })
})

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredProducts.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(filteredProducts.value.length / itemsPerPage))

const setPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    const container = document.querySelector('.crud-container')
    if (container) container.scrollIntoView({ behavior: 'smooth' })
  }
}

watch([searchQuery, selectedCategory], () => {
  currentPage.value = 1
})

const fetchProducts = async () => {
  loadingProducts.value = true
  try {
    const response = await fetch(API_URL)
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
    const data = await response.json()
    if (data.success) {
      products.value = Array.isArray(data.data) ? data.data : []
    } else {
      showMessage('Erreur lors du chargement des produits', 'error')
      products.value = []
    }
  } catch (error) {
    showMessage('Erreur de connexion: ' + error.message, 'error')
    products.value = []
  } finally {
    loadingProducts.value = false
  }
}

const fetchIngredients = async () => {
  loadingIngredients.value = true
  try {
    const response = await fetch(`${API_URL}?type=ingredient`)
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
    const data = await response.json()
    if (data.success) {
      ingredients.value = Array.isArray(data.data) ? data.data : []
    } else {
      showMessage('Erreur lors du chargement des ingrédients', 'error')
      ingredients.value = []
    }
  } catch (error) {
    showMessage('Erreur de connexion: ' + error.message, 'error')
    ingredients.value = []
  } finally {
    loadingIngredients.value = false
  }
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

const fetchStockItems = async () => {
  loadingStock.value = true
  try {
    const response = await fetch(STOCK_API_URL)
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
      const data = await response.json()
      if (data.success) {
      stockItems.value = Array.isArray(data.data) ? data.data : []
    } else {
      stockItems.value = []
    }
  } catch (error) {
    console.error('Error fetching stock items:', error)
    stockItems.value = []
  } finally {
    loadingStock.value = false
  }
}

const addStockIngredient = () => {
  productForm.value.stockIngredients.push({
    stockIngredientId: '',
    quantity: 0
  })
}

const removeStockIngredient = (index) => {
  if (productForm.value.stockIngredients.length > 1) {
    productForm.value.stockIngredients.splice(index, 1)
  }
}

const getAvailableStockItems = (currentSelectedId) => {
  // Show all stock items, but exclude already selected ones (except current one)
  const selectedIds = productForm.value.stockIngredients
    .map(ing => String(ing.stockIngredientId))
    .filter(id => id && id !== String(currentSelectedId))
  
  return stockItems.value.filter(item => !selectedIds.includes(String(item.id)))
}

const onStockIngredientChange = (index) => {
  // Reset quantity when ingredient changes
  productForm.value.stockIngredients[index].quantity = 0
}

const onNeedsIngredientChange = () => {
  if (!productForm.value.needsIngredient) {
    productForm.value.stockIngredients = []
  } else {
    // Add at least one ingredient slot when enabled
    if (productForm.value.stockIngredients.length === 0) {
      productForm.value.stockIngredients.push({
        stockIngredientId: '',
        quantity: 0
      })
}
  }
}


const openAddProductModal = async () => {
  editingProduct.value = null
  productForm.value = {
    name: '',
    price: 0,
    quantity: 0,
    category_id: '',
    needsIngredient: false,
    stockIngredients: []
  }
  await fetchStockItems()
  showProductModal.value = true
}

const openEditProductModal = async (product) => {
  editingProduct.value = product
  productForm.value = {
    name: product.name,
    price: product.price,
    price_strangers: product.price_strangers || 0,
    quantity: product.quantity,
    category_id: product.category_id || '',
    needsIngredient: product.stock_ingredients && product.stock_ingredients.length > 0,
    stockIngredients: product.stock_ingredients && product.stock_ingredients.length > 0
      ? product.stock_ingredients.map(ing => ({
          stockIngredientId: String(ing.stock_ingredient_id || ing.stock_id || ''),
          quantity: ing.quantity || 0
        }))
      : []
  }
  
  // Ensure at least one ingredient slot if needsIngredient is true
  if (productForm.value.needsIngredient && productForm.value.stockIngredients.length === 0) {
    productForm.value.stockIngredients.push({
      stockIngredientId: '',
      quantity: 0
    })
  }
  
  await fetchStockItems()
  showProductModal.value = true
}

const closeProductModal = () => {
  showProductModal.value = false
  editingProduct.value = null
  productForm.value = {
    name: '',
    price: 0,
    quantity: 0,
    category_id: '',
    needsIngredient: false,
    stockIngredients: []
  }
}

const saveProduct = async () => {
  savingProduct.value = true
  try {
    // Validate if ingredient is required
    if (productForm.value.needsIngredient) {
      if (productForm.value.stockIngredients.length === 0) {
        showMessage('Veuillez ajouter au moins un ingrédient du stock', 'error')
        savingProduct.value = false
        return
      }
      
      // Validate each ingredient
      for (let i = 0; i < productForm.value.stockIngredients.length; i++) {
        const ing = productForm.value.stockIngredients[i]
        if (!ing.stockIngredientId) {
          showMessage(`Veuillez sélectionner un ingrédient pour l'ingrédient ${i + 1}`, 'error')
          savingProduct.value = false
          return
        }
        if (!ing.quantity || ing.quantity <= 0) {
          showMessage(`Veuillez saisir une quantité valide pour l'ingrédient ${i + 1}`, 'error')
          savingProduct.value = false
          return
        }
      }
    }
    
    const url = editingProduct.value 
      ? `${API_URL}?id=${editingProduct.value.id}`
      : API_URL
    
    const method = editingProduct.value ? 'PUT' : 'POST'
    
    const requestData = {
      name: productForm.value.name.trim(),
      price: parseFloat(productForm.value.price),
      price_strangers: parseFloat(productForm.value.price_strangers),
      quantity: parseInt(productForm.value.quantity),
      category_id: productForm.value.category_id || null,
      stock_ingredients: productForm.value.needsIngredient 
        ? productForm.value.stockIngredients.map(ing => ({
            stock_ingredient_id: parseInt(ing.stockIngredientId),
            quantity: parseFloat(ing.quantity)
          }))
        : []
    }
    
    const response = await fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestData)
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: 'Erreur serveur' }))
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      showMessage(editingProduct.value ? 'Produit modifié avec succès' : 'Produit ajouté avec succès', 'success')
      closeProductModal()
      fetchProducts()
    } else {
      showMessage(data.message || 'Erreur lors de l\'enregistrement', 'error')
    }
  } catch (error) {
    showMessage('Erreur: ' + (error.message || 'Erreur de connexion'), 'error')
  } finally {
    savingProduct.value = false
  }
}

const confirmDeleteProduct = (product) => {
  productToDelete.value = product
  showDeleteProductModal.value = true
}

const cancelDeleteProduct = () => {
  showDeleteProductModal.value = false
  productToDelete.value = null
}

const deleteProduct = async () => {
  if (!productToDelete.value?.id) {
    showMessage('Erreur: ID de produit manquant', 'error')
    cancelDeleteProduct()
    return
  }
  
  deletingProduct.value = true
  try {
    const response = await fetch(`${API_URL}?id=${productToDelete.value.id}`, {
      method: 'DELETE'
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: 'Erreur serveur' }))
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      showMessage('Produit supprimé avec succès', 'success')
      cancelDeleteProduct()
      fetchProducts()
    } else {
      showMessage(data.message || 'Erreur lors de la suppression', 'error')
    }
  } catch (error) {
    showMessage('Erreur: ' + (error.message || 'Erreur de connexion'), 'error')
  } finally {
    deletingProduct.value = false
  }
}

const openAddIngredientModal = () => {
  editingIngredient.value = null
  ingredientForm.value = { name: '', weight: 0 }
  showIngredientModal.value = true
}

const openEditIngredientModal = (ingredient) => {
  editingIngredient.value = ingredient
  ingredientForm.value = {
    name: ingredient.name,
    weight: ingredient.weight
  }
  showIngredientModal.value = true
}

const closeIngredientModal = () => {
  showIngredientModal.value = false
  editingIngredient.value = null
  ingredientForm.value = { name: '', weight: 0 }
}

const saveIngredient = async () => {
  savingIngredient.value = true
  try {
    const url = editingIngredient.value 
      ? `${API_URL}?id=${editingIngredient.value.id}&type=ingredient`
      : `${API_URL}?type=ingredient`
    
    const method = editingIngredient.value ? 'PUT' : 'POST'
    
    const requestData = {
      name: ingredientForm.value.name.trim(),
      weight: parseFloat(ingredientForm.value.weight)
    }
    
    const response = await fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestData)
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: 'Erreur serveur' }))
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      showMessage(editingIngredient.value ? 'Ingrédient modifié avec succès' : 'Ingrédient ajouté avec succès', 'success')
      closeIngredientModal()
      fetchIngredients()
      if (activeTab.value === 'products') {
        fetchProducts() // Refresh products to update ingredient counts
      }
    } else {
      showMessage(data.message || 'Erreur lors de l\'enregistrement', 'error')
    }
  } catch (error) {
    showMessage('Erreur: ' + (error.message || 'Erreur de connexion'), 'error')
  } finally {
    savingIngredient.value = false
  }
}

const confirmDeleteIngredient = (ingredient) => {
  ingredientToDelete.value = ingredient
  showDeleteIngredientModal.value = true
}

const cancelDeleteIngredient = () => {
  showDeleteIngredientModal.value = false
  ingredientToDelete.value = null
}

const deleteIngredient = async () => {
  if (!ingredientToDelete.value?.id) {
    showMessage('Erreur: ID d\'ingrédient manquant', 'error')
    cancelDeleteIngredient()
    return
  }
  
  deletingIngredient.value = true
  try {
    const response = await fetch(`${API_URL}?id=${ingredientToDelete.value.id}&type=ingredient`, {
      method: 'DELETE'
    })
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: 'Erreur serveur' }))
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      showMessage('Ingrédient supprimé avec succès', 'success')
      cancelDeleteIngredient()
      fetchIngredients()
      if (activeTab.value === 'products') {
        fetchProducts()
      }
    } else {
      showMessage(data.message || 'Erreur lors de la suppression', 'error')
    }
  } catch (error) {
    showMessage('Erreur: ' + (error.message || 'Erreur de connexion'), 'error')
  } finally {
    deletingIngredient.value = false
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

onMounted(() => {
  fetchProducts()
  fetchIngredients()
  fetchCategories()
  fetchStockItems()
})
</script>

<style scoped>
.crud-container {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  border-bottom: 2px solid #e0e0e0;
}

.tab-btn {
  padding: 1rem 2rem;
  border: none;
  background: transparent;
  font-size: 1rem;
  font-weight: 600;
  color: #7f8c8d;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  transition: all 0.3s ease;
}

.tab-btn:hover {
  color: #2c3e50;
  background: #f8f9fa;
}

.tab-btn.active {
  color: #3498db;
  border-bottom-color: #3498db;
}

.tab-content {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.crud-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  gap: 1rem;
}

.header-left, .header-right {
  display: flex;
  align-items: center;
}

.search-filters {
  display: flex;
  gap: 1rem;
}

.search-box {
  position: relative;
  min-width: 250px;
}

.search-input, .filter-select {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: #f8fafc;
}

.filter-select {
  padding-left: 1rem;
  min-width: 180px;
  cursor: pointer;
}

.search-input:focus, .filter-select:focus {
  outline: none;
  border-color: #3498db;
  background: white;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.search-icon {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 1rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-primary {
  background: #3498db;
  color: white;
}

.btn-primary:hover {
  background: #2980b9;
}

.btn-edit {
  background: #f39c12;
  color: white;
  padding: 0.5rem 1rem;
  font-size: 0.9rem;
}

.btn-edit:hover {
  background: #e67e22;
}

.btn-delete {
  background: #e74c3c;
  color: white;
  padding: 0.5rem 1rem;
  font-size: 0.9rem;
}

.btn-delete:hover {
  background: #c0392b;
}

.btn-cancel {
  background: #95a5a6;
  color: white;
}

.btn-cancel:hover {
  background: #7f8c8d;
}

.table-container {
  overflow-x: auto;
}

.cards-container {
  display: none;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  background: #34495e;
  color: white;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
}

.data-table td {
  padding: 1rem;
  border-bottom: 1px solid #e0e0e0;
}

.data-table tr:hover {
  background: #f8f9fa;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.ingredient-count {
  color: #7f8c8d;
  font-size: 0.9rem;
}

.ingredient-tag {
  display: inline-block;
  margin-right: 0.25rem;
  font-size: 0.9rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: auto;
  cursor: pointer;
}

.checkbox-label span {
  font-weight: 600;
  color: #2c3e50;
  user-select: none;
}

.ingredient-stock-section {
  margin-top: 1rem;
  padding: 1rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  background: #f8f9fa;
}

.stock-ingredient-item {
  margin-bottom: 1rem;
  padding: 1rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  background: #fff;
}

.remove-btn-group {
  flex: 0 0 auto;
  min-width: 120px;
}

.remove-btn-group .btn {
  width: 100%;
}

.loading, .empty {
  text-align: center;
  padding: 2rem;
  color: #7f8c8d;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.large-modal {
  max-width: 700px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e0e0e0;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.5rem;
}

.close-btn {
  background: none;
  border: none;
  font-size: 2rem;
  cursor: pointer;
  color: #7f8c8d;
}

.close-btn:hover {
  color: #2c3e50;
}

.modal-body {
  padding: 1.5rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #2c3e50;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
  font-family: inherit;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #3498db;
}

.ingredients-select {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #ddd;
  border-radius: 6px;
  padding: 1rem;
  background: #f8f9fa;
  margin-bottom: 1.5rem;
}

.section-label {
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 0.75rem;
  font-size: 0.9rem;
}

.new-ingredients-section {
  margin-top: 1rem;
  padding: 1rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  background: #f8f9fa;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.btn-small {
  padding: 0.5rem 1rem;
  font-size: 0.9rem;
}

.btn-remove {
  background: #e74c3c;
  color: white;
  min-width: 40px;
  padding: 0.5rem;
}

.btn-remove:hover {
  background: #c0392b;
}

.new-ingredient-item {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  align-items: center;
}

.ingredient-name-input {
  flex: 2;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.ingredient-weight-input {
  flex: 1;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.hint-text {
  color: #7f8c8d;
  font-style: italic;
  font-size: 0.9rem;
  margin: 0.5rem 0 0 0;
}

.ingredient-checkbox {
  display: flex;
  align-items: center;
  padding: 0.5rem 0;
}

.ingredient-checkbox input[type="checkbox"] {
  width: auto;
  margin-right: 0.5rem;
}

.ingredient-checkbox label {
  margin: 0;
  font-weight: normal;
  cursor: pointer;
}

.no-ingredients {
  color: #7f8c8d;
  font-style: italic;
  margin: 0;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e0e0e0;
}

.delete-modal {
  padding: 2rem;
}

.delete-modal h3 {
  margin: 0 0 1rem 0;
}

.delete-modal p {
  margin: 0 0 2rem 0;
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
}

.message.success {
  background: #27ae60;
}

.message.error {
  background: #e74c3c;
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
  .large-modal {
    max-width: 90%;
  }
}

@media (max-width: 768px) {
  .crud-container {
    padding: 1rem;
  }

  .crud-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  .tabs {
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .tab-btn {
    flex: 1;
    min-width: 120px;
    font-size: 0.85rem;
    padding: 0.6rem 0.8rem;
  }

  .table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .data-table {
    min-width: 700px;
    font-size: 0.85rem;
  }

  .data-table th,
  .data-table td {
    padding: 0.5rem 0.4rem;
  }

  .actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .actions .btn {
    width: 100%;
    padding: 0.5rem;
    font-size: 0.85rem;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
  
  .large-modal {
    max-width: 95%;
    margin: 1rem;
  }

  .modal-header h2 {
    font-size: 1.2rem;
  }

  .form-group {
    margin-bottom: 1rem;
  }

  .ingredient-list {
    max-height: 200px;
  }

  .ingredient-item {
    padding: 0.5rem;
  }

  .modal-footer {
    flex-direction: column-reverse;
    gap: 0.75rem;
  }

  .modal-footer .btn {
    width: 100%;
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
  .crud-container {
    padding: 0.75rem;
  }

  .tabs {
    flex-direction: column;
  }

  .tab-btn {
    width: 100%;
  }

  .data-table {
    min-width: 600px;
    font-size: 0.75rem;
  }

  .data-table th,
  .data-table td {
    padding: 0.4rem 0.3rem;
  }

  .large-modal {
    width: 98%;
    margin: 0.5rem;
    padding: 1rem;
  }

  .modal-header h2 {
    font-size: 1rem;
  }

  .form-group label {
    font-size: 0.9rem;
  }

  .form-group input,
  .form-group select,
  .form-group textarea {
    padding: 0.6rem;
    font-size: 0.9rem;
  }

  .ingredient-input-row {
    flex-direction: column;
    gap: 0.5rem;
  }

  .ingredient-name-input,
  .ingredient-weight-input {
    width: 100%;
  }
}
.loading, .empty {
  text-align: center;
  padding: 2rem;
  color: #7f8c8d;
}

.pagination-container {
  margin-top: 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 1rem 0;
  border-top: 1px solid #f1f5f9;
}

.pagination-info {
  font-size: 0.9rem;
  color: #64748b;
  font-weight: 500;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-numbers {
  display: flex;
  gap: 0.35rem;
}

.page-btn {
  padding: 0.5rem 0.85rem;
  border: 1px solid #e2e8f0;
  background: white;
  color: #475569;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  min-width: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.page-btn:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #1e293b;
}

.page-btn.active {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f1f5f9;
}

.prev-btn, .next-btn {
  padding: 0.5rem 1rem;
}

@media (max-width: 768px) {
  .crud-container {
    padding: 1rem;
    background: #f8f9fa;
    box-shadow: none;
  }

  .crud-header {
    flex-direction: column-reverse;
    gap: 1.25rem;
    align-items: stretch;
  }

  .header-right, .search-filters, .search-box, .filter-select {
    width: 100%;
  }

  .search-filters {
    flex-direction: column;
    gap: 0.75rem;
  }

  .search-input, .filter-select {
    padding: 0.85rem 1rem 0.85rem 2.75rem;
    font-size: 1rem;
  }
  
  .filter-select {
    padding-left: 1rem;
  }

  .btn-primary {
    justify-content: center;
    width: 100%;
    padding: 1rem;
    font-weight: 600;
  }

  /* Hide table view */
  .table-container {
    display: none;
  }

  /* Show and style card view */
  .cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
  }

  .data-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #edf2f7;
    transition: transform 0.2s ease;
  }

  .data-card:active {
    transform: scale(0.98);
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    gap: 0.5rem;
  }

  .card-id-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .card-label {
    font-size: 0.75rem;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 700;
  }

  .card-id {
    font-family: monospace;
    font-weight: 700;
    color: #334155;
    background: #f1f5f9;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-size: 0.85rem;
  }

  .badge-category {
    background: #e0f2fe;
    color: #0369a1;
    font-size: 0.75rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
  }

  .card-body {
    margin-bottom: 1.25rem;
  }
  
  .card-prices {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin: 1rem 0;
    padding: 1rem 0;
    border-top: 1px solid #f8fafc;
    border-bottom: 1px solid #f8fafc;
  }

  .info-row {
    margin-bottom: 0.75rem;
  }

  .info-row:last-child {
    margin-bottom: 0;
  }

  .info-label {
    display: block;
    font-size: 0.7rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    margin-bottom: 0.25rem;
  }

  .info-value {
    display: block;
    color: #1e293b;
    font-weight: 500;
    font-size: 0.95rem;
  }
  
  .info-value.price {
    color: #27ae60;
    font-weight: 700;
  }

  .ingredient-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.4rem;
  }

  .ingredient-tag {
    background: #f1f5f9;
    color: #475569;
    font-size: 0.75rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    display: inline-block;
  }

  .card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
  }

  .card-actions .btn {
    width: 100%;
    margin: 0;
    padding: 0.65rem;
    font-size: 0.85rem;
    justify-content: center;
  }

  .modal-content {
    width: 95%;
    max-width: none !important;
    margin: 1rem;
    border-radius: 16px;
  }

  .form-row {
    flex-direction: column;
    gap: 0.5rem;
  }

  .modal-footer {
    flex-direction: column-reverse;
    gap: 0.75rem;
  }

  .modal-footer .btn {
    width: 100%;
  }

  .message {
    bottom: 1.5rem;
    left: 1rem;
    right: 1rem;
    width: auto;
    text-align: center;
  }
  
  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }

  .page-numbers {
    order: 3;
    width: 100%;
    justify-content: center;
    margin-top: 0.5rem;
  }
}

@media (max-width: 480px) {
  .card-prices {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
  
  .card-actions {
    grid-template-columns: 1fr;
  }
}
</style>


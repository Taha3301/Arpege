<template>
  <div class="history-container">
    <div class="history-header">
      <h2>Consommation des produits</h2>
    </div>

    <div class="filters">
      <div class="filter-group">
        <label>Produit</label>
        <select v-model="productId">
          <option value="">Tous</option>
          <option 
            v-for="prod in productOptions" 
            :key="prod.id" 
            :value="prod.id"
          >
            {{ prod.name }}
          </option>
        </select>
      </div>

      <div class="filter-group">
        <label>Par jour</label>
        <input type="date" v-model="dayFilter" />
      </div>

      <div class="filter-group">
        <label>Par mois</label>
        <input type="month" v-model="monthFilter" />
      </div>

      <div class="filter-group">
        <label>Par année</label>
        <input type="number" v-model="yearFilter" min="2000" max="2100" />
      </div>

      <div class="filter-group">
        <label class="checkbox-label">
          <input type="checkbox" v-model="showTotal" />
          <span>Afficher le total par produit</span>
        </label>
      </div>

      <button class="apply-btn" type="button" @click="fetchConsumption">
        Appliquer
      </button>
      <button class="reset-btn" type="button" @click="resetFilters">
        Réinitialiser
      </button>
    </div>

    <div v-if="loading" class="loading">Chargement de la consommation...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else class="table-wrapper">
      <table class="orders-table">
        <thead>
          <tr>
            <th v-if="!showTotal || !productId">Date / Heure</th>
            <th>Produit</th>
            <th>Quantité consommée</th>
            <th>Prix unitaire</th>
            <th>Revenu total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="`${row.product_id}-${row.consumption_date || 'total'}-${index}`" :class="{ 'total-row': showTotal && productId }">
            <td v-if="!showTotal || !productId">{{ formatDateTime(row.consumption_date, row.last_order_time) }}</td>
            <td><strong>{{ row.product_name }}</strong></td>
            <td><strong>{{ row.total_quantity_consumed }}</strong></td>
            <td>{{ formatPrice(row.product_price) }}</td>
            <td><strong>{{ formatPrice(row.total_revenue) }}</strong></td>
          </tr>
          <tr v-if="rows.length === 0">
            <td :colspan="showTotal && productId ? 4 : 5" class="no-results">
              Aucune consommation trouvée pour ces filtres.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { getApiUrl, API_ENDPOINTS } from '../../config/api.js'

const CONSUMPTION_API = getApiUrl(API_ENDPOINTS.PRODUCT_CONSUMPTION)
const PRODUCT_API = getApiUrl(API_ENDPOINTS.PRODUCT)

const loading = ref(false)
const error = ref('')

const productId = ref('')
const dayFilter = ref('')
const monthFilter = ref('')
const yearFilter = ref('')
const showTotal = ref(false)

const products = ref([])
const rawRows = ref([])

const productOptions = computed(() => products.value)

const rows = computed(() => {
  return rawRows.value
})

const fetchProducts = async () => {
  try {
    const resp = await fetch(PRODUCT_API)
    if (!resp.ok) return
    const data = await resp.json()
    if (data.success && Array.isArray(data.data)) {
      products.value = data.data
    }
  } catch (e) {
    console.error('Error fetching products list:', e)
  }
}

const fetchConsumption = async () => {
  loading.value = true
  error.value = ''
  rawRows.value = []

  try {
    const params = new URLSearchParams()
    if (productId.value) params.append('product_id', productId.value)
    if (dayFilter.value) {
      params.append('day', dayFilter.value)
    } else if (monthFilter.value) {
      params.append('month', monthFilter.value)
    } else if (yearFilter.value) {
      params.append('year', yearFilter.value)
    }
    if (showTotal.value && productId.value) {
      params.append('show_total', '1')
    }

    const url = `${CONSUMPTION_API}?${params.toString()}`
    const resp = await fetch(url)
    if (!resp.ok) {
      throw new Error(`HTTP error! status: ${resp.status}`)
    }
    const data = await resp.json()
    if (data.success) {
      rawRows.value = Array.isArray(data.data) ? data.data : []
    } else {
      error.value = data.message || 'Erreur lors du chargement de la consommation'
    }
  } catch (e) {
    console.error('Error fetching product consumption:', e)
    error.value = e.message || 'Erreur de connexion'
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  productId.value = ''
  dayFilter.value = ''
  monthFilter.value = ''
  yearFilter.value = ''
  showTotal.value = false
  fetchConsumption()
}

const formatPrice = (value) => {
  const n = Number(value) || 0
  return `${n.toFixed(2)} DT`
}

const formatDateTime = (date, lastOrderTime) => {
  if (!date) return ''
  // If we have last_order_time, show full datetime, otherwise just date
  if (lastOrderTime) {
    return new Date(lastOrderTime).toLocaleString('fr-FR', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    })
  }
  // Fallback to just date
  return new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
}

onMounted(async () => {
  await fetchProducts()
  await fetchConsumption()
})
</script>

<style scoped>
.history-container {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.history-header {
  margin-bottom: 1.5rem;
}

.history-header h2 {
  margin: 0;
  color: #2c3e50;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.filter-group label {
  font-size: 0.85rem;
  color: #7f8c8d;
}

.filter-group input,
.filter-group select {
  padding: 0.4rem 0.6rem;
  border-radius: 4px;
  border: 1px solid #dcdde1;
  font-size: 0.9rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  user-select: none;
}

.checkbox-label input[type="checkbox"] {
  width: auto;
  cursor: pointer;
}

.apply-btn,
.reset-btn {
  padding: 0.5rem 1rem;
  border-radius: 4px;
  border: none;
  cursor: pointer;
  font-size: 0.9rem;
}

.apply-btn {
  background: #3498db;
  color: white;
}

.apply-btn:hover {
  background: #2980b9;
}

.reset-btn {
  background: #bdc3c7;
  color: white;
}

.reset-btn:hover {
  background: #95a5a6;
}

.loading,
.error {
  padding: 1rem;
  text-align: center;
}

.error {
  color: #e74c3c;
}

.table-wrapper {
  overflow-x: auto;
}

.orders-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.orders-table th,
.orders-table td {
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #ecf0f1;
  text-align: left;
}

.orders-table th {
  background: #f9fbfc;
  font-weight: 600;
  color: #2c3e50;
}

.no-results {
  text-align: center;
  padding: 1rem;
  color: #7f8c8d;
}

.total-row {
  background: #f0f8ff;
  font-weight: 600;
}

.total-row td {
  border-top: 2px solid #3498db;
}

@media (max-width: 768px) {
  .history-container {
    padding: 1rem;
  }

  .history-header h2 {
    font-size: 1.3rem;
  }

  .filters {
    flex-direction: column;
    gap: 1rem;
  }

  .filter-group {
    width: 100%;
  }

  .filter-group label {
    font-size: 0.9rem;
  }

  .filter-group input,
  .filter-group select {
    width: 100%;
    padding: 0.5rem;
  }

  .checkbox-label {
    font-size: 0.9rem;
  }

  .apply-btn,
  .reset-btn {
    width: 100%;
    padding: 0.75rem;
  }

  .table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .orders-table {
    min-width: 500px;
    font-size: 0.85rem;
  }

  .orders-table th,
  .orders-table td {
    padding: 0.5rem 0.4rem;
  }
}

@media (max-width: 480px) {
  .history-container {
    padding: 0.75rem;
  }

  .history-header h2 {
    font-size: 1.1rem;
  }

  .orders-table {
    min-width: 400px;
    font-size: 0.75rem;
  }

  .orders-table th,
  .orders-table td {
    padding: 0.4rem 0.3rem;
  }
}
</style>


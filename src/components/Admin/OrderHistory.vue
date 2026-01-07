<template>
  <div class="history-container">
    <div class="history-header">
      <h2>Historique des commandes</h2>
    </div>

    <div class="filters">
      <div class="filter-group">
        <label>Employé</label>
        <select v-model="employeeFilter">
          <option value="">Tous</option>
          <option 
            v-for="emp in employeeOptions" 
            :key="emp.id" 
            :value="emp.id"
          >
            {{ emp.name || ('Employé ' + emp.id) }}
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
        <label>Recherche</label>
        <input 
          v-model="searchQuery" 
          type="text" 
          placeholder="ID commande, table..."
        />
      </div>

      <button class="reset-btn" type="button" @click="resetFilters">
        Réinitialiser
      </button>
    </div>

    <div v-if="loading" class="loading">Chargement des commandes...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else class="table-wrapper">
      <table class="orders-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Table</th>
            <th>Employé</th>
            <th>Date / Heure</th>
            <th>Total</th>
            <th>Statut</th>
            <th>Détails</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in filteredOrders" :key="order.id">
            <td>#{{ order.id }}</td>
            <td>Table {{ order.table_number || order.table_id }}</td>
            <td>{{ order.employee_name || ('Employé ' + (order.employee_id ?? '-')) }}</td>
            <td>{{ formatDateTime(order.order_time) }}</td>
            <td>{{ formatPrice(order.total) }}</td>
            <td>
              <span :class="['status-badge', order.status]">
                {{ order.status }}
              </span>
            </td>
            <td>
              <button 
                class="details-btn" 
                type="button" 
                @click="openDetails(order)"
              >
                Voir
              </button>
            </td>
          </tr>
          <tr v-if="filteredOrders.length === 0">
            <td colspan="7" class="no-results">
              Aucune commande trouvée pour ces filtres.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal détails commande -->
    <div 
      v-if="showDetailsModal && selectedOrder" 
      class="details-modal-overlay"
      @click.self="closeDetails"
    >
      <div class="details-modal">
        <div class="details-header">
          <h3>Commande #{{ selectedOrder.id }} - Table {{ selectedOrder.table_number || selectedOrder.table_id }}</h3>
          <button class="close-btn" type="button" @click="closeDetails">×</button>
        </div>
        <p class="details-subtitle">
          Employé : {{ selectedOrder.employee_name || ('Employé ' + (selectedOrder.employee_id ?? '-')) }}<br>
          Date : {{ formatDateTime(selectedOrder.order_time) }}
        </p>

        <div v-if="loadingDetails" class="loading">Chargement des produits...</div>
        <div v-else-if="detailsError" class="error">{{ detailsError }}</div>
        <div v-else>
          <table class="details-table">
            <thead>
              <tr>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Prix</th>
                <th>Sous-total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in orderItemsDetail" :key="item.id">
                <td>{{ item.product_name || ('Produit ' + item.product_id) }}</td>
                <td>{{ item.quantity }}</td>
                <td>{{ formatPrice(item.price) }}</td>
                <td>{{ formatPrice(item.price * item.quantity) }}</td>
              </tr>
              <tr v-if="orderItemsDetail.length === 0">
                <td colspan="4" class="no-results">
                  Aucun produit pour cette commande.
                </td>
              </tr>
            </tbody>
            <tfoot v-if="selectedOrder && orderItemsDetail.length > 0">
              <tr class="footer-row subtotal">
                <td colspan="3">Total Brut</td>
                <td>{{ formatPrice(selectedOrder.total_before_decrease || calculateRawTotal()) }}</td>
              </tr>
              <tr class="footer-row discount">
                <td colspan="3">Remise ({{ selectedOrder.percent_decrease || 16.7 }}%)</td>
                <td>- {{ formatPrice((selectedOrder.total_before_decrease || calculateRawTotal()) * ((selectedOrder.percent_decrease || 16.7) / 100)) }}</td>
              </tr>
              <tr class="footer-row total">
                <td colspan="3">Total Payé</td>
                <td>{{ formatPrice(selectedOrder.total) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getApiUrl, API_ENDPOINTS } from '../../config/api.js'

const API_URL = getApiUrl(API_ENDPOINTS.ORDER_HISTORY)
const ORDER_DETAIL_API = getApiUrl(API_ENDPOINTS.ORDER)

const orders = ref([])
const loading = ref(false)
const error = ref('')

const employeeFilter = ref('')
const dayFilter = ref('')
const monthFilter = ref('')
const yearFilter = ref('')
const searchQuery = ref('')

const showDetailsModal = ref(false)
const selectedOrder = ref(null)
const orderItemsDetail = ref([])
const loadingDetails = ref(false)
const detailsError = ref('')

const fetchOrders = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch(API_URL)
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    const data = await response.json()
    if (data.success) {
      orders.value = Array.isArray(data.data) ? data.data : []
    } else {
      error.value = data.message || 'Erreur lors du chargement des commandes'
      orders.value = []
    }
  } catch (err) {
    console.error('Error fetching orders history:', err)
    error.value = err.message || 'Erreur de connexion'
    orders.value = []
  } finally {
    loading.value = false
  }
}

const employeeOptions = computed(() => {
  const map = new Map()
  for (const o of orders.value) {
    if (o.employee_id) {
      if (!map.has(o.employee_id)) {
        map.set(o.employee_id, {
          id: o.employee_id,
          name: o.employee_name || ''
        })
      }
    }
  }
  return Array.from(map.values())
})

const filteredOrders = computed(() => {
  return orders.value.filter(order => {
    // Employee filter
    if (employeeFilter.value) {
      if (String(order.employee_id) !== String(employeeFilter.value)) {
        return false
      }
    }

    // Date filters: priority day > month > year
    const dt = order.order_time
    if (dayFilter.value) {
      if (!dt || dt.substring(0, 10) !== dayFilter.value) {
        return false
      }
    } else if (monthFilter.value) {
      if (!dt || dt.substring(0, 7) !== monthFilter.value) {
        return false
      }
    } else if (yearFilter.value) {
      if (!dt || dt.substring(0, 4) !== String(yearFilter.value)) {
        return false
      }
    }

    // Search by id or table number
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase()
      const idStr = String(order.id)
      const tableStr = String(order.table_number || order.table_id || '')
      if (!idStr.includes(q) && !tableStr.includes(q)) {
        return false
      }
    }

    return true
  })
})

const resetFilters = () => {
  employeeFilter.value = ''
  dayFilter.value = ''
  monthFilter.value = ''
  yearFilter.value = ''
  searchQuery.value = ''
}

const formatDateTime = (dt) => {
  if (!dt) return ''
  // Fix timezone offset: add 1 hour
  const date = new Date(dt)
  return new Date(date.getTime() + 3600000).toLocaleString('fr-FR')
}

const formatPrice = (value) => {
  const n = Number(value) || 0
  return `${n.toFixed(2)} DT`
}

const openDetails = async (order) => {
  selectedOrder.value = order
  showDetailsModal.value = true
  loadingDetails.value = true
  detailsError.value = ''
  orderItemsDetail.value = []

  try {
    const response = await fetch(`${ORDER_DETAIL_API}?id=${order.id}`)
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    const data = await response.json()
    if (data.success && data.data) {
      orderItemsDetail.value = Array.isArray(data.data.items) ? data.data.items : []
    } else {
      detailsError.value = data.message || 'Aucun détail trouvé pour cette commande'
    }
  } catch (err) {
    console.error('Error fetching order details:', err)
    detailsError.value = err.message || 'Erreur de connexion'
  } finally {
    loadingDetails.value = false
  }
}

const calculateRawTotal = () => {
  return orderItemsDetail.value.reduce((sum, item) => sum + (item.price * item.quantity), 0)
}

const closeDetails = () => {
  showDetailsModal.value = false
  selectedOrder.value = null
  orderItemsDetail.value = []
  detailsError.value = ''
}

onMounted(() => {
  fetchOrders()
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

.reset-btn {
  padding: 0.5rem 1rem;
  border-radius: 4px;
  border: none;
  background: #bdc3c7;
  color: white;
  cursor: pointer;
  font-size: 0.9rem;
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

.status-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  font-size: 0.75rem;
  text-transform: capitalize;
}

.status-badge.pending {
  background: #f1c40f33;
  color: #f39c12;
}

.status-badge.completed {
  background: #2ecc7133;
  color: #27ae60;
}

.status-badge.canceled {
  background: #e74c3c33;
  color: #e74c3c;
}

.status-badge.paye {
  background: #27ae6033;
  color: #27ae60;
  font-weight: 600;
}

.details-btn {
  padding: 0.35rem 0.75rem;
  border-radius: 4px;
  border: none;
  background: #3498db;
  color: white;
  font-size: 0.8rem;
  cursor: pointer;
}

.details-btn:hover {
  background: #2980b9;
}

.details-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 3000;
  padding: 1rem;
}

.details-modal {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  max-width: 700px;
  width: 100%;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

.details-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.details-header h3 {
  margin: 0;
}

.details-subtitle {
  margin: 0 0 1rem 0;
  font-size: 0.9rem;
  color: #7f8c8d;
}

.details-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.details-table td {
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #ecf0f1;
  text-align: left;
}

.footer-row td {
  padding: 0.5rem 0.75rem;
  font-weight: 500;
  text-align: left;
}

.footer-row.subtotal {
  color: #7f8c8d;
  border-top: 2px solid #ecf0f1;
}

.footer-row.discount {
  color: #e74c3c;
}

.footer-row.total {
  font-size: 1.1rem;
  font-weight: bold;
  color: #27ae60;
  border-top: 1px solid #ecf0f1;
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

  .reset-btn {
    width: 100%;
    padding: 0.75rem;
  }

  .table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .orders-table {
    min-width: 700px;
    font-size: 0.85rem;
  }

  .orders-table th,
  .orders-table td {
    padding: 0.5rem 0.4rem;
  }

  .details-btn {
    padding: 0.3rem 0.6rem;
    font-size: 0.75rem;
  }

  .details-modal {
    width: 95%;
    max-width: 600px;
    margin: 1rem;
    padding: 1rem;
  }

  .details-header h3 {
    font-size: 1.2rem;
  }

  .details-table {
    font-size: 0.8rem;
  }

  .details-table th,
  .details-table td {
    padding: 0.4rem 0.3rem;
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
    min-width: 600px;
    font-size: 0.75rem;
  }

  .orders-table th,
  .orders-table td {
    padding: 0.4rem 0.3rem;
  }

  .status-badge {
    font-size: 0.7rem;
    padding: 0.15rem 0.5rem;
  }

  .details-modal {
    width: 98%;
    margin: 0.5rem;
    padding: 0.75rem;
  }

  .details-header h3 {
    font-size: 1rem;
  }

  .details-table {
    font-size: 0.75rem;
  }
}
</style>



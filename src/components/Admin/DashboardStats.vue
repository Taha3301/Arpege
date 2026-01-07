<template>
  <div class="dashboard-stats">
    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p>Chargement des statistiques...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <span class="error-icon">⚠️</span>
      <p>{{ error }}</p>
      <button @click="fetchAllData" class="retry-btn">Réessayer</button>
    </div>

    <!-- Dashboard Content -->
    <div v-else class="dashboard-content">
      <!-- Header with Date -->
      <div class="dashboard-header">
        <div>
          <h2>Tableau de Bord</h2>
          <p class="date-info">{{ currentDate }}</p>
        </div>
        <button @click="fetchAllData" class="refresh-btn" :class="{ spinning: refreshing }">
          🔄 Actualiser
        </button>
      </div>

      <!-- Quick Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card revenue-card">
          <div class="stat-icon">💰</div>
          <div class="stat-content">
            <h3>Revenus Total</h3>
            <p class="stat-value">{{ formatCurrency(allTimeStats.revenue) }}</p>
            <div class="stat-footer">
              <span class="stat-label">Tous les temps</span>
            </div>
          </div>
        </div>

        <div class="stat-card orders-card">
          <div class="stat-icon">📦</div>
          <div class="stat-content">
            <h3>Commandes Total</h3>
            <p class="stat-value">{{ allTimeStats.orders }}</p>
            <div class="stat-footer">
              <span class="stat-label">{{ allTimeStats.orders }} commandes</span>
            </div>
          </div>
        </div>

        <div class="stat-card avg-card">
          <div class="stat-icon">📊</div>
          <div class="stat-content">
            <h3>Valeur Moyenne</h3>
            <p class="stat-value">{{ formatCurrency(averageOrderValue.all_time) }}</p>
            <div class="stat-footer">
              <span class="stat-label">par commande</span>
            </div>
          </div>
        </div>

        <div class="stat-card month-card">
          <div class="stat-icon">📅</div>
          <div class="stat-content">
            <h3>Cette Année</h3>
            <p class="stat-value">{{ formatCurrency(yearStats.revenue) }}</p>
            <div class="stat-footer">
              <span class="stat-label">{{ yearStats.orders }} commandes</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Revenue Trends -->
      <div class="section-row">
        <div class="chart-card">
          <h3>Revenus par Jour (30 Derniers Jours)</h3>
          <div class="chart-container">
            <div class="bar-chart">
              <div 
                v-for="day in dailyRevenue" 
                :key="day.date"
                class="bar-wrapper"
                :title="`${day.date}: ${formatCurrency(day.revenue)}`"
              >
                <div 
                  class="bar" 
                  :style="{ height: getBarHeight(day.revenue, maxDailyRevenue) }"
                >
                  <span class="bar-value" v-if="day.revenue > 0">{{ formatShortCurrency(day.revenue) }}</span>
                </div>
                <span class="bar-label">{{ formatDayLabel(day.date) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="chart-card">
          <h3>Commandes par Statut (Total)</h3>
          <div class="status-list">
            <div 
              v-for="status in ordersByStatus" 
              :key="status.status"
              class="status-item"
            >
              <div class="status-info">
                <span class="status-badge" :class="`status-${status.status.toLowerCase()}`">
                  {{ status.status }}
                </span>
                <span class="status-count">{{ status.order_count }} commandes</span>
              </div>
              <div class="status-bar">
                <div 
                  class="status-fill" 
                  :style="{ width: getPercentage(status.order_count, totalOrdersToday) }"
                  :class="`status-${status.status.toLowerCase()}`"
                ></div>
              </div>
              <span class="status-revenue">{{ formatCurrency(status.total_revenue) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Products -->
      <div class="section-row">
        <div class="table-card">
          <h3>🏆 Produits les Plus Vendus (Total)</h3>
          <div class="products-table">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Produit</th>
                  <th>Catégorie</th>
                  <th>Quantité</th>
                  <th>Revenus</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(product, index) in topProducts" :key="product.product_id">
                  <td class="rank">{{ index + 1 }}</td>
                  <td class="product-name">{{ product.product_name }}</td>
                  <td class="category">{{ product.category }}</td>
                  <td class="quantity">{{ product.total_quantity }}</td>
                  <td class="revenue">{{ formatCurrency(product.total_revenue) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="table-card">
          <h3>👥 Performance des Employés (Total)</h3>
          <div class="employees-list">
            <div 
              v-for="employee in employeesToday" 
              :key="employee.employee_id"
              class="employee-item"
            >
              <div class="employee-avatar">{{ getInitials(employee.employee_name) }}</div>
              <div class="employee-info">
                <h4>{{ employee.employee_name }}</h4>
                <p>{{ employee.order_count }} commandes</p>
              </div>
              <div class="employee-revenue">
                {{ formatCurrency(employee.total_revenue) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Revenue by Category -->
      <div class="chart-card full-width">
        <h3>Revenus par Catégorie (Total)</h3>
        <div class="category-grid">
          <div 
            v-for="category in revenueByCategory" 
            :key="category.category_id"
            class="category-card"
          >
            <div class="category-header">
              <span class="category-icon">📁</span>
              <h4>{{ category.category_name }}</h4>
            </div>
            <p class="category-revenue">{{ formatCurrency(category.total_revenue) }}</p>
            <div class="category-stats">
              <span>{{ category.total_quantity }} vendus</span>
              <span>{{ category.order_count }} commandes</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Monthly Revenue Trend -->
      <div class="chart-card full-width">
        <h3>Revenus Mensuels (Cette Année)</h3>
        <div class="month-chart">
          <div 
            v-for="month in monthlyRevenue" 
            :key="month.month"
            class="month-bar-wrapper"
          >
            <div 
              class="month-bar" 
              :style="{ height: getBarHeight(month.revenue, maxMonthlyRevenue) }"
            >
              <span class="month-bar-value" v-if="month.revenue > 0">
                {{ formatShortCurrency(month.revenue) }}
              </span>
            </div>
            <span class="month-label">{{ getShortMonth(month.month_name) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

import { API_BASE_URL } from '../../config/api.js'

const API_BASE = `${API_BASE_URL}/dashboard.php`

// State
const loading = ref(true)
const refreshing = ref(false)
const error = ref(null)
const selectedPeriod = ref('today')
const customStartDate = ref('')
const customEndDate = ref('')

// Data
const allTimeStats = ref({ revenue: 0, orders: 0 })
const todayStats = ref({ revenue: 0, orders: 0 })
const yesterdayStats = ref({ revenue: 0, orders: 0 })
const monthStats = ref({ revenue: 0, orders: 0 })
const yearStats = ref({ revenue: 0, orders: 0 })
const averageOrderValue = ref({ today: 0, all_time: 0 })
const dailyRevenue = ref([])
const hourlyRevenue = ref([])
const ordersByStatus = ref([])
const topProducts = ref([])
const employeesToday = ref([])
const revenueByCategory = ref([])
const monthlyRevenue = ref([])

// Computed
const currentDate = computed(() => {
  return new Date().toLocaleDateString('fr-FR', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  })
})

const revenueChange = computed(() => {
  if (yesterdayStats.value.revenue === 0) return '+0%'
  const change = ((todayStats.value.revenue - yesterdayStats.value.revenue) / yesterdayStats.value.revenue) * 100
  return `${change > 0 ? '+' : ''}${change.toFixed(1)}%`
})

const revenueChangeClass = computed(() => {
  const change = todayStats.value.revenue - yesterdayStats.value.revenue
  return change >= 0 ? 'positive' : 'negative'
})

const maxDailyRevenue = computed(() => {
  return Math.max(...dailyRevenue.value.map(d => d.revenue), 1)
})

const maxHourlyRevenue = computed(() => {
  return Math.max(...hourlyRevenue.value.map(h => h.revenue), 1)
})

const maxMonthlyRevenue = computed(() => {
  return Math.max(...monthlyRevenue.value.map(m => m.revenue), 1)
})

const totalOrdersToday = computed(() => {
  return ordersByStatus.value.reduce((sum, s) => sum + s.order_count, 0)
})

const totalRevenue = computed(() => {
  return todayStats.value.revenue + monthStats.value.revenue + yearStats.value.revenue
})

const totalOrders = computed(() => {
  return todayStats.value.orders + monthStats.value.orders
})

const totalCategoryRevenue = computed(() => {
  return revenueByCategory.value.reduce((sum, cat) => sum + cat.total_revenue, 0)
})

// Methods
const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-TN', { 
    style: 'currency', 
    currency: 'TND',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value).replace('TND', 'DT')
}

const formatShortCurrency = (value) => {
  if (value >= 1000) {
    return `${(value / 1000).toFixed(1)}k DT`
  }
  return `${value.toFixed(0)} DT`
}

const getBarHeight = (value, max) => {
  if (max === 0) return '0%'
  return `${(value / max) * 100}%`
}

const getPercentage = (value, total) => {
  if (total === 0) return '0%'
  return `${(value / total) * 100}%`
}

const getInitials = (name) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

const formatDayLabel = (dateStr) => {
  const date = new Date(dateStr)
  return `${date.getDate()}/${date.getMonth() + 1}`
}

const getShortMonth = (monthName) => {
  const months = {
    'January': 'Jan', 'February': 'Fév', 'March': 'Mar', 'April': 'Avr',
    'May': 'Mai', 'June': 'Juin', 'July': 'Juil', 'August': 'Aoû',
    'September': 'Sep', 'October': 'Oct', 'November': 'Nov', 'December': 'Déc'
  }
  return months[monthName] || monthName.slice(0, 3)
}

const fetchData = async (endpoint) => {
  const response = await fetch(`${API_BASE}?endpoint=${endpoint}`)
  if (!response.ok) throw new Error(`Failed to fetch ${endpoint}`)
  return await response.json()
}

const fetchAllData = async () => {
  try {
    refreshing.value = true
    error.value = null

    // Calculate date for last 30 days
    const endDate = new Date()
    const startDate = new Date()
    startDate.setDate(startDate.getDate() - 30)
    
    const formatDate = (date) => {
      return date.toISOString().split('T')[0]
    }

    const [
      today,
      yesterday,
      month,
      year,
      avgOrder,
      daily,
      statusAll,
      products,
      employees,
      categories,
      monthly
    ] = await Promise.all([
      fetchData('revenue-today'),
      fetchData('revenue-yesterday'),
      fetchData('revenue-this-month'),
      fetchData('revenue-this-year'),
      fetchData('average-order-value'),
      fetchData(`revenue-by-date-range&start_date=${formatDate(startDate)}&end_date=${formatDate(endDate)}`),
      fetchData('orders-by-status'),
      fetchData('top-selling-products&limit=10'),
      fetchData('orders-by-employee'),
      fetchData('revenue-by-category'),
      fetchData('revenue-by-month-this-year')
    ])

    // Calculate all-time stats from year data
    allTimeStats.value = { revenue: year.revenue, orders: year.order_count }
    todayStats.value = { revenue: today.revenue, orders: today.order_count }
    yesterdayStats.value = { revenue: yesterday.revenue, orders: yesterday.order_count }
    monthStats.value = { revenue: month.revenue, orders: month.order_count }
    yearStats.value = { revenue: year.revenue, orders: year.order_count }
    averageOrderValue.value = avgOrder.average_order_value
    dailyRevenue.value = daily.data || []
    ordersByStatus.value = statusAll.data || []
    topProducts.value = products.data || []
    employeesToday.value = employees.data || []
    revenueByCategory.value = categories.data || []
    monthlyRevenue.value = monthly.data || []

  } catch (err) {
    error.value = 'Erreur lors du chargement des données: ' + err.message
    console.error('Dashboard error:', err)
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

const handlePeriodChange = () => {
  if (selectedPeriod.value !== 'custom') {
    fetchAllData()
  }
}

const handleCustomDateChange = () => {
  if (customStartDate.value && customEndDate.value) {
    fetchAllData()
  }
}

onMounted(() => {
  fetchAllData()
  // Auto-refresh every 5 minutes
  setInterval(fetchAllData, 5 * 60 * 1000)
})
</script>

<style scoped>
.dashboard-stats {
  padding: 0;
  min-height: 100vh;
  background-image: url('../../assets/bgdashboard.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  background-repeat: no-repeat;
  position: relative;
}

.dashboard-content {
  position: relative;
  z-index: 1;
  padding: 2rem;
}

@media (max-width: 768px) {
  .dashboard-content {
    padding: 1rem;
  }
}

@media (max-width: 480px) {
  .dashboard-content {
    padding: 0.75rem;
  }
}

/* Loading & Error States */
.loading-container,
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 1rem;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-icon {
  font-size: 3rem;
}

.retry-btn {
  padding: 0.75rem 1.5rem;
  background: #3498db;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.retry-btn:hover {
  background: #2980b9;
  transform: translateY(-2px);
}

/* Dashboard Header */
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #ecf0f1;
  flex-wrap: wrap;
  gap: 1rem;
}

.dashboard-header h2 {
  margin: 0;
  font-size: 2rem;
  color: white;
  font-weight: 700;
}

.date-info {
  margin: 0.5rem 0 0 0;
  color: white;
  font-size: 0.95rem;
  text-transform: capitalize;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.date-filter {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-filter label {
  font-size: 0.9rem;
  color: #2c3e50;
  font-weight: 600;
}

.period-select {
  padding: 0.6rem 1rem;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #2c3e50;
  background: white;
  cursor: pointer;
  transition: all 0.3s ease;
  min-width: 180px;
}

.period-select:hover {
  border-color: #667eea;
}

.period-select:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.custom-date-range {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-input {
  padding: 0.6rem 1rem;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #2c3e50;
  background: white;
  cursor: pointer;
  transition: all 0.3s ease;
}

.date-input:hover {
  border-color: #667eea;
}

.date-input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.custom-date-range span {
  color: #7f8c8d;
  font-weight: 600;
}

.refresh-btn {
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.refresh-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.refresh-btn.spinning {
  animation: spin 1s linear infinite;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  border: 1px solid transparent;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.revenue-card::before {
  background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%);
}

.orders-card::before {
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

.avg-card::before {
  background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
}

.month-card::before {
  background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
}

.stat-icon {
  font-size: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
  border-radius: 12px;
}

.stat-content {
  flex: 1;
}

.stat-content h3 {
  margin: 0 0 0.5rem 0;
  font-size: 0.9rem;
  color: #7f8c8d;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-value {
  margin: 0;
  font-size: 2rem;
  font-weight: 700;
  color: #2c3e50;
  line-height: 1;
}

.stat-footer {
  margin-top: 0.75rem;
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.stat-change {
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 600;
}

.stat-change.positive {
  background: #d4edda;
  color: #155724;
}

.stat-change.negative {
  background: #f8d7da;
  color: #721c24;
}

.stat-label {
  font-size: 0.85rem;
  color: #95a5a6;
}

.stat-total {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #ecf0f1;
}

.stat-total span {
  font-size: 0.85rem;
  color: #2c3e50;
  font-weight: 600;
  background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  display: inline-block;
}

/* Section Row */
.section-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
  .section-row {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
}

/* Chart Card */
.chart-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.chart-card.full-width {
  margin-bottom: 1.5rem;
}

.chart-card h3 {
  margin: 0 0 1.5rem 0;
  font-size: 1.2rem;
  color: #2c3e50;
  font-weight: 600;
}

/* Bar Chart */
.chart-container {
  height: 300px;
  overflow-x: auto;
}

.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  height: 100%;
  padding: 1rem 0;
}

.bar-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  min-width: 40px;
}

.bar {
  width: 100%;
  background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
  border-radius: 8px 8px 0 0;
  min-height: 4px;
  position: relative;
  transition: all 0.3s ease;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 0.5rem;
}

.bar:hover {
  opacity: 0.8;
  transform: scaleY(1.05);
}

.bar-value {
  font-size: 0.7rem;
  color: white;
  font-weight: 600;
}

.bar-label {
  font-size: 0.75rem;
  color: #7f8c8d;
  font-weight: 500;
}

/* Status List */
.status-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-height: 400px;
  overflow-y: auto;
  padding-right: 0.5rem;
}

.status-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.status-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-badge {
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: capitalize;
}

.status-badge.status-disponible {
  background: #d4edda;
  color: #155724;
}

.status-badge.status-indisponible {
  background: #f8d7da;
  color: #721c24;
}

.status-badge.status-occupée,
.status-badge.status-occupee {
  background: #fff3cd;
  color: #856404;
}

.status-badge.status-réservée,
.status-badge.status-reservee {
  background: #d1ecf1;
  color: #0c5460;
}

.status-count {
  font-size: 0.9rem;
  color: #7f8c8d;
}

.status-bar {
  height: 8px;
  background: #ecf0f1;
  border-radius: 4px;
  overflow: hidden;
}

.status-fill {
  height: 100%;
  transition: width 0.5s ease;
}

.status-fill.status-disponible {
  background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%);
}

.status-fill.status-indisponible {
  background: linear-gradient(90deg, #eb3349 0%, #f45c43 100%);
}

.status-fill.status-occupée,
.status-fill.status-occupee {
  background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
}

.status-fill.status-réservée,
.status-fill.status-reservee {
  background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
}

.status-revenue {
  font-size: 0.95rem;
  font-weight: 600;
  color: #2c3e50;
  text-align: right;
}

/* Table Card */
.table-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.table-card h3 {
  margin: 0 0 1rem 0;
  font-size: 1.2rem;
  color: #2c3e50;
  font-weight: 600;
}

.products-table {
  overflow-x: auto;
  max-height: 500px;
  overflow-y: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: #f8f9fa;
}

th {
  padding: 0.75rem;
  text-align: left;
  font-size: 0.85rem;
  font-weight: 600;
  color: #7f8c8d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

td {
  padding: 0.75rem;
  border-bottom: 1px solid #ecf0f1;
  font-size: 0.95rem;
}

.rank {
  font-weight: 700;
  color: #3498db;
  font-size: 1.1rem;
}

.product-name {
  font-weight: 600;
  color: #2c3e50;
}

.category {
  color: #7f8c8d;
  font-size: 0.9rem;
}

.quantity {
  font-weight: 600;
  color: #27ae60;
}

.revenue {
  font-weight: 700;
  color: #2c3e50;
}

/* Employees List */
.employees-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-height: 500px;
  overflow-y: auto;
  padding-right: 0.5rem;
}

.employee-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 12px;
  transition: all 0.3s ease;
}

.employee-item:hover {
  background: #e9ecef;
  transform: translateX(5px);
}

.employee-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.1rem;
}

.employee-info {
  flex: 1;
}

.employee-info h4 {
  margin: 0 0 0.25rem 0;
  font-size: 1rem;
  color: #2c3e50;
}

.employee-info p {
  margin: 0;
  font-size: 0.85rem;
  color: #7f8c8d;
}

.employee-revenue {
  font-size: 1.1rem;
  font-weight: 700;
  color: #27ae60;
}

/* Category Grid */
.category-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  max-height: 600px;
  overflow-y: auto;
  padding-right: 0.5rem;
}

@media (max-width: 640px) {
  .category-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  }
}

@media (max-width: 480px) {
  .category-grid {
    grid-template-columns: 1fr;
  }
}

.category-card {
  padding: 1.5rem;
  background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
  border-radius: 12px;
  border: 2px solid #667eea30;
  transition: all 0.3s ease;
}

.category-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
}

.category-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.category-icon {
  font-size: 1.5rem;
}

.category-header h4 {
  margin: 0;
  font-size: 1rem;
  color: #2c3e50;
  font-weight: 600;
}

.category-revenue {
  margin: 0 0 0.75rem 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #2c3e50;
}

.category-stats {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.85rem;
  color: #7f8c8d;
}

/* Month Chart */
.month-chart {
  display: flex;
  align-items: flex-end;
  gap: 1rem;
  height: 300px;
  padding: 1rem 0;
  overflow-x: auto;
}

.month-bar-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  min-width: 60px;
}

.month-bar {
  width: 100%;
  background: linear-gradient(180deg, #11998e 0%, #38ef7d 100%);
  border-radius: 8px 8px 0 0;
  min-height: 4px;
  transition: all 0.3s ease;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 0.5rem;
}

.month-bar:hover {
  opacity: 0.8;
  transform: scaleY(1.05);
}

.month-bar-value {
  font-size: 0.8rem;
  color: white;
  font-weight: 600;
}

.month-label {
  font-size: 0.85rem;
  color: #7f8c8d;
  font-weight: 600;
}

/* Custom Scrollbar Styling */
.status-list::-webkit-scrollbar,
.products-table::-webkit-scrollbar,
.employees-list::-webkit-scrollbar,
.category-grid::-webkit-scrollbar {
  width: 8px;
}

.status-list::-webkit-scrollbar-track,
.products-table::-webkit-scrollbar-track,
.employees-list::-webkit-scrollbar-track,
.category-grid::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.status-list::-webkit-scrollbar-thumb,
.products-table::-webkit-scrollbar-thumb,
.employees-list::-webkit-scrollbar-thumb,
.category-grid::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 10px;
}

.status-list::-webkit-scrollbar-thumb:hover,
.products-table::-webkit-scrollbar-thumb:hover,
.employees-list::-webkit-scrollbar-thumb:hover,
.category-grid::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
}


/* Responsive */
@media (max-width: 768px) {
  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .dashboard-header h2 {
    font-size: 1.5rem;
  }

  .header-actions {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
  }

  .date-filter {
    flex-direction: column;
    align-items: stretch;
  }

  .period-select {
    width: 100%;
  }

  .custom-date-range {
    flex-direction: column;
    align-items: stretch;
  }

  .date-input {
    width: 100%;
  }

  .refresh-btn {
    width: 100%;
  }

  .stat-value {
    font-size: 1.5rem;
  }

  .stat-icon {
    font-size: 2rem;
    width: 50px;
    height: 50px;
  }

  .chart-card h3,
  .table-card h3 {
    font-size: 1rem;
  }

  .products-table {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -0.5rem;
    padding: 0 0.5rem;
  }

  table {
    min-width: 600px;
    font-size: 0.85rem;
  }

  th, td {
    padding: 0.5rem 0.4rem;
    font-size: 0.8rem;
  }

  th {
    white-space: nowrap;
  }

  .rank {
    font-size: 0.95rem;
  }

  .product-name {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .employee-item {
    padding: 0.75rem;
    gap: 0.75rem;
  }

  .employees-list {
    gap: 0.75rem;
  }

  .bar-chart,
  .month-chart {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .chart-container {
    height: 250px;
  }

  .month-chart {
    height: 250px;
  }
}

@media (max-width: 480px) {
  .dashboard-header h2 {
    font-size: 1.25rem;
  }

  .stat-card {
    padding: 1rem;
  }

  .stat-value {
    font-size: 1.25rem;
  }

  .stat-content h3 {
    font-size: 0.8rem;
  }

  .stat-icon {
    font-size: 1.75rem;
    width: 45px;
    height: 45px;
  }

  .chart-card,
  .table-card {
    padding: 1rem;
  }

  .chart-card h3,
  .table-card h3 {
    font-size: 0.95rem;
    margin-bottom: 1rem;
  }

  .category-revenue {
    font-size: 1.25rem;
  }

  .category-card {
    padding: 1rem;
  }

  .employee-avatar {
    width: 40px;
    height: 40px;
    font-size: 0.9rem;
  }

  .employee-info h4 {
    font-size: 0.9rem;
  }

  .employee-revenue {
    font-size: 1rem;
  }

  /* Additional table optimizations for small phones */
  table {
    min-width: 500px;
  }

  th, td {
    padding: 0.4rem 0.3rem;
    font-size: 0.75rem;
  }

  .product-name {
    max-width: 120px;
  }

  .employee-item {
    padding: 0.65rem;
    gap: 0.65rem;
  }

  .employee-info h4 {
    font-size: 0.85rem;
  }

  .employee-info p {
    font-size: 0.75rem;
  }

  .employee-revenue {
    font-size: 0.9rem;
  }
}

/* iPhone 14 and similar devices (390px width) */
@media (max-width: 430px) {
  /* Products Table - Make it narrower and more compact */
  .products-table {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  table {
    min-width: 100%;
    font-size: 0.7rem;
  }

  th {
    padding: 0.5rem 0.25rem;
    font-size: 0.7rem;
    white-space: nowrap;
  }

  td {
    padding: 0.5rem 0.25rem;
    font-size: 0.7rem;
  }

  .rank {
    font-size: 0.85rem;
    padding-right: 0.15rem;
  }

  .product-name {
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.7rem;
  }

  .category {
    font-size: 0.65rem;
    max-width: 70px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .quantity {
    font-size: 0.7rem;
  }

  .revenue {
    font-size: 0.7rem;
    white-space: nowrap;
  }

  /* Employees List - Make it more compact */
  .employees-list {
    gap: 0.5rem;
    padding-right: 0;
  }

  .employee-item {
    padding: 0.5rem;
    gap: 0.5rem;
    flex-wrap: nowrap;
  }

  .employee-avatar {
    width: 35px;
    height: 35px;
    font-size: 0.75rem;
    flex-shrink: 0;
  }

  .employee-info {
    flex: 1;
    min-width: 0;
  }

  .employee-info h4 {
    font-size: 0.75rem;
    margin-bottom: 0.15rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .employee-info p {
    font-size: 0.65rem;
  }

  .employee-revenue {
    font-size: 0.75rem;
    white-space: nowrap;
    flex-shrink: 0;
  }

  /* Table Card padding */
  .table-card {
    padding: 1rem 0.75rem;
  }

  .table-card h3 {
    font-size: 1rem;
    margin-bottom: 0.75rem;
  }
}

</style>

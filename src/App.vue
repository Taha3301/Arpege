<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import Log from './components/log.vue'
import AdminDashboard from './components/Admin/Dashboard.vue'
import UserLandingpage from './components/User/Landingpage.vue'
import OrderPage from './components/User/OrderPage.vue'
import OrderUpdate from './components/User/OrderUpdate.vue'
import { offlineService } from './utils/offlineService'
import { getApiUrl, API_ENDPOINTS } from './config/api.js'

// Restore session from localStorage on page load
const savedSession = (() => {
  try { return JSON.parse(localStorage.getItem('restoapp_session')) } catch { return null }
})()

const currentView = ref(savedSession?.view || 'login')
const userRole = ref(savedSession?.role || null)
const userId = ref(savedSession?.id ?? null)
const userName = ref(savedSession?.username || '')
const selectedTable = ref(null)
const priceMode = ref(savedSession?.priceMode || 'menu1')

const saveSession = (view, role, id, username, mode = priceMode.value) => {
  localStorage.setItem('restoapp_session', JSON.stringify({ view, role, id, username, priceMode: mode }))
}

const handlePriceModeUpdate = (mode) => {
  priceMode.value = mode
  saveSession(currentView.value, userRole.value, userId.value, userName.value, mode)
}

// Offline & Sync Logic
const isOnline = ref(window.navigator.onLine)
const isSyncing = ref(false)

const syncPendingOrders = async () => {
  if (!isOnline.value || isSyncing.value) return
  const queue = offlineService.getQueue()
  if (queue.length === 0) return

  isSyncing.value = true
  const apiUrl = getApiUrl(API_ENDPOINTS.ORDER)
  const result = await offlineService.syncOrders(apiUrl)
  isSyncing.value = false
  
  if (result.syncedCount > 0) {
    console.log(`✅ Successfully synced ${result.syncedCount} offline orders.`)
  }
}

const updateOnlineStatus = () => {
  isOnline.value = window.navigator.onLine
  if (isOnline.value) {
    syncPendingOrders()
  }
}

onMounted(() => {
  window.addEventListener('online', updateOnlineStatus)
  window.addEventListener('offline', updateOnlineStatus)
  // Initial sync check
  if (isOnline.value) syncPendingOrders()
})

onUnmounted(() => {
  window.removeEventListener('online', updateOnlineStatus)
  window.removeEventListener('offline', updateOnlineStatus)
})

const handleLoginSuccess = (user) => {
  console.log('Login success, user data:', user)
  console.log('User ID type:', typeof user.id, 'Value:', user.id)
  userRole.value = user.role
  userName.value = user.username || ''
  // Ensure user.id is converted to number
  userId.value = user.id !== null && user.id !== undefined ? Number(user.id) : null
  console.log('Stored userId:', userId.value, 'Type:', typeof userId.value)
  let view = 'login'
  if (user.role === 'admin') {
    view = 'admin'
  } else if (user.role === 'employee') {
    view = 'user'
  }
  currentView.value = view
  saveSession(view, user.role, userId.value, userName.value)
}

const handleLogout = () => {
  localStorage.removeItem('restoapp_session')
  currentView.value = 'login'
  userRole.value = null
  userId.value = null
  userName.value = ''
  selectedTable.value = null
}

const handleSelectTable = (table) => {
  console.log('App: handleSelectTable called with table:', table)
  selectedTable.value = table
  currentView.value = 'order'
}

const handleUpdateOrder = (table) => {
  console.log('App: handleUpdateOrder called with table:', table)
  selectedTable.value = table
  currentView.value = 'orderUpdate'
}

const handleGoBack = () => {
  currentView.value = 'user'
  selectedTable.value = null
}

const handleOrderSubmitted = () => {
  // Order was submitted successfully
  // Could refresh tables or show confirmation
}
</script>

<template>
  <div class="app-status">
    <div v-if="!isOnline" class="status-badge offline">
      <span class="dot"></span> Mode Hors-ligne
    </div>
    <div v-if="isSyncing" class="status-badge syncing">
      <span class="spinner"></span> Synchronisation...
    </div>
  </div>

  <Log v-if="currentView === 'login'" @login-success="handleLoginSuccess" />
  <AdminDashboard 
    v-else-if="currentView === 'admin'" 
    :user-name="userName"
    :user-role="userRole"
    @logout="handleLogout" 
  />
  <UserLandingpage 
    v-else-if="currentView === 'user'" 
    :user-name="userName"
    :price-mode="priceMode"
    @logout="handleLogout"
    @select-table="handleSelectTable"
    @update-order="handleUpdateOrder"
    @update-price-mode="handlePriceModeUpdate"
  />
  <OrderPage 
    v-else-if="currentView === 'order' && selectedTable"
    :selected-table="selectedTable"
    :employeeId="userId"
    :price-mode="priceMode"
    :key="`order-${userId}-${selectedTable?.id}-${priceMode}`"
    @go-back="handleGoBack"
    @order-submitted="handleOrderSubmitted"
  />
  <OrderUpdate
    v-else-if="currentView === 'orderUpdate' && selectedTable"
    :selected-table="selectedTable"
    :employeeId="userId"
    :price-mode="priceMode"
    :key="`order-update-${userId}-${selectedTable?.id}-${priceMode}`"
    @go-back="handleGoBack"
    @order-submitted="handleOrderSubmitted"
  />
</template>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body, html {
  margin: 0;
  padding: 0;
  width: 100%;
  min-height: 100%;
  overflow-x: hidden;
  overflow-y: auto;
}

#app {
  margin: 0;
  padding: 0;
  width: 100%;
  min-height: 100vh;
}

/* Status Badges */
.app-status {
  position: fixed;
  top: 1rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  pointer-events: none;
}

.status-badge {
  padding: 0.5rem 1.25rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  box-shadow: 0 8px 24px rgba(0,0,0,0.5);
  animation: slideDown 0.3s ease-out;
  backdrop-filter: blur(8px);
}

.status-badge.offline {
  background: rgba(231, 76, 60, 0.9);
  color: #fff;
  border: 1px solid rgba(255,255,255,0.2);
}

.status-badge.syncing {
  background: rgba(212, 168, 67, 0.9);
  color: #1a1208;
  border: 1px solid rgba(0,0,0,0.1);
}

.status-badge .dot {
  width: 8px;
  height: 8px;
  background: #fff;
  border-radius: 50%;
  animation: blink 1s infinite;
}

.status-badge .spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(0,0,0,0.2);
  border-top-color: #1a1208;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes slideDown {
  from { transform: translateY(-20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>


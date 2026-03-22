<script setup>
import { ref } from 'vue'
import Log from './components/log.vue'
import AdminDashboard from './components/Admin/Dashboard.vue'
import UserLandingpage from './components/User/Landingpage.vue'
import OrderPage from './components/User/OrderPage.vue'
import OrderUpdate from './components/User/OrderUpdate.vue'

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
</style>


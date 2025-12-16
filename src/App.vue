<script setup>
import { ref } from 'vue'
import Log from './components/log.vue'
import AdminDashboard from './components/Admin/Dashboard.vue'
import UserLandingpage from './components/User/Landingpage.vue'
import OrderPage from './components/User/OrderPage.vue'
import OrderUpdate from './components/User/OrderUpdate.vue'

const currentView = ref('login')
const userRole = ref(null)
const userId = ref(null)
const userName = ref('')
const selectedTable = ref(null)

const handleLoginSuccess = (user) => {
  console.log('Login success, user data:', user)
  console.log('User ID type:', typeof user.id, 'Value:', user.id)
  userRole.value = user.role
  userName.value = user.username || ''
  // Ensure user.id is converted to number
  userId.value = user.id !== null && user.id !== undefined ? Number(user.id) : null
  console.log('Stored userId:', userId.value, 'Type:', typeof userId.value)
  if (user.role === 'admin') {
    currentView.value = 'admin'
  } else if (user.role === 'employee') {
    currentView.value = 'user'
  }
}

const handleLogout = () => {
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
    @logout="handleLogout"
    @select-table="handleSelectTable"
    @update-order="handleUpdateOrder"
  />
  <OrderPage 
    v-else-if="currentView === 'order' && selectedTable"
    :selected-table="selectedTable"
    :employeeId="userId"
    :key="`order-${userId}-${selectedTable?.id}`"
    @go-back="handleGoBack"
    @order-submitted="handleOrderSubmitted"
  />
  <OrderUpdate
    v-else-if="currentView === 'orderUpdate' && selectedTable"
    :selected-table="selectedTable"
    :employeeId="userId"
    :key="`order-update-${userId}-${selectedTable?.id}`"
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


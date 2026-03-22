<template>
  <div class="landing-container">
    <header class="header">
      <!-- Secret Menu Icons -->
      <div 
        class="secret-icon icon-standard" 
        :class="{ active: props.priceMode === 'menu1' }"
        @click="updatePriceMode('menu1')"
        title="Menu Standard"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
          <path d="M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20H19V18H13C12.03,18 11.17,17.42 10.73,16.5L9.2,13.33C9.04,13 8.94,12.67 8.9,12.33C10.7,11.89 13.04,10.66 15,9C15.86,8.26 16.33,7.11 16.33,6C16.33,4.89 15.86,3.74 15,3C13.26,1.26 10.74,1.26 9,3C7.26,4.74 7.26,7.26 9,9C10,10 11.25,10.5 12.5,10.5C13.75,10.5 15,10 16,9C16.43,9.43 16.66,10 16.66,10.66C16.66,11.32 16.43,11.89 16,12.33C14.7,13.5 12.5,14.5 10,14.5C9.5,14.5 9,14.47 8.53,14.41L7.5,12.33C7.5,12.11 7.5,11.89 7.55,11.67C6,12.5 4.5,13.5 3,15V17H5V20H3V22H5L6.6,18.35C7.05,18.77 7.6,19 8.2,19H18V17H12.2C12.7,16.4 13.2,15.7 13.6,15L15.3,18.4C15.5,18.8 15.9,19 16.3,19H21V17H17.4L15.7,13.6C16.5,12.8 17,11.8 17,10.66C17,9.5 16.5,8.4 15.7,7.6L17,8Z" />
        </svg>
      </div>
      <div 
        class="secret-icon icon-foreign" 
        :class="{ active: props.priceMode === 'menu2' }"
        @click="updatePriceMode('menu2')"
        title="Menu Étrangers"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12,2C6.47,2 2,6.47 2,12C2,17.53 6.47,22 12,22C17.53,22 22,17.53 22,12C22,6.47 17.53,2 12,2M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M11,7H13V9H11V7M11,11H13V17H11V11Z" />
        </svg>
      </div>

      <div>
        <img :src="logoUrl" alt="Logo" class="header-logo" />
        <p v-if="props.userName" class="user-info">Bonjour {{ props.userName }}</p>
      </div>
      <button @click="handleLogout" class="logout-btn">Déconnexion</button>
    </header>
    
    <div class="content">
      <div v-if="loading" class="loading">Chargement des tables...</div>
      
      <div v-else class="tables-grid">
        <div 
          v-for="table in tables" 
          :key="table.id"
          :class="['table-card', getStatusClass(table.status), { 'updating': updatingTables.has(table.id) }]"
          :style="getTableCardStyle(table)"
        >
          <div class="table-header">
            <div class="table-number">{{ table.table_number }}</div>
            <button 
              @click.stop.prevent="openStatusModal(table)"
              class="status-menu-btn"
              :disabled="updatingTables.has(table.id)"
              title="Changer le statut"
              type="button"
            >
              ⋮
            </button>
          </div>
          <div class="table-status">{{ getStatusLabel(table.status) }}</div>

          <!-- Update order button when table is occupied -->
          <div 
            v-if="canUpdateOrder(table.status)"
            class="update-order-container"
          >
            <button
              class="update-order-btn"
              type="button"
              :disabled="updatingTables.has(table.id)"
              @click.stop.prevent="updateOrder(table)"
            >
              Mettre à jour la commande
            </button>
            <span class="update-hint">Ajouter ou retirer des articles</span>
          </div>

          <!-- Status Dropdown Menu (replaced by modal) -->
          <!-- Click area for selecting table (only for available/reserved) -->
          <div 
            v-if="isTableClickable(table.status)"
            class="table-select-area"
            @click="selectTable(table)"
          >
            <span class="select-hint">Cliquer pour commander</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Message -->
    <div v-if="message" :class="['message', messageType]">
      {{ message }}
    </div>

    <!-- Status Modal -->
    <div 
      v-if="showStatusModal && tableForStatus" 
      class="status-modal-overlay"
      @click.self="closeStatusModal"
    >
      <div class="status-modal">
        <div class="status-modal-header">
          <h3>Table {{ tableForStatus.table_number }}</h3>
          <button class="close-btn" @click="closeStatusModal">×</button>
        </div>
        <p>Sélectionnez un nouveau statut :</p>
        <div class="status-modal-options">
          <button
            v-for="statusOption in statusOptions"
            :key="statusOption.value"
            @click="changeTableStatus(tableForStatus, statusOption.value)"
            :class="['status-option', { 'active': tableForStatus.status === statusOption.value }]"
            :disabled="updatingTables.has(tableForStatus.id)"
          >
            {{ statusOption.label }}
          </button>
        </div>
      </div>
    </div>
    </div>
  </template>
  
<script setup>
import { ref, onMounted } from 'vue'
import logoImg from '../../assets/logo.png'

const logoUrl = logoImg
import { getApiUrl, API_ENDPOINTS } from '../../config/api.js'

const props = defineProps({
  userName: {
    type: String,
    default: ''
  },
  priceMode: {
    type: String,
    default: 'menu1'
  }
})

const emit = defineEmits(['logout', 'select-table', 'update-order', 'update-price-mode'])

const tables = ref([])
const loading = ref(false)
const showStatusModal = ref(false)
const tableForStatus = ref(null)

const updatingTables = ref(new Set())
const message = ref('')
const messageType = ref('')
const API_URL = getApiUrl(API_ENDPOINTS.TABLE)

// Get style for table card with background image
const getTableCardStyle = (table) => {
  if (table.image) {
    return {
      backgroundImage: `url(${table.image})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
      backgroundRepeat: 'no-repeat'
    }
  }
  return {}
}

const statusOptions = [
  { value: 'disponible', label: 'Disponible' },
  { value: 'Réservée', label: 'Réservée' },
  { value: 'indisponible', label: 'Indisponible' }
]

const fetchTables = async () => {
  loading.value = true
  try {
    const response = await fetch(API_URL)
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
    const data = await response.json()
    if (data.success) {
      tables.value = Array.isArray(data.data) ? data.data : []
    } else {
      tables.value = []
    }
  } catch (error) {
    console.error('Error fetching tables:', error)
    tables.value = []
  } finally {
    loading.value = false
  }
}

const getStatusLabel = (status) => {
  const labels = {
    'disponible': 'Disponible',
    'indisponible': 'Indisponible',
    'Occupée': 'Occupée',
    'Réservée': 'Réservée',
    'available': 'Disponible',
    'unavailable': 'Indisponible',
    'occupied': 'Occupée',
    'reserved': 'Réservée'
  }
  return labels[status?.toLowerCase()] || status || 'Disponible'
}

const getStatusClass = (status) => {
  const statusLower = status?.toLowerCase() || ''
  if (statusLower === 'disponible' || statusLower === 'available') {
    return 'status-available'
  } else if (statusLower === 'occupée' || statusLower === 'occupied') {
    return 'status-occupied'
  } else if (statusLower === 'réservée' || statusLower === 'reserved') {
    return 'status-reserved'
  } else {
    return 'status-unavailable'
  }
}

const selectTable = (table) => {
  const statusLower = table.status?.toLowerCase() || ''

  // For now, clicking the card itself is ONLY for creating a new order
  // (available or reserved). Updating an occupied table uses the button.
  if (statusLower === 'disponible' || statusLower === 'available' || 
      statusLower === 'réservée' || statusLower === 'reserved') {
    console.log('Selecting table for new order:', table)
    emit('select-table', table)
  }
}

const isTableClickable = (status) => {
  const statusLower = status?.toLowerCase() || ''
  // Card is clickable ONLY when we allow creating a new order
  return statusLower === 'disponible' || statusLower === 'available' || 
         statusLower === 'réservée' || statusLower === 'reserved'
}

const canUpdateOrder = (status) => {
  const statusLower = status?.toLowerCase() || ''
  return statusLower === 'occupée' || statusLower === 'occupied'
}

const updateOrder = (table) => {
  console.log('Update order button clicked for table:', table)
  // Explicit button to go to update page for occupied tables
  emit('update-order', table)
}

const openStatusModal = (table) => {
  tableForStatus.value = table
  showStatusModal.value = true
}

const closeStatusModal = () => {
  showStatusModal.value = false
  tableForStatus.value = null
}

const changeTableStatus = async (table, newStatus) => {
  if (table.status === newStatus) {
    closeStatusModal()
    return
  }

  updatingTables.value.add(table.id)
  closeStatusModal()

  try {
    console.log('Updating table status:', { tableId: table.id, oldStatus: table.status, newStatus })
    
    const requestBody = {
      id: table.id,
      table_number: table.table_number,
      status: newStatus
    }
    
    console.log('Request body:', requestBody)
    console.log('API URL:', `${API_URL}?id=${table.id}`)
    
    const response = await fetch(`${API_URL}?id=${table.id}`, {
      method: 'PUT',
      headers: { 
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(requestBody)
    })

    console.log('Response status:', response.status)
    console.log('Response ok:', response.ok)

    if (!response.ok) {
      const errorText = await response.text()
      console.error('Error response text:', errorText)
      let errorData
      try {
        errorData = JSON.parse(errorText)
      } catch (e) {
        errorData = { message: errorText || `HTTP error! status: ${response.status}` }
      }
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }

    const data = await response.json()
    console.log('Response data:', data)

    if (data.success) {
      showMessage(`Statut de la table ${table.table_number} mis à jour`, 'success')
      // Update the table in the local array with the normalized status from API
      const tableIndex = tables.value.findIndex(t => t.id === table.id)
      if (tableIndex !== -1) {
        // Use the status from the response if available, otherwise use newStatus
        tables.value[tableIndex].status = data.data?.status || newStatus
      }
    } else {
      showMessage(data.message || 'Erreur lors de la mise à jour', 'error')
    }
  } catch (error) {
    console.error('Error updating table status:', error)
    console.error('Error details:', {
      message: error.message,
      stack: error.stack,
      tableId: table.id,
      newStatus: newStatus
    })
    showMessage('Erreur: ' + (error.message || 'Erreur de connexion'), 'error')
  } finally {
    updatingTables.value.delete(table.id)
  }
}

const showMessage = (msg, type) => {
  message.value = msg
  messageType.value = type
  setTimeout(() => {
    message.value = ''
  }, 3000)
}

const handleLogout = () => {
  emit('logout')
}

const updatePriceMode = (mode) => {
  emit('update-price-mode', mode)
  showMessage(`Mode ${mode === 'menu1' ? 'Standard' : 'Étrangers'} activé`, 'success')
}

onMounted(async () => {
  await fetchTables()
})
  
  </script>
  
  <style scoped>
/* ───── DESIGN TOKENS ───── */
/* accent gold: #d4a843  |  dark bg: #12100e  |  card: rgba(255,255,255,0.06) */

@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.landing-container {
  min-height: 100vh;
  font-family: 'Inter', sans-serif;
  background-image:
    linear-gradient(160deg, rgba(18,16,14,0.96) 0%, rgba(30,20,10,0.94) 60%, rgba(18,16,14,0.98) 100%),
    url('../../assets/bguser.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  padding: 1.5rem;
  overflow-y: auto;
  overflow-x: hidden;
}

/* ── HEADER ── */
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  background: rgba(255,255,255,0.04);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(212,168,67,0.18);
  padding: 1rem 1.75rem;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
  position: relative;
}

.secret-icon {
  position: absolute;
  top: 10px;
  width: 24px;
  height: 24px;
  color: rgba(212,168,67,0.1); /* Visible enough if you look closely */
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1001;
  border-radius: 50%;
}

.icon-standard { left: 16px; }
.icon-foreign { right: 16px; }

.secret-icon:hover {
  color: rgba(212,168,67,0.4);
  background: rgba(212,168,67,0.05);
  transform: scale(1.1);
}

.secret-icon.active {
  color: rgba(212,168,67,0.2);
  background: rgba(212,168,67,0.03);
}

.header-logo {
  height: 52px;
  width: auto;
  object-fit: contain;
  display: block;
  filter: drop-shadow(0 2px 6px rgba(212,168,67,0.3));
}

.user-info {
  margin-top: 0.3rem;
  color: rgba(255,255,255,0.5);
  font-size: 0.88rem;
  letter-spacing: 0.02em;
}

.logout-btn {
  padding: 0.65rem 1.4rem;
  background: transparent;
  color: #e05c5c;
  border: 1.5px solid #e05c5c;
  border-radius: 10px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 600;
  font-family: inherit;
  transition: all 0.25s ease;
  letter-spacing: 0.02em;
}

.logout-btn:hover {
  background: #e05c5c;
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(224,92,92,0.35);
}

/* ── CONTENT & GRID ── */
.content {
  max-width: 1280px;
  margin: 0 auto;
}

.loading {
  text-align: center;
  padding: 4rem;
  color: rgba(255,255,255,0.5);
  font-size: 1rem;
  letter-spacing: 0.05em;
}

.tables-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 1.25rem;
}

/* ── TABLE CARD ── */
.table-card {
  background: rgba(255,255,255,0.055);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-radius: 16px;
  padding: 1.4rem;
  text-align: center;
  transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
  border: 1.5px solid rgba(255,255,255,0.1);
  position: relative;
  overflow: visible;
}

.table-card::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 16px;
  background: rgba(0,0,0,0.25);
  z-index: 0;
  transition: background 0.28s ease;
  pointer-events: none;
}

.table-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 20px 48px rgba(0,0,0,0.55);
}

.table-card:hover::before {
  background: rgba(0,0,0,0.08);
}

.table-card > * {
  position: relative;
  z-index: 1;
}

.table-card.updating {
  opacity: 0.5;
  pointer-events: none;
}

/* ── TABLE HEADER ── */
.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  position: relative;
  z-index: 10;
}

.status-menu-btn {
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  font-size: 1.2rem;
  cursor: pointer;
  padding: 0.2rem 0.45rem;
  border-radius: 8px;
  color: rgba(255,255,255,0.6);
  transition: all 0.2s ease;
  line-height: 1;
  position: relative;
  z-index: 10;
  pointer-events: auto;
}

.status-menu-btn:hover:not(:disabled) {
  background: rgba(255,255,255,0.15);
  color: #fff;
}

.status-menu-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ── TABLE NUMBER & STATUS BADGE ── */
.table-number {
  font-size: 2.8rem;
  font-weight: 800;
  color: #fff;
  margin-bottom: 0.5rem;
  text-shadow: 0 2px 12px rgba(0,0,0,0.7);
  line-height: 1;
}

.table-status {
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.35rem 0.9rem;
  border-radius: 20px;
  display: inline-block;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* Status colours */
.status-available { border-color: rgba(39,174,96,0.6) !important; }
.status-available .table-status { background: #27ae60; color: #fff; box-shadow: 0 0 16px rgba(39,174,96,0.4); }
.status-available:hover { border-color: #27ae60 !important; }
.status-available { cursor: pointer; }

.status-occupied { border-color: rgba(243,156,18,0.6) !important; cursor: not-allowed; }
.status-occupied .table-status { background: #f39c12; color: #fff; box-shadow: 0 0 16px rgba(243,156,18,0.4); }
.status-occupied::before { background: rgba(0,0,0,0.55) !important; }

.status-reserved { border-color: rgba(52,152,219,0.6) !important; cursor: pointer; }
.status-reserved .table-status { background: #3498db; color: #fff; box-shadow: 0 0 16px rgba(52,152,219,0.4); }
.status-reserved:hover { border-color: #3498db !important; }

.status-unavailable { border-color: rgba(231,76,60,0.5) !important; cursor: not-allowed; opacity: 0.55; }
.status-unavailable .table-status { background: #e74c3c; color: #fff; }
.status-unavailable::before { background: rgba(0,0,0,0.65) !important; }

/* ── UPDATE ORDER AREA ── */
.update-order-container {
  margin-top: 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  align-items: center;
}

.update-order-btn {
  background: linear-gradient(135deg, #f39c12, #e67e22);
  color: #fff;
  border: none;
  padding: 0.55rem 1rem;
  border-radius: 20px;
  font-weight: 700;
  font-family: inherit;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.22s ease;
  width: 100%;
  letter-spacing: 0.02em;
  box-shadow: 0 4px 14px rgba(243,156,18,0.3);
}

.update-order-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(243,156,18,0.45);
}

.update-order-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.update-hint {
  font-size: 0.72rem;
  color: rgba(255,255,255,0.4);
  font-style: italic;
}

/* ── SELECT AREA ── */
.table-select-area {
  margin-top: 0.9rem;
  padding-top: 0.9rem;
  border-top: 1px solid rgba(255,255,255,0.1);
  cursor: pointer;
}

.select-hint {
  font-size: 0.8rem;
  color: rgba(255,255,255,0.65);
  font-style: italic;
  text-shadow: 0 1px 4px rgba(0,0,0,0.6);
}

.table-select-area:hover .select-hint {
  color: #d4a843;
}

/* ── DROPDOWN ── */
.status-dropdown {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0.5rem;
  background: #1e1a14;
  border: 1px solid rgba(212,168,67,0.25);
  border-radius: 10px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.6);
  z-index: 1000;
  min-width: 160px;
  overflow: hidden;
  pointer-events: auto;
  animation: fadeIn 0.18s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}

.status-option {
  display: block;
  width: 100%;
  padding: 0.8rem 1rem;
  text-align: left;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: background 0.15s ease;
  font-size: 0.9rem;
  color: rgba(255,255,255,0.75);
  font-family: inherit;
  pointer-events: auto;
}

.status-option:hover:not(:disabled) {
  background: rgba(212,168,67,0.12);
  color: #d4a843;
}

.status-option.active {
  background: rgba(52,152,219,0.2);
  color: #3498db;
  font-weight: 700;
}

.status-option:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ── STATUS MODAL ── */
.status-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.65);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 1rem;
}

.status-modal {
  background: #1a1612;
  border: 1px solid rgba(212,168,67,0.2);
  border-radius: 16px;
  padding: 1.75rem;
  width: 100%;
  max-width: 380px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.7);
  animation: fadeIn 0.22s ease-out;
}

.status-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.2rem;
}

.status-modal-header h3 {
  margin: 0;
  color: #d4a843;
  font-size: 1.1rem;
  font-weight: 700;
}

.close-btn {
  border: none;
  background: transparent;
  font-size: 1.4rem;
  cursor: pointer;
  color: rgba(255,255,255,0.45);
  transition: color 0.15s;
}
.close-btn:hover { color: #fff; }

.status-modal p {
  color: rgba(255,255,255,0.55);
  font-size: 0.88rem;
  margin: 0 0 1rem;
}

.status-modal-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.status-modal-options .status-option {
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.04);
}

/* ── TOAST MESSAGE ── */
.message {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  padding: 1rem 1.5rem;
  border-radius: 12px;
  color: #fff;
  z-index: 3000;
  animation: slideIn 0.3s ease-out;
  box-shadow: 0 8px 32px rgba(0,0,0,0.45);
  font-weight: 600;
  font-size: 0.95rem;
  backdrop-filter: blur(8px);
}

.message.success { background: linear-gradient(135deg,#27ae60,#1e8449); }
.message.error   { background: linear-gradient(135deg,#e74c3c,#c0392b); }

@keyframes slideIn {
  from { transform: translateX(110%); opacity: 0; }
  to   { transform: translateX(0);    opacity: 1; }
}

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .landing-container { padding: 1rem; }
  .header { flex-direction: column; gap: 0.75rem; text-align: center; padding: 1rem; }
  .logout-btn { width: 100%; }
  .tables-grid { grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); gap: 1rem; }
  .table-card { padding: 1rem; }
  .table-number { font-size: 2rem; }
  .message { bottom: 1rem; right: 1rem; left: 1rem; }
}

@media (max-width: 480px) {
  .landing-container { padding: 0.5rem; }
  .tables-grid { grid-template-columns: repeat(2,1fr); gap: 0.75rem; }
  .table-number { font-size: 1.6rem; }
}
</style>
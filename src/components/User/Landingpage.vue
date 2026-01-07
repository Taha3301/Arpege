<template>
  <div class="landing-container">
    <header class="header">
      <div>
        <h1>Gestion des Tables</h1>
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
import { getApiUrl, API_ENDPOINTS } from '../../config/api.js'

const props = defineProps({
  userName: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['logout', 'select-table', 'update-order'])

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

onMounted(async () => {
  await fetchTables()
})
  
  </script>
  
  <style scoped>
.landing-container {
  min-height: 100vh;
  /* Background image with gradient overlay */
  background-image:
    linear-gradient(135deg, rgba(102, 126, 234, 0.85) 0%, rgba(118, 75, 162, 0.9) 100%),
    url('../../assets/bguser.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  padding: 2rem;
  overflow-y: auto;
  overflow-x: hidden;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  background: white;
  padding: 1.5rem 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header h1 {
  margin: 0;
  color: #2c3e50;
  font-size: 2rem;
}

.user-info {
  margin-top: 0.4rem;
  color: #7f8c8d;
  font-size: 0.95rem;
}

.logout-btn {
  padding: 0.75rem 1.5rem;
  background: #e74c3c;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 600;
  transition: all 0.3s ease;
}

.logout-btn:hover {
  background: #c0392b;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.content {
  max-width: 1200px;
  margin: 0 auto;
}

.loading {
  text-align: center;
  padding: 3rem;
  color: white;
  font-size: 1.2rem;
}

.tables-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1.5rem;
}

.table-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  text-align: center;
  transition: all 0.3s ease;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border: 3px solid transparent;
  position: relative;
  overflow: visible;
}

.table-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.3); /* Darker, more transparent overlay for clarity */
  z-index: 0;
  transition: all 0.3s ease;
  pointer-events: none;
}

.table-card:hover::before {
  background: rgba(0, 0, 0, 0.1); /* Even clearer on hover */
}

.table-card > * {
  position: relative;
  z-index: 1;
}

.table-card.updating {
  opacity: 0.6;
  pointer-events: none;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  position: relative;
  z-index: 10;
}

.status-menu-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  color: #7f8c8d;
  transition: all 0.2s ease;
  line-height: 1;
  position: relative;
  z-index: 10;
  pointer-events: auto;
}

.status-menu-btn:hover:not(:disabled) {
  background: #ecf0f1;
  color: #2c3e50;
}

.status-menu-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.status-dropdown {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0.5rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 1000;
  min-width: 150px;
  overflow: hidden;
  pointer-events: auto;
  display: block !important;
  animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.status-option {
  display: block;
  width: 100%;
  padding: 0.75rem 1rem;
  text-align: left;
  border: none;
  background: white;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.95rem;
  color: #2c3e50;
  position: relative;
  z-index: 1001;
  pointer-events: auto;
}

.status-option:hover:not(:disabled) {
  background: #f8f9fa;
}

.status-option.active {
  background: #3498db;
  color: white;
  font-weight: 600;
}

.status-option:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.update-order-container {
  margin-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  align-items: center;
}

.update-order-btn {
  background: #f39c12;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  width: 100%;
}

.update-order-btn:hover:not(:disabled) {
  background: #d68910;
  transform: translateY(-1px);
}

.update-order-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.update-hint {
  font-size: 0.75rem;
  color: #7f8c8d;
  font-style: italic;
}

.status-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 1rem;
}

.status-modal {
  background: #fff;
  border-radius: 12px;
  padding: 1.5rem;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  animation: fadeIn 0.2s ease-out;
}

.status-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.status-modal-header h3 {
  margin: 0;
  color: #2c3e50;
}

.close-btn {
  border: none;
  background: transparent;
  font-size: 1.5rem;
  cursor: pointer;
  color: #7f8c8d;
}

.status-modal-options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.table-select-area {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e0e0e0;
  cursor: pointer;
}

.table-select-area:hover {
  background: rgba(52, 152, 219, 0.1);
  border-radius: 8px;
  margin: 0.5rem -0.5rem 0;
  padding: 0.5rem;
  margin-top: 1rem;
}

.select-hint {
  font-size: 0.85rem;
  color: white;
  font-style: italic;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
}

.table-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

.table-card.status-available {
  border-color: #27ae60;
  cursor: pointer;
}

.table-card.status-available:hover::before {
  background: rgba(213, 244, 230, 0.8);
}

.table-card.status-occupied {
  border-color: #f39c12;
  cursor: not-allowed;
  opacity: 0.7;
}

.table-card.status-occupied::before {
  background: rgba(0, 0, 0, 0.6); /* More dimmed when occupied */
}

.table-card.status-reserved {
  border-color: #3498db;
  cursor: pointer;
}

.table-card.status-reserved:hover::before {
  background: rgba(235, 245, 251, 0.8);
}

.table-card.status-unavailable {
  border-color: #e74c3c;
  cursor: not-allowed;
  opacity: 0.5;
}

.table-card.status-unavailable::before {
  background: rgba(0, 0, 0, 0.7);
}

.table-number {
  font-size: 3rem;
  font-weight: bold;
  color: white;
  margin-bottom: 0.5rem;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

.table-status {
  font-size: 1rem;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  display: inline-block;
}

.status-available .table-status {
  background: #27ae60;
  color: white;
}

.status-occupied .table-status {
  background: #f39c12;
  color: white;
}

.status-reserved .table-status {
  background: #3498db;
  color: white;
}

.status-unavailable .table-status {
  background: #e74c3c;
  color: white;
}

@media (max-width: 768px) {
  .landing-container {
    padding: 1rem;
  }

  .header {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
    padding: 1rem;
  }

  .header h1 {
    font-size: 1.5rem;
  }

  .user-info {
    font-size: 0.85rem;
  }

  .logout-btn {
    width: 100%;
    padding: 0.75rem;
  }

  .tables-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 1rem;
  }
  
  .table-card {
    padding: 1rem;
  }

  .table-number {
    font-size: 2rem;
  }

  .table-status {
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
  }

  .status-menu-btn {
    font-size: 1.3rem;
    padding: 0.3rem 0.4rem;
  }

  .update-order-btn {
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
  }

  .update-hint {
    font-size: 0.7rem;
  }

  .select-hint {
    font-size: 0.75rem;
  }

  .status-modal {
    width: 90%;
    max-width: 350px;
    padding: 1rem;
  }

  .status-modal-header h3 {
    font-size: 1.2rem;
  }

  .status-option {
    padding: 0.75rem;
    font-size: 0.9rem;
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
  .landing-container {
    padding: 0.5rem;
  }

  .header {
    padding: 0.75rem;
  }

  .header h1 {
    font-size: 1.2rem;
  }

  .tables-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }

  .table-card {
    padding: 0.75rem;
  }

  .table-number {
    font-size: 1.5rem;
  }

  .table-status {
    font-size: 0.75rem;
    padding: 0.3rem 0.6rem;
  }

  .update-order-container {
    margin-top: 0.5rem;
  }

  .update-order-btn {
    font-size: 0.75rem;
    padding: 0.35rem 0.6rem;
  }

  .update-hint {
    font-size: 0.65rem;
  }

  .status-modal {
    width: 95%;
    padding: 0.75rem;
  }
}

.message {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  padding: 1rem 1.5rem;
  border-radius: 8px;
  color: white;
  z-index: 1000;
  animation: slideIn 0.3s ease-out;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
  </style>
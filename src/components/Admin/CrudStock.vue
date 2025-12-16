<template>
  <div class="crud-container">
    <div class="crud-header">
      <button @click="openAddModal" class="btn btn-primary">
        <span>+</span> Ajouter Stock
      </button>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Quantité</th>
            <th>Unité</th>
            <th>Date de Création</th>
            <th>Dernière Mise à Jour</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="loading">Chargement...</td>
          </tr>
          <tr v-else-if="stocks.length === 0">
            <td colspan="7" class="empty">Aucun stock trouvé</td>
          </tr>
          <tr v-else v-for="stock in stocks" :key="stock.id">
            <td>{{ stock.id }}</td>
            <td>{{ stock.name }}</td>
            <td>{{ stock.quantity }}</td>
            <td>{{ stock.unit }}</td>
            <td>{{ formatDate(stock.created_at) }}</td>
            <td>{{ formatDate(stock.updated_at) }}</td>
            <td class="actions">
              <button @click="openEditModal(stock)" class="btn btn-edit">Modifier</button>
              <button @click="confirmDelete(stock)" class="btn btn-delete">Supprimer</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingStock ? 'Modifier Stock' : 'Ajouter Stock' }}</h2>
          <button @click="closeModal" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="saveStock" class="modal-body">
          <div class="form-group">
            <label>Nom *</label>
            <input v-model="formData.name" type="text" required maxlength="100" />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Quantité *</label>
              <input v-model.number="formData.quantity" type="number" step="0.01" min="0" required />
            </div>
            <div class="form-group">
              <label>Unité *</label>
              <select v-model="formData.unit" required>
                <option value="">Sélectionner une unité</option>
                <option value="g">g (grammes)</option>
                <option value="kg">kg (kilogrammes)</option>
                <option value="ml">ml (millilitres)</option>
                <option value="l">l (litres)</option>
                <option value="piece">Pièce</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="closeModal" class="btn btn-cancel">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="cancelDelete">
      <div class="modal-content delete-modal" @click.stop>
        <h3>Confirmer la suppression</h3>
        <p>Êtes-vous sûr de vouloir supprimer le stock <strong>{{ stockToDelete?.name }}</strong> ?</p>
        <div class="modal-footer">
          <button @click="cancelDelete" class="btn btn-cancel">Annuler</button>
          <button @click="deleteStock" class="btn btn-delete" :disabled="deleting">
            {{ deleting ? 'Suppression...' : 'Supprimer' }}
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
import { ref, onMounted } from 'vue'
import { getApiUrl, API_ENDPOINTS } from '../../config/api.js'

const API_URL = getApiUrl(API_ENDPOINTS.STOCK)

const stocks = ref([])
const loading = ref(false)
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingStock = ref(null)
const stockToDelete = ref(null)
const saving = ref(false)
const deleting = ref(false)
const message = ref('')
const messageType = ref('')

const formData = ref({
  name: '',
  quantity: 0,
  unit: ''
})

const fetchStocks = async () => {
  loading.value = true
  try {
    const response = await fetch(API_URL)
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      stocks.value = Array.isArray(data.data) ? data.data : []
    } else {
      showMessage(data.message || 'Erreur lors du chargement des stocks', 'error')
      stocks.value = []
    }
  } catch (error) {
    console.error('Fetch error:', error)
    showMessage('Erreur de connexion: ' + error.message, 'error')
    stocks.value = []
  } finally {
    loading.value = false
  }
}

const openAddModal = () => {
  editingStock.value = null
  formData.value = {
    name: '',
    quantity: 0,
    unit: ''
  }
  showModal.value = true
}

const openEditModal = (stock) => {
  editingStock.value = stock
  formData.value = {
    name: stock.name,
    quantity: stock.quantity,
    unit: stock.unit
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingStock.value = null
  formData.value = {
    name: '',
    quantity: 0,
    unit: ''
  }
}

const saveStock = async () => {
  saving.value = true
  try {
    const url = editingStock.value 
      ? `${API_URL}?id=${editingStock.value.id}`
      : API_URL
    
    const method = editingStock.value ? 'PUT' : 'POST'
    
    const requestData = {
      name: formData.value.name.trim(),
      quantity: parseFloat(formData.value.quantity),
      unit: formData.value.unit
    }
    
    const response = await fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestData)
    })
    
    const data = await response.json()
    
    if (data.success) {
      showMessage(editingStock.value ? 'Stock modifié avec succès' : 'Stock ajouté avec succès', 'success')
      closeModal()
      fetchStocks()
    } else {
      showMessage(data.message || 'Erreur lors de l\'enregistrement', 'error')
    }
  } catch (error) {
    console.error('Error:', error)
    showMessage('Erreur de connexion: ' + error.message, 'error')
  } finally {
    saving.value = false
  }
}

const confirmDelete = (stock) => {
  stockToDelete.value = stock
  showDeleteModal.value = true
}

const cancelDelete = () => {
  showDeleteModal.value = false
  stockToDelete.value = null
}

const deleteStock = async () => {
  deleting.value = true
  try {
    const response = await fetch(`${API_URL}?id=${stockToDelete.value.id}`, {
      method: 'DELETE'
    })
    
    const data = await response.json()
    
    if (data.success) {
      showMessage('Stock supprimé avec succès', 'success')
      cancelDelete()
      fetchStocks()
    } else {
      showMessage(data.message || 'Erreur lors de la suppression', 'error')
    }
  } catch (error) {
    showMessage('Erreur de connexion', 'error')
  } finally {
    deleting.value = false
  }
}

const formatDate = (dateString) => {
  if (!dateString || dateString.trim() === '') return '-'
  try {
    const date = new Date(dateString)
    if (isNaN(date.getTime())) return '-'
    return date.toLocaleDateString('fr-FR', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    })
  } catch (e) {
    return '-'
  }
}

const showMessage = (msg, type) => {
  message.value = msg
  messageType.value = type
  setTimeout(() => {
    message.value = ''
  }, 3000)
}

onMounted(() => {
  fetchStocks()
})
</script>

<style scoped>
.crud-container {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.crud-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
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

.btn-primary:hover:not(:disabled) {
  background: #2980b9;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

.btn-delete:hover:not(:disabled) {
  background: #c0392b;
}

.btn-delete:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

.form-group select:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
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

  .table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .data-table {
    min-width: 600px;
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

  .modal-content {
    width: 95%;
    max-width: 500px;
    margin: 1rem;
  }

  .modal-header h2 {
    font-size: 1.2rem;
  }

  .form-group {
    margin-bottom: 1rem;
  }

  .form-row {
    grid-template-columns: 1fr;
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

  .data-table {
    min-width: 500px;
    font-size: 0.75rem;
  }

  .data-table th,
  .data-table td {
    padding: 0.4rem 0.3rem;
  }

  .modal-content {
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
  .form-group select {
    padding: 0.6rem;
    font-size: 0.9rem;
  }
}
</style>


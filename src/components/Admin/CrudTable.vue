<template>
  <div class="crud-container">
    <div class="crud-header">
      <button @click="openAddModal" class="btn btn-primary">
        <span>+</span> Ajouter Table
      </button>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Photo</th>
            <th>Numéro de Table</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="loading">Chargement...</td>
          </tr>
          <tr v-else-if="tables.length === 0">
            <td colspan="5" class="empty">Aucune table trouvée</td>
          </tr>
          <tr v-else v-for="table in tables" :key="table.id">
            <td>{{ table.id }}</td>
            <td class="table-photo-cell">
              <div v-if="table.image" class="table-photo-preview">
                <img :src="table.image" alt="Table Photo" />
              </div>
              <div v-else class="no-photo">-</div>
            </td>
            <td>{{ table.table_number }}</td>
            <td>
              <span :class="['badge', getStatusClass(table.status)]">
                {{ getStatusLabel(table.status) }}
              </span>
            </td>

            <td class="actions">
              <button @click="openEditModal(table)" class="btn btn-edit">Modifier</button>
              <button @click="confirmDelete(table)" class="btn btn-delete">Supprimer</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingTable ? 'Modifier Table' : 'Ajouter Table' }}</h2>
          <button @click="closeModal" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="saveTable" class="modal-body">
          <div class="form-group">
            <label>Numéro de Table *</label>
            <input v-model="formData.table_number" type="text" required />
          </div>
          <div class="form-group">
            <label>Statut *</label>
            <select v-model="formData.status" required>
              <option value="disponible">Disponible</option>
              <option value="Occupée">Occupée</option>
              <option value="Réservée">Réservée</option>
              <option value="indisponible">Indisponible</option>
            </select>
          </div>

          <div class="form-group photo-upload-section">
            <label>Photo de la table</label>
            <div v-if="formData.image" class="photo-preview">
              <img :src="formData.image" alt="Preview" />
              <button type="button" @click="removePhoto" class="remove-preview-btn" title="Supprimer la photo">&times;</button>
            </div>
            <div v-else class="photo-placeholder" @click="triggerPhotoInput">
              <span>Cliquez pour ajouter une photo</span>
            </div>
            <input 
              type="file" 
              ref="photoInput" 
              @change="handlePhotoUpload" 
              accept="image/*" 
              class="photo-input" 
            />
            <button type="button" @click="triggerPhotoInput" class="btn btn-secondary photo-upload-btn">
              {{ formData.image ? 'Changer la photo' : 'Choisir une photo' }}
            </button>
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
        <p>Êtes-vous sûr de vouloir supprimer la table <strong>{{ tableToDelete?.table_number }}</strong> ?</p>
        <div class="modal-footer">
          <button @click="cancelDelete" class="btn btn-cancel">Annuler</button>
          <button @click="deleteTable" class="btn btn-delete" :disabled="deleting">
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

const API_URL = getApiUrl(API_ENDPOINTS.TABLE)

const tables = ref([])
const loading = ref(false)
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingTable = ref(null)
const tableToDelete = ref(null)
const saving = ref(false)
const deleting = ref(false)
const message = ref('')
const messageType = ref('')

const formData = ref({
  table_number: '',
  status: 'disponible',
  image: null
})

const photoInput = ref(null)

const fetchTables = async () => {
  loading.value = true
  try {
    const response = await fetch(API_URL)
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`)
    const data = await response.json()
    if (data.success) {
      tables.value = Array.isArray(data.data) ? data.data : []
    } else {
      showMessage(data.message || 'Erreur lors du chargement des tables', 'error')
      tables.value = []
    }
  } catch (error) {
    showMessage('Erreur: ' + error.message, 'error')
    tables.value = []
  } finally {
    loading.value = false
  }
}

const openAddModal = () => {
  editingTable.value = null
  formData.value = { 
    table_number: '', 
    status: 'disponible',
    image: null
  }
  showModal.value = true
}

const openEditModal = (table) => {
  editingTable.value = table
  formData.value = {
    table_number: String(table.table_number),
    status: table.status || 'disponible',
    image: table.image || null
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingTable.value = null
  formData.value = { 
    table_number: '', 
    status: 'disponible',
    image: null
  }
}

const saveTable = async () => {
  saving.value = true
  try {
    const url = editingTable.value 
      ? `${API_URL}?id=${editingTable.value.id}`
      : API_URL
    
    const method = editingTable.value ? 'PUT' : 'POST'
    const tableNumber = String(formData.value.table_number || '').trim()
    
    if (!tableNumber) {
      showMessage('Le numéro de table est requis', 'error')
      saving.value = false
      return
    }
    
    const requestData = {
      table_number: tableNumber,
      status: formData.value.status || 'disponible',
      image: formData.value.image
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
      showMessage(editingTable.value ? 'Table modifiée avec succès' : 'Table ajoutée avec succès', 'success')
      closeModal()
      fetchTables()
    } else {
      showMessage(data.message || 'Erreur lors de l\'enregistrement', 'error')
    }
  } catch (error) {
    showMessage('Erreur: ' + (error.message || 'Erreur de connexion'), 'error')
  } finally {
    saving.value = false
  }
}

const confirmDelete = (table) => {
  tableToDelete.value = table
  showDeleteModal.value = true
}

const cancelDelete = () => {
  showDeleteModal.value = false
  tableToDelete.value = null
}

const deleteTable = async () => {
  if (!tableToDelete.value || !tableToDelete.value.id) {
    showMessage('Erreur: ID de table manquant', 'error')
    cancelDelete()
    return
  }
  
  deleting.value = true
  try {
    const tableId = tableToDelete.value.id
    const url = `${API_URL}?id=${tableId}`
    
    console.log('Deleting table with ID:', tableId)
    
    const response = await fetch(url, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    })
    
    console.log('Delete response status:', response.status)
    
    if (!response.ok) {
      const errorText = await response.text()
      console.error('Delete error response:', errorText)
      let errorData
      try {
        errorData = JSON.parse(errorText)
      } catch (e) {
        errorData = { message: errorText || `HTTP error! status: ${response.status}` }
      }
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    console.log('Delete response data:', data)
    if (data.success) {
      showMessage('Table supprimée avec succès', 'success')
      cancelDelete()
      fetchTables()
    } else {
      showMessage(data.message || 'Erreur lors de la suppression', 'error')
    }
  } catch (error) {
    console.error('Delete error:', error)
    showMessage('Erreur: ' + (error.message || 'Erreur de connexion'), 'error')
  } finally {
    deleting.value = false
  }
}

const getStatusLabel = (status) => {
  const labels = {
    'available': 'Disponible',
    'occupied': 'Occupée',
    'reserved': 'Réservée',
    'unavailable': 'Indisponible',
    'disponible': 'Disponible',
    'occupée': 'Occupée',
    'réservée': 'Réservée',
    'indisponible': 'Indisponible'
  }
  return labels[status?.toLowerCase()] || status || 'Disponible'
}

const getStatusClass = (status) => {
  const classes = {
    'available': 'badge-success',
    'occupied': 'badge-warning',
    'reserved': 'badge-info',
    'unavailable': 'badge-danger',
    'disponible': 'badge-success',
    'occupée': 'badge-warning',
    'réservée': 'badge-info',
    'indisponible': 'badge-danger'
  }
  return classes[status?.toLowerCase()] || 'badge-default'
}

const triggerPhotoInput = () => {
  if (photoInput.value) {
    photoInput.value.click()
  }
}

const handlePhotoUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return
  
  if (!file.type.startsWith('image/')) {
    showMessage('Veuillez sélectionner un fichier image', 'error')
    return
  }
  
  if (file.size > 2 * 1024 * 1024) {
    showMessage('L\'image ne doit pas dépasser 2MB', 'error')
    return
  }
  
  const reader = new FileReader()
  reader.onload = (e) => {
    formData.value.image = e.target.result
  }
  reader.readAsDataURL(file)
}

const removePhoto = () => {
  formData.value.image = null
  if (photoInput.value) {
    photoInput.value.value = ''
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
  fetchTables()
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

.table-photo-cell {
  width: 120px;
}

.table-photo-preview {
  position: relative;
  width: 80px;
  height: 80px;
  margin: 0 auto;
}

.table-photo-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 6px;
  border: 2px solid #e0e0e0;
}

.remove-photo-btn {
  position: absolute;
  top: -8px;
  right: -8px;
  background: #e74c3c;
  color: white;
  border: none;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  cursor: pointer;
  font-size: 1.2rem;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.remove-photo-btn:hover {
  background: #c0392b;
}

.download-photo-btn {
  position: absolute;
  bottom: -8px;
  right: -8px;
  background: #3498db;
  color: white;
  border: none;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  cursor: pointer;
  font-size: 0.9rem;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.download-photo-btn:hover {
  background: #2980b9;
}

.no-photo {
  text-align: center;
  color: #95a5a6;
  font-size: 0.85rem;
  padding: 1rem;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 600;
}

.badge-success {
  background: #27ae60;
  color: white;
}

.badge-warning {
  background: #f39c12;
  color: white;
}

.badge-info {
  background: #3498db;
  color: white;
}

.badge-danger {
  background: #e74c3c;
  color: white;
}

.badge-default {
  background: #95a5a6;
  color: white;
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

.photo-placeholder {
  width: 200px;
  height: 200px;
  border: 2px dashed #ddd;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #95a5a6;
  transition: all 0.3s ease;
}

.photo-placeholder:hover {
  border-color: #3498db;
  color: #3498db;
}

.photo-preview {
  position: relative;
  width: 200px;
  height: 200px;
  border-radius: 8px;
  overflow: hidden;
}

.photo-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.photo-input {
  display: none;
}

.photo-upload-btn {
  margin-top: 0.5rem;
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

  .table-photo-cell {
    min-width: 120px;
  }

  .table-photo-preview {
    width: 80px;
    height: 80px;
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

  .photo-preview {
    width: 150px;
    height: 150px;
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

  .table-photo-preview {
    width: 60px;
    height: 60px;
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

  .photo-preview {
    width: 120px;
    height: 120px;
  }

  .photo-upload-btn {
    padding: 0.6rem 1rem;
    font-size: 0.85rem;
  }
}
</style>


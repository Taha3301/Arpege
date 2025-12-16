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
            <th>Numéro de Table</th>
            <th>Statut</th>
            <th>Photo</th>
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
            <td>{{ table.table_number }}</td>
            <td>
              <span :class="['badge', getStatusClass(table.status)]">
                {{ getStatusLabel(table.status) }}
              </span>
            </td>
            <td class="table-photo-cell">
              <div v-if="getTablePhotoSync(table.id)" class="table-photo-preview">
                <img :src="getTablePhotoSync(table.id)" :alt="`Table ${table.table_number}`" />
                <button @click="removeTablePhoto(table.id)" class="remove-photo-btn" title="Supprimer la photo">×</button>
                <button @click="downloadTablePhoto(table.id)" class="download-photo-btn" title="Télécharger la photo">⬇</button>
              </div>
              <div v-else class="no-photo">Aucune photo</div>
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
          <div class="form-group">
            <label>Photo de la table</label>
            <div class="photo-upload-section">
              <div v-if="formData.photoPreview" class="photo-preview">
                <img :src="formData.photoPreview" alt="Aperçu" />
                <button type="button" @click="removePhoto" class="remove-preview-btn">×</button>
              </div>
              <div v-else-if="editingTable && getTablePhotoSync(editingTable.id)" class="photo-preview">
                <img :src="getTablePhotoSync(editingTable.id)" alt="Photo actuelle" />
                <button type="button" @click="removePhoto" class="remove-preview-btn">×</button>
              </div>
              <input 
                type="file" 
                @change="handlePhotoUpload" 
                accept="image/*" 
                ref="photoInput"
                class="photo-input"
                id="table-photo-input"
              />
              <label for="table-photo-input" class="photo-upload-btn">
                {{ formData.photoPreview || (editingTable && getTablePhotoSync(editingTable.id)) ? 'Changer la photo' : 'Ajouter une photo' }}
              </label>
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
  photoPreview: null,
  photoFile: null
})

const photoInput = ref(null)

// IndexedDB database name and version
const DB_NAME = 'TablesPhotosDB'
const DB_VERSION = 1
const STORE_NAME = 'table_photos'
let db = null

// Initialize IndexedDB
const initDB = () => {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)
    
    request.onerror = () => {
      console.error('IndexedDB error:', request.error)
      reject(request.error)
    }
    
    request.onsuccess = () => {
      db = request.result
      resolve(db)
    }
    
    request.onupgradeneeded = (event) => {
      const database = event.target.result
      if (!database.objectStoreNames.contains(STORE_NAME)) {
        const objectStore = database.createObjectStore(STORE_NAME, { keyPath: 'tableId' })
        objectStore.createIndex('tableId', 'tableId', { unique: true })
      }
    }
  })
}

const fetchTables = async () => {
  loading.value = true
  try {
    const response = await fetch(API_URL)
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      tables.value = Array.isArray(data.data) ? data.data : []
      // Load photos for all tables
      if (tables.value.length > 0) {
        await Promise.all(tables.value.map(table => loadTablePhoto(table.id)))
      }
    } else {
      showMessage(data.message || 'Erreur lors du chargement des tables', 'error')
      tables.value = []
    }
  } catch (error) {
    console.error('Fetch error:', error)
    showMessage('Erreur de connexion: ' + error.message, 'error')
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
    photoPreview: null,
    photoFile: null
  }
  if (photoInput.value) {
    photoInput.value.value = ''
  }
  showModal.value = true
}

const openEditModal = (table) => {
  editingTable.value = table
  // Map status from database to form value
  const statusMap = {
    'available': 'available',
    'occupied': 'occupied',
    'reserved': 'reserved',
    'unavailable': 'unavailable',
    'disponible': 'available',
    'occupée': 'occupied',
    'réservée': 'reserved',
    'indisponible': 'unavailable'
  }
  
  formData.value = {
    table_number: String(table.table_number), // Convert to string
    status: table.status || 'disponible', // Use status directly from database
    photoPreview: null,
    photoFile: null
  }
  if (photoInput.value) {
    photoInput.value.value = ''
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingTable.value = null
  formData.value = { 
    table_number: '', 
    status: 'disponible',
    photoPreview: null,
    photoFile: null
  }
  if (photoInput.value) {
    photoInput.value.value = ''
  }
}

const saveTable = async () => {
  saving.value = true
  try {
    const url = editingTable.value 
      ? `${API_URL}?id=${editingTable.value.id}`
      : API_URL
    
    const method = editingTable.value ? 'PUT' : 'POST'
    
    // Ensure table_number is a string and trim it
    const tableNumber = String(formData.value.table_number || '').trim()
    
    if (!tableNumber) {
      showMessage('Le numéro de table est requis', 'error')
      saving.value = false
      return
    }
    
    const requestData = {
      table_number: tableNumber,
      status: formData.value.status || 'disponible'
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
      // Save photo if one was uploaded
      const tableId = editingTable.value ? editingTable.value.id : data.data?.id
      if (tableId && formData.value.photoFile) {
        try {
          await saveTablePhoto(tableId, formData.value.photoFile)
          // Update cache
          await loadTablePhoto(tableId)
        } catch (error) {
          console.error('Error saving photo:', error)
          showMessage('Table enregistrée mais erreur lors de la sauvegarde de la photo', 'error')
        }
      }
      
      showMessage(editingTable.value ? 'Table modifiée avec succès' : 'Table ajoutée avec succès', 'success')
      closeModal()
      fetchTables()
    } else {
      showMessage(data.message || 'Erreur lors de l\'enregistrement', 'error')
    }
  } catch (error) {
    console.error('Error:', error)
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
      // Remove photo when table is deleted
      if (tableToDelete.value?.id) {
        await removeTablePhoto(tableToDelete.value.id)
      }
      
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

// Photo handling functions
const handlePhotoUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return
  
  // Validate file type
  if (!file.type.startsWith('image/')) {
    showMessage('Veuillez sélectionner un fichier image', 'error')
    return
  }
  
  // Validate file size (max 5MB)
  if (file.size > 5 * 1024 * 1024) {
    showMessage('L\'image ne doit pas dépasser 5MB', 'error')
    return
  }
  
  // Read file as base64
  const reader = new FileReader()
  reader.onload = (e) => {
    formData.value.photoPreview = e.target.result
    formData.value.photoFile = file
  }
  reader.onerror = () => {
    showMessage('Erreur lors de la lecture de l\'image', 'error')
  }
  reader.readAsDataURL(file)
}

const removePhoto = () => {
  formData.value.photoPreview = null
  formData.value.photoFile = null
  if (photoInput.value) {
    photoInput.value.value = ''
  }
}

const saveTablePhoto = async (tableId, file) => {
  if (!db) {
    await initDB()
  }
  
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = async (e) => {
      try {
        // Get file extension
        const fileName = file.name || `table_${tableId}.jpg`
        const fileExtension = fileName.split('.').pop() || 'jpg'
        const finalFileName = `table_${tableId}.${fileExtension}`
        
        // Store in IndexedDB
        const photoData = {
          tableId: tableId,
          imageData: e.target.result, // base64 string
          fileName: finalFileName,
          mimeType: file.type || 'image/jpeg',
          uploadedAt: new Date().toISOString()
        }
        
        const transaction = db.transaction([STORE_NAME], 'readwrite')
        const store = transaction.objectStore(STORE_NAME)
        const request = store.put(photoData)
        
        request.onsuccess = async () => {
          console.log(`Photo saved for table ${tableId} in IndexedDB`)
          console.log(`Photo filename: ${finalFileName}`)
          // Photo is stored in IndexedDB with filename: table_{id}.{ext}
          // Use download button to export to tables folder if needed
          resolve()
        }
        
        request.onerror = () => {
          console.error('Error saving photo to IndexedDB:', request.error)
          reject(request.error)
        }
      } catch (error) {
        console.error('Error processing photo:', error)
        reject(error)
      }
    }
    reader.onerror = () => {
      reject(new Error('Error reading file'))
    }
    reader.readAsDataURL(file)
  })
}

// Save photo to file system (tables folder)
const savePhotoToFileSystem = async (tableId, file, fileName) => {
  // Check if File System Access API is supported
  if (!window.showDirectoryPicker) {
    console.log('File System Access API not supported, skipping file system save')
    return
  }
  
  try {
    // Request directory access (user will select the tables folder)
    // For automatic saving, we'll use a download approach instead
    // Convert file to blob
    const blob = file
    
    // Create download link to save in tables folder
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = fileName
    link.style.display = 'none'
    document.body.appendChild(link)
    
    // Note: This will download to user's default download folder
    // For actual project folder, user would need to use File System Access API manually
    // For now, we'll store in IndexedDB and provide download option
    
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error saving to file system:', error)
    throw error
  }
}

const getTablePhoto = async (tableId) => {
  if (!tableId) return null
  
  if (!db) {
    try {
      await initDB()
    } catch (error) {
      console.error('Error initializing DB:', error)
      return null
    }
  }
  
  return new Promise((resolve) => {
    const transaction = db.transaction([STORE_NAME], 'readonly')
    const store = transaction.objectStore(STORE_NAME)
    const request = store.get(tableId)
    
    request.onsuccess = () => {
      const result = request.result
      resolve(result ? result.imageData : null)
    }
    
    request.onerror = () => {
      console.error('Error getting photo:', request.error)
      resolve(null)
    }
  })
}

// Reactive photo URLs cache
const photoCache = ref({})

const getTablePhotoSync = (tableId) => {
  return photoCache.value[tableId] || null
}

const loadTablePhoto = async (tableId) => {
  if (!tableId) return
  const photo = await getTablePhoto(tableId)
  if (photo) {
    photoCache.value[tableId] = photo
  }
}

const removeTablePhoto = async (tableId) => {
  if (!tableId) return
  
  if (!db) {
    try {
      await initDB()
    } catch (error) {
      console.error('Error initializing DB:', error)
      showMessage('Erreur lors de la suppression de la photo', 'error')
      return
    }
  }
  
  return new Promise((resolve) => {
    const transaction = db.transaction([STORE_NAME], 'readwrite')
    const store = transaction.objectStore(STORE_NAME)
    const request = store.delete(tableId)
    
    request.onsuccess = () => {
      // Remove from cache
      delete photoCache.value[tableId]
      showMessage('Photo supprimée avec succès', 'success')
      // Refresh tables to update UI
      fetchTables()
      resolve()
    }
    
    request.onerror = () => {
      console.error('Error deleting photo:', request.error)
      showMessage('Erreur lors de la suppression de la photo', 'error')
      resolve()
    }
  })
}

const showMessage = (msg, type) => {
  message.value = msg
  messageType.value = type
  setTimeout(() => {
    message.value = ''
  }, 3000)
}

// Download photo function
const downloadTablePhoto = async (tableId) => {
  if (!tableId) return
  
  if (!db) {
    try {
      await initDB()
    } catch (error) {
      console.error('Error initializing DB:', error)
      showMessage('Erreur lors du téléchargement de la photo', 'error')
      return
    }
  }
  
  const transaction = db.transaction([STORE_NAME], 'readonly')
  const store = transaction.objectStore(STORE_NAME)
  const request = store.get(tableId)
  
  request.onsuccess = () => {
    const photoData = request.result
    if (!photoData) {
      showMessage('Aucune photo trouvée pour cette table', 'error')
      return
    }
    
    // Convert base64 to blob
    const byteCharacters = atob(photoData.imageData.split(',')[1])
    const byteNumbers = new Array(byteCharacters.length)
    for (let i = 0; i < byteCharacters.length; i++) {
      byteNumbers[i] = byteCharacters.charCodeAt(i)
    }
    const byteArray = new Uint8Array(byteNumbers)
    const blob = new Blob([byteArray], { type: photoData.mimeType })
    
    // Create download link
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = photoData.fileName || `table_${tableId}.jpg`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
    
    showMessage('Photo téléchargée avec succès', 'success')
  }
  
  request.onerror = () => {
    console.error('Error getting photo for download:', request.error)
    showMessage('Erreur lors du téléchargement de la photo', 'error')
  }
}

onMounted(async () => {
  // Initialize IndexedDB
  try {
    await initDB()
    console.log('IndexedDB initialized successfully')
  } catch (error) {
    console.error('Failed to initialize IndexedDB:', error)
    showMessage('Erreur d\'initialisation de la base de données locale', 'error')
  }
  
  // Fetch tables
  await fetchTables()
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

.photo-upload-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.photo-preview {
  position: relative;
  width: 200px;
  height: 200px;
  border: 2px dashed #ddd;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.photo-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.remove-preview-btn {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  background: rgba(231, 76, 60, 0.9);
  color: white;
  border: none;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 1.5rem;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.remove-preview-btn:hover {
  background: rgba(192, 57, 43, 1);
}

.photo-input {
  display: none;
}

.photo-upload-btn {
  display: inline-block;
  padding: 0.75rem 1.5rem;
  background: #3498db;
  color: white;
  border-radius: 6px;
  cursor: pointer;
  text-align: center;
  transition: all 0.3s ease;
  font-size: 0.95rem;
  width: fit-content;
}

.photo-upload-btn:hover {
  background: #2980b9;
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


<template>
  <div class="crud-container">
    <div class="crud-header">
      <div class="header-left">
        <button @click="openAddModal" class="btn btn-primary">
          <span>+</span> Ajouter Catégorie
        </button>
      </div>
      <div class="header-right">
        <div class="search-box">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Rechercher une catégorie..." 
            class="search-input"
          />
          <span class="search-icon">🔍</span>
        </div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Date de Création</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="4" class="loading">Chargement...</td>
          </tr>
          <tr v-else-if="filteredCategories.length === 0">
            <td colspan="4" class="empty">Aucune catégorie trouvée</td>
          </tr>
          <tr v-else v-for="category in paginatedCategories" :key="category.id">
            <td>{{ category.id }}</td>
            <td>{{ category.name }}</td>
            <td>{{ formatDate(category.created_at) }}</td>
            <td class="actions">
              <button @click="openEditModal(category)" class="btn btn-edit">Modifier</button>
              <button @click="confirmDelete(category)" class="btn btn-delete">Supprimer</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="cards-container">
      <div v-if="loading" class="loading">Chargement...</div>
      <div v-else-if="filteredCategories.length === 0" class="empty">Aucune catégorie trouvée</div>
      <div v-else v-for="category in paginatedCategories" :key="category.id" class="data-card">
        <div class="card-header">
          <div class="card-id-wrapper">
            <span class="card-label">ID:</span>
            <span class="card-id">#{{ category.id }}</span>
          </div>
        </div>
        
        <div class="card-body">
          <div class="card-info">
            <div class="info-row">
              <span class="info-label">Nom</span>
              <span class="info-value">{{ category.name }}</span>
            </div>
            
            <div class="info-row">
              <span class="info-label">Créée le</span>
              <span class="info-value">{{ formatDate(category.created_at) }}</span>
            </div>
          </div>
        </div>
        
        <div class="card-actions">
          <button @click="openEditModal(category)" class="btn btn-edit">
            <span>✏️</span> Modifier
          </button>
          <button @click="confirmDelete(category)" class="btn btn-delete">
            <span>🗑️</span> Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination-container" v-if="totalPages > 1">
      <div class="pagination-info">
        Page {{ currentPage }} sur {{ totalPages }}
      </div>
      <div class="pagination-controls">
        <button 
          @click="setPage(currentPage - 1)" 
          :disabled="currentPage === 1" 
          class="page-btn prev-btn"
        >
          &laquo; Précédent
        </button>
        <div class="page-numbers">
          <button 
            v-for="page in totalPages" 
            :key="page" 
            @click="setPage(page)" 
            :class="['page-btn', { active: currentPage === page }]"
          >
            {{ page }}
          </button>
        </div>
        <button 
          @click="setPage(currentPage + 1)" 
          :disabled="currentPage === totalPages" 
          class="page-btn next-btn"
        >
          Suivant &raquo;
        </button>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingCategory ? 'Modifier Catégorie' : 'Ajouter Catégorie' }}</h2>
          <button @click="closeModal" class="close-btn">&times;</button>
        </div>
        <form @submit.prevent="saveCategory" class="modal-body">
          <div class="form-group">
            <label>Nom *</label>
            <input v-model="formData.name" type="text" required />
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
        <p>Êtes-vous sûr de vouloir supprimer la catégorie <strong>{{ categoryToDelete?.name }}</strong> ?</p>
        <div class="modal-footer">
          <button @click="cancelDelete" class="btn btn-cancel">Annuler</button>
          <button @click="deleteCategory" class="btn btn-delete" :disabled="deleting">
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
import { ref, onMounted, computed, watch } from 'vue'
import { getApiUrl, API_ENDPOINTS } from '../../config/api.js'

const API_URL = getApiUrl(API_ENDPOINTS.CATEGORY)

const categories = ref([])
const loading = ref(false)
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingCategory = ref(null)
const categoryToDelete = ref(null)
const saving = ref(false)
const deleting = ref(false)
const message = ref('')
const messageType = ref('')
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = 8

const formData = ref({
  name: ''
})

const filteredCategories = computed(() => {
  return categories.value.filter(category => 
    category.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})

const paginatedCategories = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredCategories.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(filteredCategories.value.length / itemsPerPage))

const setPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    const container = document.querySelector('.crud-container')
    if (container) container.scrollIntoView({ behavior: 'smooth' })
  }
}

// Reset page when searching
watch(searchQuery, () => {
  currentPage.value = 1
})

const fetchCategories = async () => {
  loading.value = true
  try {
    const response = await fetch(API_URL)
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      // Ensure data is an array
      categories.value = Array.isArray(data.data) ? data.data : []
    } else {
      showMessage(data.message || 'Erreur lors du chargement des catégories', 'error')
      categories.value = []
    }
  } catch (error) {
    console.error('Fetch error:', error)
    showMessage('Erreur de connexion: ' + error.message, 'error')
    categories.value = []
  } finally {
    loading.value = false
  }
}

const openAddModal = () => {
  editingCategory.value = null
  formData.value = { name: '' }
  showModal.value = true
}

const openEditModal = (category) => {
  editingCategory.value = category
  formData.value = {
    name: category.name
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingCategory.value = null
  formData.value = { name: '' }
}

const saveCategory = async () => {
  saving.value = true
  try {
    const url = editingCategory.value 
      ? `${API_URL}?id=${editingCategory.value.id}`
      : API_URL
    
    const method = editingCategory.value ? 'PUT' : 'POST'
    
    // Prepare data
    const requestData = {
      name: formData.value.name.trim()
    }
    
    const response = await fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestData)
    })
    
    const data = await response.json()
    
    if (data.success) {
      showMessage(editingCategory.value ? 'Catégorie modifiée avec succès' : 'Catégorie ajoutée avec succès', 'success')
      closeModal()
      fetchCategories()
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

const confirmDelete = (category) => {
  categoryToDelete.value = category
  showDeleteModal.value = true
}

const cancelDelete = () => {
  showDeleteModal.value = false
  categoryToDelete.value = null
}

const deleteCategory = async () => {
  deleting.value = true
  try {
    const response = await fetch(`${API_URL}?id=${categoryToDelete.value.id}`, {
      method: 'DELETE'
    })
    
    const data = await response.json()
    
    if (data.success) {
      showMessage('Catégorie supprimée avec succès', 'success')
      cancelDelete()
      fetchCategories()
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
  fetchCategories()
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
  gap: 1rem;
}

.header-left, .header-right {
  display: flex;
  align-items: center;
}

.search-box {
  position: relative;
  min-width: 250px;
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: #f8fafc;
}

.search-input:focus {
  outline: none;
  border-color: #3498db;
  background: white;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.search-icon {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 1rem;
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

.cards-container {
  display: none;
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

.pagination-container {
  margin-top: 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 1rem 0;
  border-top: 1px solid #f1f5f9;
}

.pagination-info {
  font-size: 0.9rem;
  color: #64748b;
  font-weight: 500;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-numbers {
  display: flex;
  gap: 0.35rem;
}

.page-btn {
  padding: 0.5rem 0.85rem;
  border: 1px solid #e2e8f0;
  background: white;
  color: #475569;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  min-width: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.page-btn:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #1e293b;
}

.page-btn.active {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f1f5f9;
}

.prev-btn, .next-btn {
  padding: 0.5rem 1rem;
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
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
  font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #3498db;
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
    background: #f8f9fa;
    box-shadow: none;
  }

  .crud-header {
    flex-direction: column-reverse;
    gap: 1.25rem;
    align-items: stretch;
  }

  .header-right, .search-box {
    width: 100%;
  }

  .search-input {
    padding: 0.85rem 1rem 0.85rem 2.75rem;
    font-size: 1rem;
  }

  .btn-primary {
    justify-content: center;
    width: 100%;
    padding: 1rem;
    font-weight: 600;
  }

  /* Hide table view */
  .table-container {
    display: none;
  }

  /* Show and style card view */
  .cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
  }

  .data-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #edf2f7;
    transition: transform 0.2s ease;
  }

  .data-card:active {
    transform: scale(0.98);
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
  }

  .card-id-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .card-label {
    font-size: 0.8rem;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 700;
  }

  .card-id {
    font-family: monospace;
    font-weight: 700;
    color: #334155;
    background: #f1f5f9;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.9rem;
  }

  .card-body {
    margin-bottom: 1.25rem;
  }

  .info-row {
    margin-bottom: 1rem;
  }

  .info-row:last-child {
    margin-bottom: 0;
  }

  .info-label {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    margin-bottom: 0.35rem;
  }

  .info-value {
    display: block;
    color: #1e293b;
    font-weight: 500;
    font-size: 1rem;
  }

  .card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
  }

  .card-actions .btn {
    width: 100%;
    margin: 0;
    padding: 0.75rem;
    font-size: 0.9rem;
    justify-content: center;
  }

  .modal-content {
    width: 95%;
    max-width: none;
    margin: 1rem;
    border-radius: 16px;
  }

  .modal-footer {
    flex-direction: column-reverse;
    gap: 0.75rem;
  }

  .modal-footer .btn {
    width: 100%;
  }

  .message {
    bottom: 1.5rem;
    left: 1rem;
    right: 1rem;
    width: auto;
    text-align: center;
  }

  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }

  .page-numbers {
    order: 3;
    width: 100%;
    justify-content: center;
    margin-top: 0.5rem;
  }
}

@media (max-width: 480px) {
  .crud-container {
    padding: 0.75rem;
  }

  .data-table {
    min-width: 400px;
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
  .form-group textarea {
    padding: 0.6rem;
    font-size: 0.9rem;
  }
}
</style>


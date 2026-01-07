<template>
  <div class="dashboard-container">
    <!-- Overlay for mobile when sidebar is open -->
    <div 
      v-if="isSidebarOpen" 
      class="sidebar-overlay" 
      @click="toggleSidebar"
    ></div>
    
    <aside class="sidebar" :class="{ 'sidebar-open': isSidebarOpen }">
      <div class="sidebar-header">
        <div class="sidebar-header-top">
          <h2>Admin Panel</h2>
          <button class="close-sidebar-btn" @click="toggleSidebar" aria-label="Close sidebar">
            ×
          </button>
        </div>
        <p class="welcome-text" v-if="props.userName">
          Bonjour {{ props.userName }}
          <span class="role-label" v-if="roleLabel">({{ roleLabel }})</span>
        </p>
      </div>
      <nav class="sidebar-nav">
        <ul class="nav-list">
          <li>
            <a href="#" @click.prevent="handleNavClick('securite')" :class="{ active: activeView === 'securite' }">
              <span class="icon">📊</span>
              <span>Tableau de Bord</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('crud-employeur')" :class="{ active: activeView === 'crud-employeur' }">
              <span class="icon">👥</span>
              <span>Gestion des Employés</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('crud-admin')" :class="{ active: activeView === 'crud-admin' }">
              <span class="icon">👤</span>
              <span>Gestion des Administrateurs</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('crud-category')" :class="{ active: activeView === 'crud-category' }">
              <span class="icon">📁</span>
              <span>Catégories du Menu</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('crud-table')" :class="{ active: activeView === 'crud-table' }">
              <span class="icon">🪑</span>
              <span>Gestion des Tables</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('crud-produit')" :class="{ active: activeView === 'crud-produit' }">
              <span class="icon">🍴</span>
              <span>Carte des Plats</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('historique-commande')" :class="{ active: activeView === 'historique-commande' }">
              <span class="icon">📋</span>
              <span>Historique des Commandes</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('historique-jour')" :class="{ active: activeView === 'historique-jour' }">
              <span class="icon">📅</span>
              <span>Consommation Ingrédients</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('consommation-produits')" :class="{ active: activeView === 'consommation-produits' }">
              <span class="icon">📊</span>
              <span>Consommation Produits</span>
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="handleNavClick('gestion-stock')" :class="{ active: activeView === 'gestion-stock' }">
              <span class="icon">📦</span>
              <span>Gestion des Stocks</span>
            </a>
          </li>
        </ul>
      </nav>
      <div class="sidebar-footer">
        <button @click="handleLogout" class="logout-btn">
          <span class="icon">🚪</span>
          <span>Logout</span>
        </button>
      </div>
    </aside>
    <main class="main-content">
      <div class="content-header">
        <button class="hamburger-btn" @click="toggleSidebar" aria-label="Toggle sidebar">
          <span class="hamburger-line"></span>
          <span class="hamburger-line"></span>
          <span class="hamburger-line"></span>
        </button>
        <div>
          <h1>{{ getViewTitle() }}</h1>
          <p v-if="props.userName" class="content-greeting">
            Connecté en tant que {{ props.userName }}
            <span v-if="roleLabel">({{ roleLabel }})</span>
          </p>
        </div>
      </div>
      <div class="content-body">
        <component :is="getActiveComponent()" />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import DashboardStats from './DashboardStats.vue'
import CrudEmployee from './CrudEmployee.vue'
import CrudAdmin from './CrudAdmin.vue'
import CrudCategory from './CrudCategory.vue'
import CrudTable from './CrudTable.vue'
import CrudProduct from './CrudProduct.vue'
import OrderHistory from './OrderHistory.vue'
import IngredientUsage from './IngredientUsage.vue'
import ProductConsumption from './ProductConsumption.vue'
import CrudStock from './CrudStock.vue'

const props = defineProps({
  userName: {
    type: String,
    default: ''
  },
  userRole: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['logout'])
const activeView = ref('securite')
const isSidebarOpen = ref(false)

const setActiveView = (view) => {
  activeView.value = view
}

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

const handleNavClick = (view) => {
  setActiveView(view)
  // Close sidebar on mobile after navigation
  if (window.innerWidth <= 768) {
    isSidebarOpen.value = false
  }
}

const handleLogout = () => {
  emit('logout')
}

const roleLabel = computed(() => {
  if (props.userRole === 'admin') return 'Administrateur'
  if (props.userRole === 'employee') return 'Employé'
  return props.userRole || ''
})

const getViewTitle = () => {
  const titles = {
    'crud-employeur': 'Gestion des Employés',
    'crud-admin': 'Gestion des Administrateurs',
    'crud-category': 'Catégories du Menu',
    'crud-table': 'Gestion des Tables',
    'crud-produit': 'Carte des Plats',
    'securite': 'Tableau de Bord',
    'historique-commande': 'Historique des Commandes',
    'historique-jour': 'Consommation Ingrédients',
    'consommation-produits': 'Consommation Produits',
    'gestion-stock': 'Gestion des Stocks'
  }
  return titles[activeView.value] || 'Dashboard'
}

const getActiveComponent = () => {
  if (activeView.value === 'securite') {
    return DashboardStats
  } else if (activeView.value === 'crud-employeur') {
    return CrudEmployee
  } else if (activeView.value === 'crud-admin') {
    return CrudAdmin
  } else if (activeView.value === 'crud-category') {
    return CrudCategory
  } else if (activeView.value === 'crud-table') {
    return CrudTable
  } else if (activeView.value === 'crud-produit') {
    return CrudProduct
  } else if (activeView.value === 'historique-commande') {
    return OrderHistory
  } else if (activeView.value === 'historique-jour') {
    return IngredientUsage
  } else if (activeView.value === 'consommation-produits') {
    return ProductConsumption
  } else if (activeView.value === 'gestion-stock') {
    return CrudStock
  } else {
    // Placeholder component for other views
    return {
      template: `<div class="placeholder-content">
        <p>Contenu pour: ${getViewTitle()}</p>
        <p>Cette section sera implémentée prochainement.</p>
      </div>`
    }
  }
}
</script>

<style scoped>
.dashboard-container {
  display: flex;
  min-height: 100vh;
  background: #f5f5f5;
}

.sidebar-overlay {
  display: none;
}

.sidebar {
  width: 280px;
  background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
  color: white;
  display: flex;
  flex-direction: column;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
  position: fixed;
  height: 100vh;
  overflow-y: auto;
  z-index: 1000;
  transition: transform 0.3s ease-in-out;
}

.sidebar-header {
  padding: 2rem 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.close-sidebar-btn {
  display: none;
  background: transparent;
  border: none;
  color: white;
  font-size: 2rem;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  line-height: 1;
  transition: transform 0.2s ease;
}

.close-sidebar-btn:hover {
  transform: scale(1.1);
}

.sidebar-header h2 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  color: white;
}

.welcome-text {
  margin-top: 0.5rem;
  font-size: 0.9rem;
  color: rgba(255, 255, 255, 0.85);
}

.role-label {
  font-size: 0.85rem;
  color: #ecf0f1;
}

.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
}

.nav-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.nav-list li {
  margin: 0;
}

.nav-list a {
  display: flex;
  align-items: center;
  padding: 1rem 1.5rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.3s ease;
  border-left: 3px solid transparent;
}

.nav-list a:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border-left-color: #3498db;
}

.nav-list a.active {
  background: rgba(255, 255, 255, 0.15);
  color: white;
  border-left-color: #3498db;
  font-weight: 600;
}

.nav-list .icon {
  margin-right: 0.75rem;
  font-size: 1.2rem;
  width: 24px;
  text-align: center;
}

.sidebar-footer {
  padding: 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.logout-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 1rem;
  background: rgba(231, 76, 60, 0.2);
  border: 1px solid rgba(231, 76, 60, 0.5);
  border-radius: 8px;
  color: white;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.3s ease;
}

.logout-btn:hover {
  background: rgba(231, 76, 60, 0.3);
  border-color: #e74c3c;
  transform: translateY(-2px);
}

.logout-btn .icon {
  margin-right: 0.5rem;
}

.main-content {
  flex: 1;
  margin-left: 280px;
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow-y: auto;
}

.content-header {
  background: white;
  padding: 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  border-bottom: 1px solid #e0e0e0;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.hamburger-btn {
  display: none;
  flex-direction: column;
  justify-content: space-around;
  width: 32px;
  height: 32px;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  z-index: 1001;
  transition: transform 0.3s ease;
}

.hamburger-btn:active {
  transform: scale(0.95);
}

.hamburger-line {
  width: 100%;
  height: 3px;
  background: #2c3e50;
  border-radius: 2px;
  transition: all 0.3s ease;
}

.content-header h1 {
  margin: 0;
  font-size: 2rem;
  color: #2c3e50;
  font-weight: 600;
}

.content-greeting {
  margin-top: 0.35rem;
  color: #7f8c8d;
  font-size: 0.95rem;
}

.content-body {
  flex: 1;
  padding: 2rem;
  overflow-y: auto;
  min-height: 0;
}

.placeholder-content {
  background: white;
  padding: 3rem;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.placeholder-content p {
  font-size: 1.1rem;
  color: #7f8c8d;
  margin: 0.5rem 0;
}

@media (max-width: 1024px) {
  .sidebar {
    width: 240px;
  }
  
  .main-content {
    margin-left: 240px;
  }
}

@media (max-width: 768px) {
  .dashboard-container {
    flex-direction: column;
  }

  .sidebar-overlay {
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    transition: opacity 0.3s ease;
  }

  .sidebar {
    width: 280px;
    max-width: 85vw;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    transform: translateX(-100%);
  }

  .sidebar.sidebar-open {
    transform: translateX(0);
  }

  .close-sidebar-btn {
    display: block;
  }

  .hamburger-btn {
    display: flex;
  }

  .sidebar-header {
    padding: 1rem;
  }

  .sidebar-header h2 {
    font-size: 1.2rem;
  }

  .welcome-text {
    font-size: 0.85rem;
  }

  .nav-list a {
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
  }

  .nav-list .icon {
    font-size: 1rem;
    width: 20px;
  }

  .sidebar-footer {
    padding: 1rem;
  }

  .logout-btn {
    padding: 0.6rem 0.8rem;
    font-size: 0.9rem;
  }
  
  .main-content {
    margin-left: 0;
    height: auto;
    min-height: 100vh;
  }

  .content-header {
    padding: 1rem;
  }

  .content-header h1 {
    font-size: 1.5rem;
  }

  .content-greeting {
    font-size: 0.85rem;
  }

  .content-body {
    padding: 1rem;
  }
}

@media (max-width: 480px) {
  .sidebar-header {
    padding: 0.75rem;
  }

  .sidebar-header h2 {
    font-size: 1rem;
  }

  .nav-list a {
    padding: 0.6rem 0.75rem;
    font-size: 0.85rem;
  }

  .nav-list .icon {
    font-size: 0.9rem;
    margin-right: 0.5rem;
  }

  .content-header {
    padding: 0.75rem;
  }

  .content-header h1 {
    font-size: 1.2rem;
  }

  .content-body {
    padding: 0.75rem;
  }
}
</style>
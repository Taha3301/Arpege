<script setup>
import { ref } from 'vue'
import logoImage from '../assets/logo.png'
import backgroundImage from '../assets/bg.jpg'

const emit = defineEmits(['login-success'])

import { getApiUrl, API_ENDPOINTS } from '../config/api.js'

const code = ref('')
const message = ref('')
const isLoading = ref(false)
const showSecurityModal = ref(false)

const API_URL = getApiUrl(API_ENDPOINTS.USER)

const handleConfirm = async () => {
  const trimmedCode = code.value.trim()
  
  if (!trimmedCode) {
    message.value = 'Please enter a code'
    setTimeout(() => {
      message.value = ''
    }, 3000)
    return
  }
  
  isLoading.value = true
  message.value = ''
  
  try {
    const response = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ code: trimmedCode })
    })
    
    const data = await response.json()
    
    if (data.success) {
      // Check if user account is active
      const userStatus = data.user.status || 'active' // Default to active if status not set
      
      if (userStatus === 'inactive') {
        message.value = 'Your account is inactive. Please contact an administrator.'
        code.value = ''
        isLoading.value = false
        
        setTimeout(() => {
          message.value = ''
        }, 4000)
        return
      }
      
      message.value = 'Login successful!'
      code.value = ''
      
      // Emit login success with full user data
      setTimeout(() => {
        emit('login-success', data.user)
      }, 1000)
    } else {
      message.value = data.message || 'Invalid code'
      isLoading.value = false
      
      setTimeout(() => {
        message.value = ''
      }, 3000)
    }
  } catch (error) {
    console.error('Login error:', error)
    if (error.name === 'TypeError' || error.message.includes('fetch')) {
      showSecurityModal.value = true
    } else {
      message.value = 'Connection error. Please try again.'
    }
    isLoading.value = false
    
    setTimeout(() => {
      message.value = ''
    }, 3000)
  }
}
</script>

<template>
  <div class="container" :style="{ backgroundImage: `url(${backgroundImage})` }">

    <div class="split-layout">
      <!-- LEFT : Logo -->
      <div class="left-side">
        <img :src="logoImage" alt="Arpege Logo" class="logo-image" />
      </div>

      <!-- RIGHT : Form -->
      <div class="right-side">
        <input
          type="password"
          v-model="code"
          placeholder="Enter code"
          class="code-input"
          :disabled="isLoading"
          @keyup.enter="handleConfirm"
        />

        <button
          @click="handleConfirm"
          class="confirm-button"
          :disabled="isLoading"
        >
          {{ isLoading ? 'Processing...' : 'Confirm' }}
        </button>
      </div>
    </div>

    <!-- Security Troubleshooting Modal -->
    <div v-if="showSecurityModal" class="modal-overlay">
      <div class="security-modal">
        <h3>🔒 Problème de Connexion Sécurisée</h3>
        <p>Votre navigateur bloque la connexion car le certificat de sécurité du serveur n'est pas reconnu.</p>
        
        <div class="steps">
          <p><strong>Pour corriger cela :</strong></p>
          <ol>
            <li>Cliquez sur le bouton ci-dessous pour ouvrir le serveur.</li>
            <li>Cliquez sur <strong>"Paramètres Avancés"</strong>.</li>
            <li>Cliquez sur <strong>"Continuer vers le site (non sécurisé)"</strong>.</li>
            <li>Revenez ici et essayez de vous reconnecter.</li>
          </ol>
        </div>

        <div class="modal-actions">
          <a :href="API_URL" target="_blank" class="fix-btn" @click="showSecurityModal = false">
            Ouvrir la page de sécurité
          </a>
          <button @click="showSecurityModal = false" class="close-modal-btn">Annuler</button>
        </div>
      </div>
    </div>

    <div
      v-if="message"
      class="message"
      :class="{
        'message-success': message.includes('successful'),
        'message-error':
          message.includes('Please') ||
          message.includes('inactive') ||
          message.includes('Invalid') ||
          message.includes('Connection')
      }"
    >
      {{ message }}
    </div>
  </div>
</template>


<style scoped>
/* MAIN CONTAINER */
.container {
  position: fixed;
  inset: 0;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

/* SPLIT LAYOUT */
.split-layout {
  display: flex;
  height: 100vh;
  width: 100%;
  backdrop-filter: blur(6px);
}

/* LEFT SIDE (LOGO) */
.left-side {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    to right,
    rgba(0, 0, 0, 0.45),
    rgba(0, 0, 0, 0.15)
  );
}

.logo-image {
  max-width: 70%;
  max-height: 70%;
  animation: logoAnimation 3s ease-in-out infinite;
}

/* RIGHT SIDE (FORM) */
.right-side {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 2rem;
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(14px);
  padding: 2rem;
}

.slogan {
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 700;
  color: white;
  text-align: center;
  text-shadow: 2px 2px 12px rgba(0, 0, 0, 0.5);
  margin: 0;
  animation: fadeInDown 1s ease-out;
}

.code-input {
  width: 100%;
  max-width: 400px;
  padding: 18px 24px;
  font-size: 1.1rem;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 12px;
  outline: none;
  transition: all 0.3s ease;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  color: #333;
  animation: fadeInUp 1s ease-out 0.2s both;
}

.code-input::placeholder {
  color: #999;
}

.code-input:focus {
  border-color: rgba(255, 255, 255, 0.8);
  background: rgba(255, 255, 255, 1);
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.2),
              0 0 0 4px rgba(52, 152, 219, 0.3);
  transform: translateY(-2px);
}

.code-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  background: rgba(255, 255, 255, 0.7);
}

.confirm-button {
  width: 100%;
  max-width: 400px;
  padding: 18px 24px;
  font-size: 1.2rem;
  font-weight: 600;
  border: none;
  border-radius: 12px;
  outline: none;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
  color: white;
  cursor: pointer;
  box-shadow: 0 8px 32px rgba(52, 152, 219, 0.4);
  animation: fadeInUp 1s ease-out 0.4s both;
}

.confirm-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
  box-shadow: 0 12px 48px rgba(52, 152, 219, 0.5);
  transform: translateY(-3px);
}

.confirm-button:active:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 24px rgba(52, 152, 219, 0.4);
}

.confirm-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* MESSAGE */
.message {
  position: fixed;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 20;
  padding: 14px 28px;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: 500;
  text-align: center;
  animation: slideUp 0.3s ease-out;
  max-width: 450px;
  width: calc(100% - 4rem);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.message-success {
  background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
  color: white;
}

.message-error {
  background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
  color: white;
}

/* ANIMATIONS */
@keyframes fadeInDown {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes logoAnimation {
  0%, 100% {
    transform: translateY(0) scale(1);
  }
  50% {
    transform: translateY(-15px) scale(1.02);
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateX(-50%) translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }
}

/* SECURITY MODAL */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1.5rem;
}

.security-modal {
  background: #fff;
  border-radius: 16px;
  padding: 2rem;
  width: 100%;
  max-width: 450px;
  color: #333;
  box-shadow: 0 20px 60px rgba(0,0,0,0.5);
  animation: modalIn 0.3s ease-out;
}

@keyframes modalIn {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.security-modal h3 {
  margin-top: 0;
  color: #e74c3c;
  font-size: 1.3rem;
  margin-bottom: 1rem;
}

.security-modal p {
  font-size: 0.95rem;
  line-height: 1.5;
  color: #555;
}

.steps {
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 10px;
  margin: 1.5rem 0;
  border-left: 4px solid #e74c3c;
}

.steps ol {
  padding-left: 1.2rem;
  margin-top: 0.5rem;
}

.steps li {
  margin-bottom: 0.5rem;
}

.modal-actions {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
}

.fix-btn {
  background: #e74c3c;
  color: white;
  text-decoration: none;
  padding: 0.8rem;
  border-radius: 10px;
  text-align: center;
  font-weight: 600;
  transition: all 0.2s;
}

.fix-btn:hover {
  background: #c0392b;
  transform: translateY(-2px);
}

.close-modal-btn {
  background: transparent;
  border: 1px solid #ddd;
  color: #777;
  padding: 0.6rem;
  border-radius: 8px;
  cursor: pointer;
}

.close-modal-btn:hover {
  background: #f5f5f5;
}

/* RESPONSIVE (MOBILE) */
@media (max-width: 900px) {
  .split-layout {
    flex-direction: column;
  }

  .left-side {
    flex: none;
    height: 40vh;
  }

  .logo-image {
    max-width: 60%;
  }

  .right-side {
    flex: none;
    height: 60vh;
  }

  .slogan {
    font-size: 1.8rem;
  }

  .code-input,
  .confirm-button {
    max-width: 90%;
    padding: 16px 20px;
    font-size: 1rem;
  }

  .message {
    bottom: 1rem;
    width: calc(100% - 2rem);
    font-size: 0.95rem;
    padding: 12px 20px;
  }
}

</style>


<script setup>
import { ref } from 'vue'
import logoImage from '../assets/logo.svg'
import backgroundImage from '../assets/bg.jpg'

const emit = defineEmits(['login-success'])

import { getApiUrl, API_ENDPOINTS } from '../config/api.js'

const code = ref('')
const message = ref('')
const isLoading = ref(false)

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
    message.value = 'Connection error. Please try again.'
    isLoading.value = false
    
    setTimeout(() => {
      message.value = ''
    }, 3000)
  }
}
</script>

<template>
  <div class="container" :style="{ backgroundImage: `url(${backgroundImage})` }">
    <div class="logo-container">
      <img :src="logoImage" alt="Arpege Logo" class="logo-image" />
    </div>
    <div class="content">
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
    <div v-if="message" class="message" :class="{ 'message-success': message.includes('successfully'), 'message-error': message.includes('Please') }">
      {{ message }}
    </div>
  </div>
</template>

<style scoped>
.container {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  min-height: 100vh;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  margin: 0;
  padding: 0;
  overflow: hidden;
}

.logo-container {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1;
  padding: 1.5rem 1rem;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  width: 100%;
  pointer-events: none;
}

.logo-image {
  max-width: 600px;
  max-height: 400px;
  width: auto;
  height: auto;
  object-fit: contain;
  display: block;
  animation: logoAnimation 3s ease-in-out infinite;
  pointer-events: none;
}

.content {
  position: relative;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  gap: 2.5rem;
  padding: 2rem;
}




.code-input {
  padding: 16px 24px;
  font-size: 1.1rem;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 12px;
  width: 350px;
  max-width: 90%;
  outline: none;
  transition: all 0.3s ease;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
              0 4px 16px rgba(0, 0, 0, 0.05);
  color: #333;
  animation: fadeInUp 1s ease-out 0.2s both;
  position: relative;
  z-index: 11;
}

.code-input::placeholder {
  color: #999;
}

.code-input:focus {
  border-color: rgba(255, 255, 255, 0.8);
  background: rgba(255, 255, 255, 1);
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15),
              0 6px 24px rgba(0, 212, 255, 0.2),
              inset 0 0 0 2px rgba(255, 255, 255, 0.5);
  transform: translateY(-2px);
}

.code-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.confirm-button {
  padding: 16px 48px;
  font-size: 1.1rem;
  font-weight: 600;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 12px;
  width: 350px;
  max-width: 90%;
  outline: none;
  transition: all 0.3s ease;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
              0 4px 16px rgba(0, 0, 0, 0.05);
  color: #333;
  cursor: pointer;
  animation: fadeInUp 1s ease-out 0.4s both;
}

.confirm-button:hover {
  background: rgba(255, 255, 255, 1);
  border-color: rgba(255, 255, 255, 0.8);
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15),
              0 6px 24px rgba(0, 212, 255, 0.2);
  transform: translateY(-2px);
}

.confirm-button:active {
  transform: translateY(0);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.confirm-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.confirm-button:disabled:hover {
  transform: none;
}

.message {
  position: fixed;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 20;
  padding: 12px 24px;
  border-radius: 8px;
  font-size: 0.95rem;
  text-align: center;
  animation: slideUp 0.3s ease-out;
  max-width: 350px;
  width: calc(100% - 4rem);
}

.message-success {
  background: rgba(76, 175, 80, 0.9);
  color: white;
  box-shadow: 0 4px 16px rgba(76, 175, 80, 0.3);
}

.message-error {
  background: rgba(244, 67, 54, 0.9);
  color: white;
  box-shadow: 0 4px 16px rgba(244, 67, 54, 0.3);
}

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

@keyframes logoAnimation {
  0%, 100% {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
  50% {
    transform: translateY(-10px) scale(1.05);
    opacity: 0.95;
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

@media (max-width: 768px) {
  .logo-container {
    padding: 1rem;
  }
  
  .logo-image {
    max-width: 400px;
    max-height: 250px;
  }
  
  .code-input {
    width: 280px;
    font-size: 1rem;
    padding: 14px 20px;
  }
  
  .confirm-button {
    width: 280px;
    font-size: 1rem;
    padding: 14px 20px;
  }
  
  .message {
    bottom: 1rem;
    width: calc(100% - 2rem);
    max-width: 90%;
  }
}
</style>


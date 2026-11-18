import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import * as authApi from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token') || '')
  const guestConfig = ref(null)
  const isLoggedIn = computed(() => !!token.value)

  window.addEventListener('storage', () => {
    token.value = localStorage.getItem('auth_token') || ''
  })
  window.addEventListener('flavor-auth-cleared', () => {
    token.value = ''
  })

  function saveAuthData(data) {
    token.value = data.auth_data
    localStorage.setItem('auth_token', data.auth_data)
  }

  async function login(email, password) {
    const data = await authApi.login({ email, password })
    saveAuthData(data)
    return data
  }

  async function register(form) {
    const data = await authApi.register(form)
    saveAuthData(data)
    return data
  }

  async function loginWithToken(verify) {
    const data = await authApi.token2Login(verify)
    saveAuthData(data)
    return data
  }

  async function forget(form) {
    return await authApi.forget(form)
  }

  async function sendVerifyCode(email) {
    return await authApi.sendEmailVerify({ email })
  }

  async function fetchGuestConfig() {
    if (guestConfig.value) return guestConfig.value
    guestConfig.value = await authApi.getGuestConfig()
    return guestConfig.value
  }

  async function recordInvitePv(inviteCode) {
    if (!inviteCode) return
    return await authApi.recordInvitePv(inviteCode)
  }

  function logout() {
    token.value = ''
    localStorage.removeItem('auth_token')
  }

  return {
    token, guestConfig, isLoggedIn,
    login, register, loginWithToken, forget, sendVerifyCode,
    fetchGuestConfig, recordInvitePv, logout
  }
})

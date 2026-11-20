import axios from 'axios'
import { getCurrentLocale } from '@/utils/locale'

function clearAuthAndRedirect() {
  localStorage.removeItem('auth_token')
  window.dispatchEvent(new Event('flavor-auth-cleared'))
  if (window.location.hash !== '#/login') {
    window.location.hash = '#/login'
  }
}

const request = axios.create({
  baseURL: '/api/v1',
  timeout: 30000,
  headers: { 'Content-Type': 'application/json' }
})

request.interceptors.request.use(config => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = token
  }
  config.headers['content-language'] = getCurrentLocale()
  return config
})

request.interceptors.response.use(
  res => {
    const data = res.data
    if (data.status === 'error' || data.status === 'fail') {
      return Promise.reject(new Error(data.message || '请求失败'))
    }
    return data.data !== undefined ? data.data : data
  },
  err => {
    if (err.response) {
      const status = err.response.status
      if (status === 401 || status === 403) {
        if (!err.config?.ignoreAuthError) {
          clearAuthAndRedirect()
        }
      }
      const errors = err.response.data?.errors
      const firstError = errors && typeof errors === 'object'
        ? errors[Object.keys(errors)[0]]?.[0]
        : ''
      const msg = err.response.data?.message || firstError || '请求失败'
      return Promise.reject(new Error(msg))
    }
    if (err.code === 'ECONNABORTED') {
      return Promise.reject(new Error('请求超时，请检查服务器负载或反向代理超时配置'))
    }
    return Promise.reject(new Error('网络连接失败，请检查 API 域名、HTTPS 或反向代理配置'))
  }
)

export default request

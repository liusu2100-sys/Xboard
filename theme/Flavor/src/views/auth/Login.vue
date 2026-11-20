<template>
  <div class="auth-page">
    <div class="auth-bg" aria-hidden="true"></div>

    <div class="auth-card anim-fade-up">
      <div class="auth-card__header">
        <div class="auth-card__logo">
          <Icon name="zap" :size="28" />
        </div>
        <h1 class="auth-card__title">{{ siteTitle }}</h1>
        <p class="auth-card__desc">登录您的账号</p>
      </div>

      <form @submit.prevent="handleLogin" class="auth-form">
        <div class="form-group">
          <label class="form-label">邮箱地址</label>
          <div class="form-input-wrap">
            <Icon name="mail" :size="16" class="form-input-icon" />
            <input v-model="form.email" type="email" placeholder="请输入邮箱" required autocomplete="email" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">密码</label>
          <div class="form-input-wrap">
            <Icon name="lock" :size="16" class="form-input-icon" />
            <input v-model="form.password" :type="showPwd ? 'text' : 'password'" placeholder="请输入密码" required autocomplete="current-password" />
            <button type="button" class="form-input-toggle" @click="showPwd = !showPwd">
              <Icon :name="showPwd ? 'eye-off' : 'eye'" :size="16" />
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn--primary btn--lg w-full" :disabled="loading">
          <span v-if="loading" class="anim-spin" style="display:inline-block">⟳</span>
          <span v-else>登 录</span>
        </button>

        <div class="auth-links">
          <router-link to="/forget" class="auth-link">忘记密码?</router-link>
          <router-link to="/register" class="auth-link">注册新账号</router-link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const app = useAppStore()

const siteTitle = computed(() => window.settings?.title || 'Xboard')

const form = ref({ email: '', password: '' })
const showPwd = ref(false)
const loading = ref(false)

function normalizeRedirect(redirect) {
  if (!redirect || redirect === 'dashboard') return '/'
  return String(redirect).startsWith('/') ? redirect : `/${redirect}`
}

async function handleLogin() {
  if (loading.value) return
  loading.value = true
  try {
    await auth.login(form.value.email, form.value.password)
    app.showToast('登录成功', 'success')
    const redirect = normalizeRedirect(route.query.redirect)
    router.push(redirect)
  } catch (e) {
    app.showToast(e.message || '登录失败', 'error')
  } finally {
    loading.value = false
  }
}

async function handleTokenLogin() {
  if (!route.query.verify || loading.value) return
  loading.value = true
  try {
    await auth.loginWithToken(route.query.verify)
    app.showToast('登录成功', 'success')
    router.replace(normalizeRedirect(route.query.redirect))
  } catch (e) {
    app.showToast(e.message || '登录链接已失效', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  handleTokenLogin()
})
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  padding: 24px;
  background: var(--gradient-hero);
}
.auth-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    linear-gradient(115deg, rgba(255,255,255,0.16), transparent 32%),
    linear-gradient(180deg, rgba(6, 182, 212, 0.16), transparent 48%),
    repeating-linear-gradient(90deg, rgba(255,255,255,0.07) 0 1px, transparent 1px 110px),
    repeating-linear-gradient(0deg, rgba(255,255,255,0.05) 0 1px, transparent 1px 110px);
}
.auth-bg::after {
  content: "";
  position: absolute;
  inset: auto 0 0;
  height: 38%;
  background: linear-gradient(180deg, transparent, rgba(7, 18, 31, 0.32));
}
.auth-card {
  width: 100%;
  max-width: 420px;
  background: rgba(255, 255, 255, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.46);
  border-radius: var(--r-xl);
  padding: 36px;
  position: relative;
  z-index: 1;
  box-shadow: 0 24px 70px rgba(6, 78, 88, 0.24);
  backdrop-filter: blur(20px);
}
[data-theme="dark"] .auth-card {
  background: rgba(13, 27, 42, 0.88);
  border-color: rgba(148, 163, 184, 0.2);
}
.auth-card__header {
  text-align: center;
  margin-bottom: 28px;
}
.auth-card__logo {
  width: 56px;
  height: 56px;
  margin: 0 auto 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--gradient-accent);
  border-radius: var(--r-md);
  color: #fff;
  box-shadow: 0 16px 38px rgba(59, 130, 246, 0.32);
}
.auth-card__title {
  font-size: var(--fs-xl);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
  margin-bottom: 4px;
  letter-spacing: 0;
}
.auth-card__desc {
  font-size: var(--fs-sm);
  color: var(--c-text-3);
}
.auth-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-label {
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--c-text-2);
}
.form-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.form-input-icon {
  position: absolute;
  left: 14px;
  color: var(--c-text-3);
  pointer-events: none;
  z-index: 1;
}
.form-input-wrap input {
  height: 44px;
  padding-left: 40px;
  background: color-mix(in srgb, var(--c-bg-input) 88%, transparent);
}
.form-input-toggle {
  position: absolute;
  right: 10px;
  padding: 4px;
  color: var(--c-text-3);
  transition: color var(--t-fast);
}
.form-input-toggle:hover { color: var(--c-text-1); }
.form-row { display: flex; gap: 8px; }
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: var(--r-sm);
  font-weight: var(--fw-semibold);
  transition: transform var(--t-fast), box-shadow var(--t-fast), border-color var(--t-fast), color var(--t-fast), background var(--t-fast);
  cursor: pointer;
}
.btn--lg {
  height: 46px;
  font-size: var(--fs-md);
}
.btn--primary {
  background: var(--gradient-primary);
  color: #fff;
  box-shadow: var(--shadow-glow);
}
.btn--primary:hover:not(:disabled) {
  box-shadow: var(--shadow-float);
  transform: translateY(-1px);
}
.btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn--outline {
  border: 1px solid var(--c-border);
  color: var(--c-text-2);
  padding: 0 16px;
  white-space: nowrap;
  height: 44px;
  border-radius: var(--r-sm);
  font-size: var(--fs-sm);
  background: var(--c-surface-glass);
}
.btn--outline:hover:not(:disabled) {
  border-color: var(--c-primary);
  color: var(--c-primary);
  background: var(--c-primary-light);
}
.btn--outline:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn--send { min-width: 110px; }
.auth-links {
  display: flex;
  justify-content: center;
  gap: 16px;
  font-size: var(--fs-sm);
}
.auth-link {
  color: var(--c-text-2);
  transition: color var(--t-fast);
}
.auth-link:hover { color: var(--c-primary); }
@media (max-width: 480px) {
  .auth-page { padding: 16px; }
  .auth-card { padding: 28px 22px; }
  .form-row { flex-direction: column; }
  .btn--send { width: 100%; }
}
</style>

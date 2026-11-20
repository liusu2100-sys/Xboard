<template>
  <div class="auth-page">
    <div class="auth-bg" aria-hidden="true"></div>

    <div class="auth-card anim-fade-up">
      <div class="auth-card__header">
        <div class="auth-card__logo"><Icon name="zap" :size="28" /></div>
        <h1 class="auth-card__title">创建账号</h1>
        <p class="auth-card__desc">注册以开始使用</p>
      </div>

      <form @submit.prevent="handleRegister" class="auth-form">
        <div class="form-group">
          <label class="form-label">邮箱地址</label>
          <div class="form-input-wrap">
            <Icon name="mail" :size="16" class="form-input-icon" />
            <input v-model="form.email" type="email" placeholder="请输入邮箱" required />
          </div>
        </div>

        <div class="form-group" v-if="needVerify">
          <label class="form-label">邮箱验证码</label>
          <div class="form-row">
            <div class="form-input-wrap" style="flex:1">
              <input v-model="form.email_code" placeholder="请输入验证码" />
            </div>
            <button type="button" class="btn btn--outline btn--send" @click="sendCode" :disabled="countdown > 0">
              {{ countdown > 0 ? countdown + 's' : '发送验证码' }}
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">密码</label>
          <div class="form-input-wrap">
            <Icon name="lock" :size="16" class="form-input-icon" />
            <input v-model="form.password" type="password" placeholder="请输入密码 (至少8位)" required minlength="8" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">邀请码 <span class="text-3">({{ inviteRequired ? '必填' : '可选' }})</span></label>
          <div class="form-input-wrap">
            <Icon name="gift" :size="16" class="form-input-icon" />
            <input v-model="form.invite_code" placeholder="请输入邀请码" :required="inviteRequired" />
          </div>
        </div>

        <button type="submit" class="btn btn--primary btn--lg w-full" :disabled="loading">
          <span v-if="loading" class="anim-spin" style="display:inline-block">⟳</span>
          <span v-else>注 册</span>
        </button>

        <div class="auth-links">
          <router-link to="/login" class="auth-link">已有账号? 立即登录</router-link>
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

const form = ref({
  email: '',
  password: '',
  invite_code: '',
  email_code: ''
})

const loading = ref(false)
const countdown = ref(0)
const needVerify = computed(() => Number(auth.guestConfig?.is_email_verify || 0) === 1)
const inviteRequired = computed(() => Number(auth.guestConfig?.is_invite_force || 0) === 1)

onMounted(async () => {
  if (route.query.code) {
    form.value.invite_code = route.query.code
    auth.recordInvitePv(route.query.code).catch(() => {})
  }
  await auth.fetchGuestConfig().catch(() => {})
})

async function sendCode() {
  if (!form.value.email) {
    app.showToast('请先输入邮箱', 'warning')
    return
  }
  try {
    await auth.sendVerifyCode(form.value.email)
    app.showToast('验证码已发送', 'success')
    countdown.value = 60
    const timer = setInterval(() => {
      countdown.value--
      if (countdown.value <= 0) clearInterval(timer)
    }, 1000)
  } catch (e) {
    app.showToast(e.message || '发送失败', 'error')
  }
}

async function handleRegister() {
  if (loading.value) return
  loading.value = true
  try {
    await auth.register(form.value)
    app.showToast('注册成功', 'success')
    router.push('/')
  } catch (e) {
    app.showToast(e.message || '注册失败', 'error')
  } finally {
    loading.value = false
  }
}
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
  background: var(--c-bg-shell, var(--c-bg-base));
}
.auth-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    linear-gradient(115deg, rgba(20, 184, 166, 0.05), transparent 32%),
    linear-gradient(180deg, rgba(20, 184, 166, 0.03), transparent 48%),
    repeating-linear-gradient(90deg, rgba(20, 184, 166, 0.02) 0 1px, transparent 1px 110px),
    repeating-linear-gradient(0deg, rgba(20, 184, 166, 0.02) 0 1px, transparent 1px 110px);
}
.auth-card {
  width: 100%;
  max-width: 420px;
  background: var(--c-surface-glass);
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  padding: 36px;
  position: relative;
  z-index: 1;
  box-shadow: var(--shadow-float);
  backdrop-filter: blur(20px);
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

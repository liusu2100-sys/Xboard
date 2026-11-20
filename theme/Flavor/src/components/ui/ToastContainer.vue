<template>
  <Teleport to="body">
    <div class="toast-container">
      <div v-for="t in app.toasts" :key="t.id" class="toast anim-slide-right" :class="'toast--' + t.type">
        <span class="toast__icon">{{ icons[t.type] }}</span>
        <span class="toast__msg">{{ t.message }}</span>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { useAppStore } from '@/stores/app'
const app = useAppStore()
const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' }
</script>

<style scoped>
.toast-container {
  position: fixed; top: 20px; right: 20px; z-index: 9999;
  display: flex; flex-direction: column; gap: 8px;
}
.toast {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 20px;
  border-radius: var(--r-md);
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-lg);
  font-size: var(--fs-sm);
  color: var(--c-text-1);
  min-width: 240px;
  backdrop-filter: blur(12px);
}
.toast--success { border-color: var(--c-success); }
.toast--success .toast__icon { color: var(--c-success); }
.toast--error { border-color: var(--c-danger); }
.toast--error .toast__icon { color: var(--c-danger); }
.toast--warning { border-color: var(--c-warning); }
.toast--warning .toast__icon { color: var(--c-warning); }
.toast--info { border-color: var(--c-info); }
.toast--info .toast__icon { color: var(--c-info); }
.toast__icon { font-size: 16px; font-weight: 700; flex-shrink: 0; }
</style>

<template>
  <div v-if="show" class="sub-modal-overlay" @click.self="close">
    <div class="sub-modal">
      <div class="sub-modal__header">
        <h3 class="sub-modal__title">导入订阅</h3>
        <button class="sub-modal__close" @click="close">
          <Icon name="x" :size="20" />
        </button>
      </div>

      <div class="sub-modal__body">
        <!-- Top Actions -->
        <div class="sub-modal__actions">
          <button class="action-btn" @click="copySubscribe">
            <div class="action-btn__icon" style="background: #e0f2fe; color: #0284c7;">
              <Icon name="copy" :size="20" />
            </div>
            <div class="action-btn__text">
              <div class="action-btn__title">复制订阅地址</div>
              <div class="action-btn__desc">复制订阅链接到剪贴板</div>
            </div>
          </button>
          
          <button class="action-btn" @click="$emit('open-qr')">
            <div class="action-btn__icon" style="background: #dcfce7; color: #16a34a;">
              <Icon name="qr" :size="20" />
            </div>
            <div class="action-btn__text">
              <div class="action-btn__title">扫描二维码订阅</div>
              <div class="action-btn__desc">使用客户端扫描二维码导入</div>
            </div>
          </button>
        </div>

        <!-- OS Tabs -->
        <div class="sub-modal__tabs-wrapper">
          <div class="sub-modal__tabs">
            <button v-for="tab in osTabs" :key="tab.id"
              class="os-tab" :class="{ 'os-tab--active': activeOs === tab.id }"
              @click="activeOs = tab.id">
              <span class="os-tab__icon" v-html="tab.icon"></span>
              <span>{{ tab.name }}</span>
            </button>
          </div>
        </div>

        <!-- Clients Grid -->
        <div class="sub-modal__clients-section">
          <h4 class="os-title">{{ activeOsName }}</h4>
          <div class="clients-grid">
            <button v-for="app in activeClients" :key="app.name" class="client-card" @click="importConfig(app.link)">
              <div class="client-icon" v-html="app.svg"></div>
              <div class="client-name">{{ app.name }}</div>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import { copyText } from '@/utils/clipboard'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  show: { type: Boolean, default: false },
  subscribeUrl: { type: String, default: '' },
  subscribeName: { type: String, default: 'Rocket' }
})

const emit = defineEmits(['update:show', 'open-qr'])

const appStore = useAppStore()

function close() {
  emit('update:show', false)
}

async function copySubscribe() {
  if (!props.subscribeUrl) return appStore.showToast('暂无订阅链接', 'warning')
  const ok = await copyText(props.subscribeUrl)
  if (ok) {
    appStore.showToast('已复制到剪贴板', 'success')
  } else {
    appStore.showToast('复制失败，请手动复制', 'error')
  }
}

function importConfig(link) {
  if (!link) return
  copyText(link)
  appStore.showToast('已生成一键导入链接，若未自动唤醒请手动复制到浏览器打开', 'success')
  window.location.href = link
}

// URL Helpers
function appendSubscribeFlag(url, flag) {
  try {
    const next = new URL(url, window.location.origin)
    next.searchParams.set('flag', flag)
    return next.toString()
  } catch (error) {
    const joiner = url.includes('?') ? '&' : '?'
    return `${url}${joiner}flag=${encodeURIComponent(flag)}`
  }
}

function base64UrlEncode(value) {
  const bytes = new TextEncoder().encode(value)
  let binary = ''
  bytes.forEach(byte => binary += String.fromCharCode(byte))
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

function getAppLinks() {
  const url = props.subscribeUrl
  const name = props.subscribeName
  if (!url) return {}

  const encodedName = encodeURIComponent(name)
  const withFlag = (flag) => appendSubscribeFlag(url, flag)
  const encodedUrl = (flag) => encodeURIComponent(withFlag(flag))
  const rawUrl = (flag) => withFlag(flag)
  const hiddifyUrl = encodeURIComponent(withFlag('hiddify'))
  const base64Url = (flag) => base64UrlEncode(withFlag(flag))
  const quantumultRemote = encodeURIComponent(JSON.stringify({
    server_remote: [`${rawUrl('quantumult-x')}, tag=${name}`]
  }))

  return {
    'Shadowrocket': `shadowrocket://add/sub://${base64Url('shadowrocket')}?remark=${encodedName}`,
    'Surge': `surge:///install-config?url=${encodedUrl('surge')}&name=${encodedName}`,
    'Stash': `stash://install-config?url=${encodedUrl('stash')}&name=${encodedName}`,
    'Quantumult X': `quantumult-x:///update-configuration?remote-resource=${quantumultRemote}`,
    'Hiddify': `hiddify://import/${hiddifyUrl}#${encodedName}`,
    'Singbox': `sing-box://import-remote-profile?url=${encodedUrl('sing-box')}#${encodedName}`,
    'Loon': `loon://import?sub=${encodedUrl('loon')}`,
    'FlClash': `flclash://install-config?url=${encodedUrl('flclash')}&name=${encodedName}`,
    'V2rayNG': `v2rayng://install-sub?url=${encodedUrl('v2rayng')}&name=${encodedName}`,
    'Clash': `clash://install-config?url=${encodedUrl('clash')}&name=${encodedName}`,
    'Surfboard': `surfboard:///install-config?url=${encodedUrl('surfboard')}&name=${encodedName}`,
    'Clash Meta': `clash://install-config?url=${encodedUrl('meta')}&name=${encodedName}`,
    'Nekobox': `clash://install-config?url=${encodedUrl('nekobox')}&name=${encodedName}`,
    'ClashVerge': `clash://install-config?url=${encodedUrl('clash')}&name=${encodedName}`,
    'Nekoray': `clash://install-config?url=${encodedUrl('nekobox')}&name=${encodedName}`,
    'ClashX': `clash://install-config?url=${encodedUrl('clash')}&name=${encodedName}`,
    'ClashX Meta': `clash://install-config?url=${encodedUrl('meta')}&name=${encodedName}`
  }
}

// Icons (Simple SVG representations)
const SVGS = {
  Shadowrocket: `<svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>`,
  Surge: `<svg viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>`,
  Stash: `<svg viewBox="0 0 24 24" fill="#0ea5e9"><rect width="24" height="24" rx="5" fill="#e0f2fe"/><path d="M12 6a6 6 0 1 0 0 12 6 6 0 0 0 0-12zm-3 8h6v2H9v-2zm0-4h6v2H9V10z" fill="#0ea5e9"/></svg>`,
  'Quantumult X': `<svg viewBox="0 0 24 24" fill="#1e3a8a"><rect width="24" height="24" rx="5" fill="#1e3a8a"/><path d="M7 7l10 10m0-10L7 17" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>`,
  Hiddify: `<svg viewBox="0 0 24 24" fill="#f59e0b"><rect x="5" y="10" width="3" height="10" rx="1.5"/><rect x="10.5" y="4" width="3" height="16" rx="1.5"/><rect x="16" y="8" width="3" height="12" rx="1.5"/></svg>`,
  Singbox: `<svg viewBox="0 0 24 24" fill="#334155"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>`,
  Loon: `<svg viewBox="0 0 24 24" fill="none" stroke="#c084fc" stroke-width="2"><path d="M12 22C17.52 22 22 17.52 22 12C22 6.48 12 2 12 2S2 6.48 2 12C2 17.52 6.48 22 12 22Z" fill="#e9d5ff"/></svg>`,
  FlClash: `<svg viewBox="0 0 24 24" fill="#3b82f6"><path d="M5 8v12h14V8l-7-4-7 4zm3 3h2v2H8v-2zm6 0h2v2h-2v-2z" /></svg>`,
  V2rayNG: `<svg viewBox="0 0 24 24" fill="#000"><path d="M12 21L3 5h18L12 21z"/></svg>`,
  Clash: `<svg viewBox="0 0 24 24" fill="#1e293b"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5c-.83 0-1.5-.67-1.5-1.5S9.17 11.5 10 11.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM12 8.5c-.83 0-1.5-.67-1.5-1.5S11.17 5.5 12 5.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>`,
  Surfboard: `<svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>`,
  'Clash Meta': `<svg viewBox="0 0 24 24" fill="#0284c7"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>`,
  Nekobox: `<svg viewBox="0 0 24 24" fill="#475569"><path d="M12,8L10,4H8L9,9C6.24,9.54 4,12 4,15v4h16v-4c0-3-2.24-5.46-5-6l1-5h-2L12,8z"/></svg>`,
  ClashVerge: `<svg viewBox="0 0 24 24" fill="#ec4899"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5c-.83 0-1.5-.67-1.5-1.5S9.17 11.5 10 11.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM12 8.5c-.83 0-1.5-.67-1.5-1.5S11.17 5.5 12 5.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>`,
  Nekoray: `<svg viewBox="0 0 24 24" fill="#3b82f6"><path d="M12,8L10,4H8L9,9C6.24,9.54 4,12 4,15v4h16v-4c0-3-2.24-5.46-5-6l1-5h-2L12,8z"/></svg>`,
  ClashX: `<svg viewBox="0 0 24 24" fill="#0ea5e9"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5c-.83 0-1.5-.67-1.5-1.5S9.17 11.5 10 11.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM12 8.5c-.83 0-1.5-.67-1.5-1.5S11.17 5.5 12 5.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>`,
  'ClashX Meta': `<svg viewBox="0 0 24 24" fill="#0284c7"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5c-.83 0-1.5-.67-1.5-1.5S9.17 11.5 10 11.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM12 8.5c-.83 0-1.5-.67-1.5-1.5S11.17 5.5 12 5.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>`,
}

const OS_APPS = {
  ios: ['Shadowrocket', 'Surge', 'Stash', 'Quantumult X', 'Hiddify', 'Singbox', 'Loon'],
  android: ['FlClash', 'V2rayNG', 'Clash', 'Surfboard', 'Clash Meta', 'Nekobox', 'Singbox', 'Hiddify'],
  windows: ['FlClash', 'ClashVerge', 'Clash', 'Nekoray', 'Singbox', 'Hiddify'],
  macos: ['FlClash', 'ClashVerge', 'ClashX', 'ClashX Meta', 'Surge', 'Stash', 'Quantumult X', 'Singbox', 'Hiddify']
}

const osTabs = [
  { id: 'ios', name: 'iOS', icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.94c1.5 0 2.75 1.06 4 1.06 3 0 6-8 6-12.22A4.91 4.91 0 0 0 17 5c-2.22 0-4 1.44-5 2-1-.56-2.78-2-5-2a4.9 4.9 0 0 0-5 4.78C2 14 5 22 8 22c1.25 0 2.5-1.06 4-1.06Z"/><path d="M10 2c1 .5 2 2 2 5"/></svg>` },
  { id: 'android', name: 'Android', icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="2" ry="2"/><path d="M4 12H2v4h2M20 12h2v4h-2M8 4l-1-2M16 4l1-2M8 8v16M16 8v16"/><path d="M4 8c0-4.42 3.58-8 8-8s8 3.58 8 8"/></svg>` },
  { id: 'windows', name: 'Windows', icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>` },
  { id: 'macos', name: 'MacOS', icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><path d="M12 20v-4M8 20h8"/></svg>` }
]

const activeOs = ref('ios')

const activeOsName = computed(() => {
  return osTabs.find(t => t.id === activeOs.value)?.name || 'iOS'
})

const activeClients = computed(() => {
  const links = getAppLinks()
  const apps = OS_APPS[activeOs.value] || []
  return apps.map(name => ({
    name,
    svg: SVGS[name] || SVGS['Clash'], // Fallback
    link: links[name] || ''
  }))
})

</script>

<style scoped>
.sub-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(240, 240, 243, 0.85);
  backdrop-filter: blur(12px);
}

.sub-modal {
  width: min(860px, 100%);
  background: #f9fafb;
  border-radius: 20px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #f3f4f6;
}

.sub-modal__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 32px 16px;
}

.sub-modal__title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.sub-modal__close {
  color: #6b7280;
  cursor: pointer;
  background: transparent;
  border: none;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.2s;
}
.sub-modal__close:hover {
  background: #e5e7eb;
  color: #111827;
}

.sub-modal__body {
  padding: 0 32px 32px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Action Buttons */
.sub-modal__actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 16px;
  width: 100%;
  padding: 16px 20px;
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
}
.action-btn:hover {
  background: #fafafa;
  box-shadow: 0 4px 12px rgba(0,0,0,0.03);
  transform: translateY(-1px);
}

.action-btn__icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.action-btn__text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.action-btn__title {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}

.action-btn__desc {
  font-size: 0.875rem;
  color: #6b7280;
}

/* OS Tabs */
.sub-modal__tabs-wrapper {
  display: flex;
  justify-content: center;
  margin-top: 8px;
}

.sub-modal__tabs {
  display: inline-flex;
  background: #fff;
  border-radius: 40px;
  padding: 6px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  gap: 4px;
}

.os-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 20px;
  border: 1px solid transparent;
  border-radius: 30px;
  background: transparent;
  cursor: pointer;
  color: #6b7280;
  font-size: 0.95rem;
  font-weight: 500;
  transition: all 0.2s;
}

.os-tab:hover {
  color: #374151;
}

.os-tab--active {
  background: #fff;
  color: #b45309;
  border-color: #fcd34d;
  box-shadow: 0 4px 12px rgba(252, 211, 77, 0.15);
  font-weight: 600;
}

.os-tab__icon {
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.os-tab__icon :deep(svg) {
  width: 100%;
  height: 100%;
}

/* Clients Grid */
.sub-modal__clients-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.os-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #374151;
  margin: 0 4px;
}

.clients-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 12px;
}

.client-card {
  background: #fff;
  border: 1px solid transparent;
  border-radius: 16px;
  padding: 20px 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}

.client-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(0,0,0,0.06);
  border-color: #f3f4f6;
}

.client-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.client-icon :deep(svg) {
  width: 100%;
  height: 100%;
}

.client-name {
  font-size: 0.85rem;
  font-weight: 500;
  color: #4b5563;
  text-align: center;
  line-height: 1.2;
}

/* Scrollbar adjustment for smaller screens */
@media (max-width: 640px) {
  .sub-modal {
    max-height: 90vh;
    border-radius: 16px;
  }
  .sub-modal__body {
    overflow-y: auto;
    padding: 0 20px 20px;
  }
  .sub-modal__header {
    padding: 20px 20px 16px;
  }
  .clients-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>

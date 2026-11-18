<template>
  <div class="dashboard">
    <div class="dashboard-hero anim-fade-up">
      <div class="dashboard-hero__content">
        <span class="dashboard-hero__eyebrow">Network Console</span>
        <h2 class="dashboard-hero__title">控制台</h2>
        <p class="dashboard-hero__meta">{{ planName }} · {{ statusText }} · {{ expireDate }}</p>
        <div class="dashboard-hero__chips">
          <span>Clash Meta</span>
          <span>Sing-box</span>
          <span>Hiddify</span>
          <span>V2rayNG</span>
        </div>
      </div>
      <router-link to="/shop" class="dashboard-hero__action">
        <Icon name="shop" :size="16" />
        <span>购买套餐</span>
      </router-link>
    </div>

    <!-- Notice Banner -->
    <div class="notice-banner mb-3" v-if="user.notices && user.notices.length > 0">
      <Icon name="bell" :size="18" class="text-primary mr-2" style="flex-shrink:0" />
      <div class="notice-marquee">
        <div class="notice-marquee__inner">
          <span v-for="notice in user.notices" :key="notice.id" class="notice-marquee__item" @click="activeNotice = activeNotice === notice.id ? null : notice.id">
            [{{ formatDate(notice.created_at) }}] {{ notice.title }}
          </span>
        </div>
      </div>
    </div>
    
    <!-- Expanded Notice Modal / Dropdown -->
    <div class="notice-expand card p-4 mb-4" v-if="activeNoticeObj">
       <div class="d-flex justify-between mb-3 border-bottom pb-2">
         <h3 class="font-bold text-lg text-primary">{{ activeNoticeObj.title }}</h3>
         <button class="btn btn--secondary btn--sm" @click="activeNotice = null">收起</button>
       </div>
       <div class="notice-content" v-html="activeNoticeObj.content"></div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="user.loading" class="dash-grid">
      <div v-for="i in 4" :key="i" class="card skeleton" style="height:180px"></div>
    </div>

    <template v-else>
      <!-- Subscription Card -->
      <div class="sub-card anim-fade-up" style="animation-delay:0.05s">
        <div class="sub-card__header">
          <div class="sub-card__plan">
            <span class="sub-card__plan-name">{{ planName }}</span>
            <span class="sub-card__status" :class="statusClass">{{ statusText }}</span>
          </div>
          <div class="sub-card__actions">
            <button class="sub-card__action" @click="copySubscribe" title="复制订阅链接">
              <Icon name="copy" :size="16" />
              <span>复制订阅</span>
            </button>
            <button class="sub-card__action sub-card__action--ghost" @click="openQrModal" title="扫描二维码订阅">
              <Icon name="qr" :size="16" />
              <span>扫码订阅</span>
            </button>
          </div>
        </div>

        <!-- Traffic Ring -->
        <div class="sub-card__body">
          <div class="traffic-ring">
            <svg viewBox="0 0 120 120" class="traffic-ring__svg">
              <circle cx="60" cy="60" r="52" fill="none" stroke="var(--c-border)" stroke-width="8" />
              <circle cx="60" cy="60" r="52" fill="none" stroke="var(--c-primary)" stroke-width="8"
                :stroke-dasharray="circumference" :stroke-dashoffset="dashOffset"
                stroke-linecap="round" transform="rotate(-90 60 60)"
                style="transition: stroke-dashoffset 1s ease" />
            </svg>
            <div class="traffic-ring__inner">
              <span class="traffic-ring__percent">{{ user.trafficPercent }}%</span>
              <span class="traffic-ring__label">已用流量</span>
            </div>
          </div>

          <div class="traffic-details">
            <div class="traffic-item">
              <Icon name="upload" :size="16" class="text-primary" />
              <div>
                <div class="traffic-item__label">上行</div>
                <div class="traffic-item__value">{{ formatBytes(user.subscription?.u || 0) }}</div>
              </div>
            </div>
            <div class="traffic-item">
              <Icon name="download" :size="16" class="text-info" />
              <div>
                <div class="traffic-item__label">下行</div>
                <div class="traffic-item__value">{{ formatBytes(user.subscription?.d || 0) }}</div>
              </div>
            </div>
            <div class="traffic-item">
              <Icon name="zap" :size="16" class="text-warning" />
              <div>
                <div class="traffic-item__label">总流量</div>
                <div class="traffic-item__value">{{ formatBytes(user.trafficTotal) }}</div>
              </div>
            </div>
            <div class="traffic-item">
              <Icon name="shield" :size="16" class="text-success" />
              <div>
                <div class="traffic-item__label">到期时间</div>
                <div class="traffic-item__value">{{ expireDate }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Stats Grid -->
      <div class="dash-grid">
        <!-- Wallet Card -->
        <div class="card anim-fade" style="animation-delay:0.1s">
          <div class="card__header">
            <Icon name="wallet" :size="20" class="text-primary" />
            <span class="card__title">钱包</span>
          </div>
          <div class="stat-value">¥ {{ (user.info?.balance / 100 || 0).toFixed(2) }}</div>
          <div class="stat-label">账户余额</div>
        </div>

        <!-- Commission Card -->
        <div class="card anim-fade" style="animation-delay:0.15s">
          <div class="card__header">
            <Icon name="gift" :size="20" class="text-warning" />
            <span class="card__title">佣金</span>
          </div>
          <div class="stat-value">¥ {{ ((user.info?.commission_balance || 0) / 100).toFixed(2) }}</div>
          <div class="stat-label">待提现佣金</div>
        </div>

        <!-- Quick Actions -->
        <div class="card anim-fade" style="animation-delay:0.2s">
          <div class="card__header">
            <Icon name="zap" :size="20" class="text-success" />
            <span class="card__title">快捷操作</span>
          </div>
          <div class="quick-actions">
            <router-link to="/shop" class="quick-btn">
              <Icon name="shop" :size="18" />
              <span>购买套餐</span>
            </router-link>
            <router-link to="/ticket" class="quick-btn">
              <Icon name="send" :size="18" />
              <span>提交工单</span>
            </router-link>
            <router-link to="/invite" class="quick-btn">
              <Icon name="invite" :size="18" />
              <span>邀请好友</span>
            </router-link>
            <router-link to="/knowledge" class="quick-btn">
              <Icon name="book" :size="18" />
              <span>使用教程</span>
            </router-link>
          </div>
        </div>
      </div>

      <!-- Subscription Links -->
      <div class="card anim-fade" style="animation-delay:0.25s" v-if="user.subscribe">
        <div class="card__header">
          <Icon name="link" :size="20" class="text-primary" />
          <span class="card__title">订阅链接</span>
          <button class="card__btn" @click="resetSubscribe" title="重置订阅链接">
            <Icon name="refresh" :size="14" />
            <span>重置</span>
          </button>
        </div>
        <div class="subscribe-link">
          <code class="subscribe-link__url">{{ subscribeUrl || '—' }}</code>
          <button class="subscribe-link__copy" @click="copySubscribe" title="复制订阅链接">
            <Icon :name="copied ? 'check' : 'copy'" :size="14" />
          </button>
          <button class="subscribe-link__copy" @click="openQrModal" title="扫描二维码订阅">
            <Icon name="qr" :size="14" />
          </button>
        </div>
        <div class="subscribe-apps" v-if="subApps.length">
          <a v-for="app in subApps" :key="app.name" :href="app.link"
            class="sub-app" :title="app.title || app.name" rel="noreferrer">
            {{ app.name }}
          </a>
        </div>
      </div>

      <div v-if="qrModalOpen" class="modal-overlay" @click.self="qrModalOpen = false">
        <div class="qr-modal">
          <div class="qr-modal__header">
            <div>
              <h3>扫描二维码订阅</h3>
              <p>使用 FlClash / Clash Meta 扫码导入当前订阅</p>
            </div>
            <button class="qr-modal__close" @click="qrModalOpen = false" title="关闭">
              <Icon name="x" :size="18" />
            </button>
          </div>
          <div class="qr-modal__body">
            <div class="qr-modal__code">
              <div v-if="qrLoading" class="qr-modal__empty">二维码生成中...</div>
              <img v-else-if="qrImage" :src="qrImage" alt="订阅二维码" class="qr-modal__image">
              <div v-else class="qr-modal__empty">{{ qrError || '二维码生成失败' }}</div>
            </div>
            <code class="qr-modal__url">{{ qrContent || qrSubscribeLink }}</code>
          </div>
          <div class="qr-modal__actions">
            <button class="btn btn--secondary" @click="copyQrLink">
              <Icon :name="qrCopied ? 'check' : 'copy'" :size="16" />
              <span>{{ qrCopied ? '已复制' : '复制扫码链接' }}</span>
            </button>
            <button class="btn btn--primary" @click="qrModalOpen = false">完成</button>
          </div>
        </div>
      </div>

      <!-- Notices removed from here to top -->
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import * as userApi from '@/api/user'
import { copyText } from '@/utils/clipboard'

const user = useUserStore()
const appStore = useAppStore()
const copied = ref(false)
const qrCopied = ref(false)
const qrModalOpen = ref(false)
const qrLoading = ref(false)
const qrImage = ref('')
const qrContent = ref('')
const qrError = ref('')
const activeNotice = ref(null)
const activeNoticeObj = computed(() => {
  if (!activeNotice.value) return null
  return user.notices.find(n => n.id === activeNotice.value)
})

const circumference = 2 * Math.PI * 52
const dashOffset = computed(() => {
  return circumference - (user.trafficPercent / 100) * circumference
})

const subscribeUrl = computed(() => normalizeSubscribeUrl(user.subscribe?.subscribe_url || ''))
const subscribeName = computed(() => window.settings?.title || 'Xboard')
const subApps = computed(() => buildSubscribeApps(subscribeUrl.value, subscribeName.value))
const qrSubscribeLink = computed(() => buildFlClashImportLink(subscribeUrl.value, subscribeName.value))
const planName = computed(() => user.subscribe?.plan?.name || user.info?.plan?.name || '未订阅')
const expireDate = computed(() => {
  if (!user.subscription?.expired_at) return '永久'
  return new Date(user.subscription.expired_at * 1000).toLocaleDateString('zh-CN')
})

const statusClass = computed(() => {
  if (user.isExpired) return 'status--danger'
  if (user.daysRemaining <= 7) return 'status--warning'
  return 'status--active'
})

const statusText = computed(() => {
  if (!user.info?.plan) return '未激活'
  if (user.isExpired) return '已过期'
  if (user.daysRemaining <= 7) return `${user.daysRemaining}天后到期`
  return '使用中'
})

function buildSubscribeApps(url, name) {
  if (!url) return []

  const encodedName = encodeURIComponent(name)
  const withFlag = (flag) => appendSubscribeFlag(url, flag)
  const encodedUrl = (flag) => encodeURIComponent(withFlag(flag))
  const rawUrl = (flag) => withFlag(flag)
  const hiddifyUrl = encodeURIComponent(withFlag('meta'))
  const base64Url = (flag) => base64UrlEncode(withFlag(flag))
  const quantumultRemote = encodeURIComponent(JSON.stringify({
    server_remote: [`${rawUrl('quantumult-x')}, tag=${name}`]
  }))

  return [
    {
      name: 'Clash',
      title: '导入 Clash 订阅配置',
      link: `clash://install-config?url=${encodedUrl('clash')}&name=${encodedName}`
    },
    {
      name: 'Clash Meta',
      title: '导入 Clash Meta / Mihomo 订阅配置',
      link: `clash://install-config?url=${encodedUrl('meta')}&name=${encodedName}`
    },
    {
      name: 'FlClash',
      title: '导入 FlClash 订阅配置',
      link: buildFlClashImportLink(url, name)
    },
    {
      name: 'Stash',
      title: '导入 Stash 订阅配置',
      link: `stash://install-config?url=${encodedUrl('stash')}&name=${encodedName}`
    },
    {
      name: 'Surge',
      title: '导入 Surge 订阅配置',
      link: `surge:///install-config?url=${encodedUrl('surge')}&name=${encodedName}`
    },
    {
      name: 'Surfboard',
      title: '导入 Surfboard 订阅配置',
      link: `surfboard:///install-config?url=${encodedUrl('surfboard')}&name=${encodedName}`
    },
    {
      name: 'Sing-box',
      title: '导入 sing-box 远程配置',
      link: `sing-box://import-remote-profile?url=${encodedUrl('sing-box')}#${encodedName}`
    },
    {
      name: 'Hiddify',
      title: '导入 Hiddify 订阅配置',
      link: `hiddify://import/${hiddifyUrl}#${encodedName}`
    },
    {
      name: 'NekoBox',
      title: '导入 NekoBox / ClashMeta 订阅配置',
      link: `clash://install-config?url=${encodedUrl('nekobox')}&name=${encodedName}`
    },
    {
      name: 'V2rayNG',
      title: '导入 V2rayNG 订阅配置',
      link: `v2rayng://install-sub?url=${encodedUrl('v2rayng')}&name=${encodedName}`
    },
    {
      name: 'Loon',
      title: '导入 Loon 远程订阅配置',
      link: `loon://import?sub=${encodedUrl('loon')}`
    },
    {
      name: 'Shadowrocket',
      title: '导入 Shadowrocket 订阅配置',
      link: `shadowrocket://add/sub://${base64Url('shadowrocket')}?remark=${encodedName}`
    },
    {
      name: 'Quantumult X',
      title: '导入 Quantumult X 订阅配置',
      link: `quantumult-x:///update-configuration?remote-resource=${quantumultRemote}`
    }
  ]
}

function buildFlClashImportLink(url, name) {
  if (!url) return ''
  const encodedName = encodeURIComponent(name)
  const encodedUrl = encodeURIComponent(appendSubscribeFlag(url, 'flclash'))
  return `clash://install-config?url=${encodedUrl}&name=${encodedName}`
}

function normalizeSubscribeUrl(url) {
  if (!url) return ''
  try {
    const parsed = new URL(url, window.location.origin)
    if (['localhost', '127.0.0.1', '0.0.0.0', '::1'].includes(parsed.hostname)) {
      const origin = new URL(window.location.origin)
      parsed.protocol = origin.protocol
      parsed.hostname = origin.hostname
      parsed.port = origin.port
    }
    return parsed.toString()
  } catch (error) {
    return url
  }
}

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

function formatBytes(bytes) {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function formatDate(ts) {
  if (!ts) return ''
  const d = new Date(ts * 1000)
  return d.toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' })
}

async function copySubscribe() {
  const url = subscribeUrl.value
  if (!url) return appStore.showToast('暂无订阅链接', 'warning')
  const ok = await copyText(url)
  if (ok) {
    copied.value = true
    appStore.showToast('已复制到剪贴板', 'success')
    setTimeout(() => copied.value = false, 2000)
  } else {
    appStore.showToast('复制失败，请手动复制', 'error')
  }
}

function openQrModal() {
  if (!subscribeUrl.value) return appStore.showToast('暂无订阅链接', 'warning')
  qrModalOpen.value = true
  fetchSubscribeQrCode()
}

async function copyQrLink() {
  const url = qrContent.value || qrSubscribeLink.value
  if (!url) return appStore.showToast('暂无订阅链接', 'warning')
  const ok = await copyText(url)
  if (ok) {
    qrCopied.value = true
    appStore.showToast('已复制扫码链接', 'success')
    setTimeout(() => qrCopied.value = false, 2000)
  } else {
    appStore.showToast('复制失败，请手动复制', 'error')
  }
}

async function fetchSubscribeQrCode() {
  qrLoading.value = true
  qrError.value = ''
  qrImage.value = ''
  qrContent.value = ''
  try {
    const data = await userApi.getSubscribeQrCode({ target: 'flclash', size: 420 })
    qrImage.value = data.image || ''
    qrContent.value = data.content || data.subscribe_url || appendSubscribeFlag(subscribeUrl.value, 'flclash')
    if (!qrImage.value) throw new Error('二维码生成失败')
  } catch (error) {
    qrError.value = error.message || '二维码生成失败'
    qrContent.value = appendSubscribeFlag(subscribeUrl.value, 'flclash')
    appStore.showToast('二维码生成失败，请复制链接导入', 'error')
  } finally {
    qrLoading.value = false
  }
}

async function resetSubscribe() {
  if (!confirm('重置后旧的订阅链接将失效，确定重置？')) return
  try {
    await userApi.resetSecurity()
    appStore.showToast('重置成功，请重新导入订阅', 'success')
    user.fetchAll()
  } catch (e) {
    appStore.showToast(e.message, 'error')
  }
}

onMounted(() => {
  if (!user.isLoaded) user.fetchAll()
})
</script>

<style scoped>
.dashboard { display: flex; flex-direction: column; gap: 20px; }

.dashboard-hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 28px;
  color: #fff;
  background: var(--gradient-hero);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-float);
  overflow: hidden;
}
.dashboard-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    linear-gradient(115deg, rgba(255,255,255,0.18), transparent 34%),
    repeating-linear-gradient(90deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 96px);
  pointer-events: none;
}
.dashboard-hero__content,
.dashboard-hero__action { position: relative; z-index: 1; }
.dashboard-hero__eyebrow {
  display: inline-flex;
  margin-bottom: 8px;
  color: rgba(255,255,255,0.72);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
}
.dashboard-hero__title {
  margin: 0;
  font-size: clamp(1.75rem, 3vw, 2.45rem);
  line-height: 1.1;
  letter-spacing: 0;
}
.dashboard-hero__meta {
  margin-top: 8px;
  color: rgba(255,255,255,0.78);
  font-size: var(--fs-sm);
}
.dashboard-hero__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 16px;
}
.dashboard-hero__chips span {
  padding: 6px 10px;
  color: rgba(255,255,255,0.9);
  background: rgba(255,255,255,0.14);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: var(--r-full);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
}
.dashboard-hero__action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 16px;
  color: #0f766e;
  background: #fff;
  border-radius: var(--r-sm);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
}
.dashboard-hero__action:hover { color: #0f766e; opacity: 0.92; }

.page-title {
  font-size: var(--fs-xl);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
}

/* Subscription Card */
.sub-card {
  position: relative;
  background: linear-gradient(145deg, #0f766e, #178d82 42%, #2563eb);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: var(--r-xl);
  padding: 24px;
  box-shadow: var(--shadow-float);
  overflow: hidden;
}
.sub-card::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(115deg, rgba(255,255,255,0.18), transparent 32%);
  pointer-events: none;
}
.sub-card > * { position: relative; z-index: 1; }
.sub-card__header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 20px;
}
.sub-card__plan { display: flex; align-items: center; gap: 12px; }
.sub-card__plan-name {
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: #fff;
}
.sub-card__status {
  font-size: var(--fs-xs);
  padding: 2px 10px;
  border-radius: var(--r-full);
  font-weight: var(--fw-semibold);
}
.status--active { background: rgba(255,255,255,0.18); color: #d8fff4; }
.status--warning { background: rgba(245,158,11,0.18); color: #fff7d6; }
.status--danger { background: rgba(239,68,68,0.2); color: #ffe4e6; }

.sub-card__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.sub-card__action {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 14px;
  border-radius: var(--r-sm);
  background: rgba(255,255,255,0.92);
  color: #0f766e;
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  transition: all var(--t-fast);
}
.sub-card__action:hover {
  background: #fff;
  color: #0f766e;
  transform: translateY(-1px);
}
.sub-card__action--ghost {
  background: rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.92);
  border: 1px solid rgba(255,255,255,0.22);
}
.sub-card__action--ghost:hover {
  border-color: rgba(255,255,255,0.42);
  background: rgba(255,255,255,0.18);
  color: #fff;
}

.sub-card__body {
  display: flex; align-items: center; gap: 40px;
}

/* Traffic Ring */
.traffic-ring {
  position: relative;
  width: 140px; height: 140px;
  flex-shrink: 0;
}
.traffic-ring__svg { width: 100%; height: 100%; }
.traffic-ring__inner {
  position: absolute; inset: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.traffic-ring__svg circle:first-child { stroke: rgba(255,255,255,0.18); }
.traffic-ring__svg circle:last-child { stroke: #fff; }
.traffic-ring__percent {
  font-size: var(--fs-xl);
  font-weight: var(--fw-bold);
  color: #fff;
}
.traffic-ring__label {
  font-size: var(--fs-xs);
  color: rgba(255,255,255,0.7);
}

/* Traffic Details */
.traffic-details {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.traffic-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px;
  background: rgba(255,255,255,0.13);
  border: 1px solid rgba(255,255,255,0.14);
  border-radius: var(--r-sm);
}
.traffic-item__label {
  font-size: var(--fs-xs);
  color: rgba(255,255,255,0.7);
  margin-bottom: 2px;
}
.traffic-item__value {
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: #fff;
}

.text-info { color: var(--c-info); }
.sub-card .text-primary,
.sub-card .text-info,
.sub-card .text-warning,
.sub-card .text-success { color: rgba(255,255,255,0.9); }

/* Dashboard Grid */
.dash-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

/* Card */
.card {
  background: var(--gradient-panel);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  padding: 20px;
  box-shadow: var(--shadow-card);
  backdrop-filter: blur(16px);
}
.card__header {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 16px;
}
.card__title {
  font-size: var(--fs-base);
  font-weight: var(--fw-semibold);
  color: var(--c-text-1);
  flex: 1;
}
.card__btn {
  display: flex; align-items: center; gap: 4px;
  font-size: var(--fs-xs);
  color: var(--c-text-3);
  padding: 4px 8px;
  border-radius: var(--r-sm);
  transition: all var(--t-fast);
}
.card__btn:hover { color: var(--c-primary); background: var(--c-primary-light); }

.stat-value {
  font-size: var(--fs-xl);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
  margin-bottom: 4px;
}
.stat-label {
  font-size: var(--fs-xs);
  color: var(--c-text-3);
}

/* Quick Actions */
.quick-actions {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.quick-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px;
  border-radius: var(--r-sm);
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  color: var(--c-text-2);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
  transition: all var(--t-fast);
  text-decoration: none;
}
.quick-btn:hover {
  background: var(--c-primary-light);
  color: var(--c-primary);
  border-color: var(--c-primary-light);
}

/* Subscribe Link */
.subscribe-link {
  display: flex; align-items: center; gap: 8px;
  padding: 12px;
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
  margin-bottom: 12px;
}
.subscribe-link__url {
  flex: 1;
  font-size: var(--fs-xs);
  color: var(--c-text-2);
  word-break: break-all;
  font-family: monospace;
}
.subscribe-link__copy {
  flex-shrink: 0;
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border-radius: var(--r-sm);
  color: var(--c-text-3);
  transition: all var(--t-fast);
}
.subscribe-link__copy:hover { color: var(--c-primary); background: var(--c-primary-light); }

.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(8px);
}
.qr-modal {
  width: min(520px, 100%);
  background: var(--c-bg-card);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}
.qr-modal__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 20px 16px;
  border-bottom: 1px solid var(--c-border);
}
.qr-modal__header h3 {
  margin: 0 0 4px;
  color: var(--c-text-1);
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
}
.qr-modal__header p {
  margin: 0;
  color: var(--c-text-3);
  font-size: var(--fs-sm);
}
.qr-modal__close {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--c-text-3);
  border-radius: var(--r-sm);
  transition: all var(--t-fast);
}
.qr-modal__close:hover {
  color: var(--c-text-1);
  background: var(--c-bg-elevated);
}
.qr-modal__body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 24px 20px;
}
.qr-modal__code {
  width: 360px;
  max-width: 100%;
  padding: 12px;
  box-sizing: border-box;
  background: #fff;
  border-radius: var(--r-sm);
  border: 1px solid var(--c-border);
}
.qr-modal__image {
  display: block;
  width: 100%;
  height: auto;
  background: #fff;
  image-rendering: pixelated;
}
.qr-modal__empty {
  min-height: 336px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--c-text-3);
  background: #fff;
  border-radius: var(--r-sm);
  font-size: var(--fs-sm);
}
.qr-modal__url {
  width: 100%;
  max-height: 84px;
  overflow: auto;
  padding: 10px 12px;
  color: var(--c-text-2);
  background: var(--c-bg-elevated);
  border-radius: var(--r-sm);
  font-size: var(--fs-xs);
  word-break: break-all;
  font-family: monospace;
}
.qr-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 20px 20px;
  border-top: 1px solid var(--c-border);
}
.qr-modal__actions .btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  width: auto;
}

.subscribe-apps {
  display: flex; flex-wrap: wrap; gap: 8px;
}
.sub-app {
  padding: 7px 12px;
  border: 1px solid var(--c-border);
  border-radius: var(--r-full);
  background: var(--c-surface-glass);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
  color: var(--c-text-2);
  text-decoration: none;
  transition: all var(--t-fast);
}
.sub-app:hover { border-color: var(--c-primary); color: var(--c-primary); background: var(--c-primary-light); }

/* Notices Banner */
.notice-banner {
  display: flex; align-items: center;
  padding: 12px 16px;
  background: var(--c-surface-glass);
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
  color: var(--c-primary);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.notice-marquee {
  flex: 1; overflow: hidden; white-space: nowrap;
}
.notice-marquee__inner {
  display: inline-block;
  animation: marquee 20s linear infinite;
  white-space: nowrap;
}
.notice-marquee__inner:hover {
  animation-play-state: paused;
}
@keyframes marquee {
  0% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}
.notice-marquee__item {
  display: inline-block; margin-right: 40px;
  font-size: var(--fs-sm); font-weight: var(--fw-medium);
  cursor: pointer; position: relative;
}
.notice-marquee__item:hover { text-decoration: underline; }

.notice-expand {
  background: var(--c-bg-card);
  animation: slideDown 0.3s ease;
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
.notice-content {
  font-size: var(--fs-sm); color: var(--c-text-2); line-height: 1.6;
}

/* Helper Classes */
.d-flex { display: flex; }
.justify-between { justify-content: space-between; }
.mb-0 { margin-bottom: 0; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.pb-2 { padding-bottom: 8px; }
.mr-2 { margin-right: 8px; }
.border-bottom { border-bottom: 1px solid var(--c-border); }
.font-bold { font-weight: var(--fw-bold); }
.text-lg { font-size: var(--fs-lg); }

/* Responsive */
@media (max-width: 768px) {
  .dashboard-hero { align-items: flex-start; flex-direction: column; padding: 24px; }
  .dashboard-hero__action { width: 100%; justify-content: center; }
  .sub-card__header { align-items: flex-start; flex-direction: column; gap: 12px; }
  .sub-card__actions { width: 100%; justify-content: stretch; }
  .sub-card__action { flex: 1; justify-content: center; }
  .sub-card__body { flex-direction: column; gap: 20px; }
  .traffic-ring { width: 120px; height: 120px; margin: 0 auto; }
  .dash-grid { grid-template-columns: 1fr; }
  .traffic-details { grid-template-columns: 1fr 1fr; }
}
</style>

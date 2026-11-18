<template>
  <div class="dashboard-page anim-fade-up">
    <div class="dash-layout">
      <!-- Left Main Column -->
      <main class="dash-main">
        <!-- Hero Subscription Card -->
        <div class="sub-hero">
          <div class="sub-hero__left">
            <div class="user-header">
              <div class="user-avatar">
                <span class="avatar-text">{{ avatarInitial }}</span>
              </div>
              <h2 class="user-name">Halo, {{ displayName }}</h2>
            </div>
            
            <div class="sub-info">
              <div class="sub-label">我的订阅</div>
              <div class="sub-title">{{ planName }}</div>
              
              <div class="sub-details">
                <div class="detail-row">到期时间: <span class="fw-bold">{{ expireDate }}</span></div>
                <div class="detail-row">流量重置时间: <span class="fw-bold">无需重置</span></div>
                <div class="detail-row">剩余可用流量: <span class="fw-bold">{{ formatBytes(user.trafficTotal - (user.subscription?.u || 0) - (user.subscription?.d || 0)) }}</span></div>
              </div>
            </div>
          </div>
          
          <div class="sub-hero__right">
            <div class="recommend-card">
              <div class="recommend-header">
                <span class="recommend-tag">套餐推荐</span>
                <Icon name="diamond" :size="20" class="recommend-icon" />
              </div>
              <h3 class="recommend-title">{{ recommendedPlanTitle }}</h3>
              <div class="recommend-price-row">
                <span class="price"><span class="currency">¥</span> {{ recommendedPlanPrice }}</span>
                <router-link :to="recommendedPlanLink" class="btn btn--primary btn--sm">立即购买</router-link>
              </div>
              <div class="recommend-footer">
                {{ recommendedPlanMeta }}
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Import Section -->
        <div class="import-section card" style="padding: 24px; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
          <div class="import-left" style="flex: 1;">
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 8px;">一键导入配置</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0;">支持 iOS, Android, macOS, Windows 全平台主流客户端一键导入</p>
          </div>
          <button class="btn btn--primary" style="padding: 12px 24px; border-radius: 12px; font-weight: 600; box-shadow: 0 4px 12px rgba(15, 118, 110, 0.2);" @click="showSubscribeModal = true">
            导入订阅
          </button>
        </div>

        <!-- Official Clients -->
        <div class="official-clients card">
          <h3 class="clients-title">官方客户端</h3>
          <div class="clients-grid">
            <a :href="appLinks.ios || 'javascript:void(0)'" :target="appLinks.ios ? '_blank' : ''" class="client-item" :class="{'disabled': !appLinks.ios}">
              <div class="client-icon client-icon--ios">
                <Icon name="apple" :size="24" />
              </div>
              <span class="client-name">iOS</span>
            </a>
            <a :href="appLinks.android || 'javascript:void(0)'" :target="appLinks.android ? '_blank' : ''" class="client-item" :class="{'disabled': !appLinks.android}">
              <div class="client-icon client-icon--android">
                <Icon name="android" :size="24" />
              </div>
              <span class="client-name">Android</span>
            </a>
            <a :href="appLinks.macos || 'javascript:void(0)'" :target="appLinks.macos ? '_blank' : ''" class="client-item" :class="{'disabled': !appLinks.macos}">
              <div class="client-icon client-icon--macos">
                <Icon name="monitor" :size="24" />
              </div>
              <span class="client-name">MacOS</span>
            </a>
            <a :href="appLinks.windows || 'javascript:void(0)'" :target="appLinks.windows ? '_blank' : ''" class="client-item" :class="{'disabled': !appLinks.windows}">
              <div class="client-icon client-icon--windows">
                <Icon name="grid" :size="24" />
              </div>
              <span class="client-name">Windows</span>
            </a>
          </div>
        </div>

        <!-- App Tutorials -->
        <div class="app-tutorials">
          <a href="#/knowledge" class="app-card app-card--ios">
            <span class="app-tag">热门</span>
            <div class="app-icon-wrap">
              <svg viewBox="0 0 24 24" class="app-logo" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91 1.65.17 2.76.81 3.5 1.88-3 1.8-2.52 5.5.58 6.74-.71 1.76-1.56 3.4-2.29 4.04zM15.42 5.37c.78-.96 1.3-2.28 1.15-3.62-1.15.05-2.56.78-3.37 1.74-.71.84-1.33 2.19-1.15 3.49 1.29.1 2.58-.65 3.37-1.61z"/></svg>
            </div>
            <div class="app-info">
              <h3>IOS | Shadowrocket</h3>
              <p>视频教程, 保姆教学</p>
            </div>
            <div class="app-action">
              <span>点击播放视频教程</span>
              <Icon name="play-circle" :size="16" />
            </div>
          </a>

          <a href="#/knowledge" class="app-card app-card--windows">
            <span class="app-tag">推荐</span>
            <div class="app-icon-wrap">
              <svg viewBox="0 0 24 24" class="app-logo" fill="currentColor"><path d="M2.5 5.5L10.5 4.5V11.5H2.5V5.5ZM11.5 4.5L21.5 3V11.5H11.5V4.5ZM2.5 12.5H10.5V19.5L2.5 18.5V12.5ZM11.5 12.5H21.5V21L11.5 19.5V12.5Z"/></svg>
            </div>
            <div class="app-info">
              <h3>Windows | Clash</h3>
              <p>图文教程, 轻松掌握</p>
            </div>
            <div class="app-action">
              <span>下载/查看教程</span>
              <Icon name="arrow-up-right" :size="16" />
            </div>
          </a>

          <a href="#/knowledge" class="app-card app-card--android">
            <div class="app-icon-wrap">
              <svg viewBox="0 0 24 24" class="app-logo" fill="currentColor"><path d="M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85-.29-.15-.65-.06-.81.24l-1.92 3.32C15.12 8.44 13.62 8 12 8c-1.62 0-3.12.44-4.45 1.01L5.63 5.69c-.15-.29-.52-.39-.81-.24-.31.16-.42.54-.26.85l1.84 3.18C3.78 10.85 2 13.22 2 16h20c0-2.78-1.78-5.15-4.4-6.52zM7 14.25c-.41 0-.75-.34-.75-.75s.34-.75.75-.75.75.34.75.75-.34.75-.75.75zm10 0c-.41 0-.75-.34-.75-.75s.34-.75.75-.75.75.34.75.75-.34.75-.75.75z"/></svg>
            </div>
            <div class="app-info">
              <h3>安卓 | Clash</h3>
              <p>图文教程, 轻松掌握</p>
            </div>
            <div class="app-action">
              <span>下载/查看教程</span>
              <Icon name="arrow-up-right" :size="16" />
            </div>
          </a>
        </div>

        <!-- Invite Banner -->
        <div class="invite-banner">
          <div class="invite-content">
            <h2>邀请好友<br>赚取丰厚佣金</h2>
            <div class="invite-tags">
              <span class="tag-percent">10<small>%</small></span>
              <span class="tag-label">佣金比例</span>
            </div>
            <p>立刻赚钱丰厚佣金返现，可提现或用于消费</p>
            <router-link to="/invite" class="btn btn--primary invite-btn">立即邀请</router-link>
          </div>
          <div class="invite-image">
            <img src="@/assets/images/invite_robot.png" alt="Invite" />
          </div>
        </div>
      </main>

      <!-- Right Aside Column -->
      <aside class="dash-sidebar">
        <div class="promo-card card">
          <div class="promo-header">
            <h3 class="font-bold">重要通知</h3>
            <button class="notice-bell-btn" title="查看全部通知" @click="showNoticesDrawer = true">
              <Icon name="bell" :size="22" class="text-primary" />
              <span class="notice-badge" v-if="user.notices && user.notices.length > 0"></span>
            </button>
          </div>
          
          <div class="promo-graphic">
            <img src="@/assets/images/promo_gift.png" alt="Promo" class="promo-img" />
            <div class="promo-overlay">
              <div class="promo-timer">-9754:-24:-43</div>
              <div class="promo-price">0.00 <small>元</small></div>
              <div class="promo-desc">EveryOne 收下这份礼物吧！</div>
            </div>
            <button class="btn btn--primary promo-btn" @click="$router.push('/shop')">点击领券下单</button>
          </div>
        </div>
      </aside>
    </div>

    <!-- Notifications Drawer -->
    <Teleport to="body">
      <div class="notice-drawer-overlay" v-if="showNoticesDrawer" @click="showNoticesDrawer = false"></div>
      <div class="notice-drawer" :class="{ 'notice-drawer--open': showNoticesDrawer }">
        <div class="notice-drawer__header">
          <button class="notice-drawer__close" @click="showNoticesDrawer = false">
            <Icon name="x" :size="20" />
          </button>
          <h3 class="notice-drawer__title">全部通知</h3>
        </div>
        <div class="notice-drawer__body">
          <div v-if="!user.notices || user.notices.length === 0" class="notice-empty">暂无通知</div>
          <div class="notice-item" v-for="notice in user.notices" :key="notice.id">
            <div class="notice-item__header" @click="activeNotice = activeNotice === notice.id ? null : notice.id">
              <span class="notice-item__title">{{ notice.title }}</span>
              <Icon :name="activeNotice === notice.id ? 'chevron-up' : 'chevron-down'" :size="16" class="notice-item__icon" />
            </div>
            <div class="notice-item__date">{{ new Date(notice.created_at * 1000).toLocaleString() }}</div>
            <transition name="notice-expand">
              <div class="notice-item__content" v-if="activeNotice === notice.id" v-html="notice.content"></div>
            </transition>
          </div>
        </div>
      </div>
    </Teleport>
    
    <SubscribeModal 
      v-model:show="showSubscribeModal" 
      :subscribe-url="subscribeUrl" 
      :subscribe-name="subscribeName"
      @open-qr="openQrModal" 
    />

    <Teleport to="body">
      <div v-if="qrModalOpen" class="modal-overlay" @click.self="qrModalOpen = false">
        <div class="qr-modal">
          <div class="qr-modal__header">
            <div>
              <h3>扫描二维码订阅</h3>
              <p>使用各端支持扫码的客户端导入订阅</p>
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
            <div class="qr-modal__targets">
              <button
                v-for="option in qrOptions"
                :key="option.key"
                class="qr-target"
                :class="{ 'qr-target--active': selectedQrKey === option.key }"
                @click="selectQrOption(option.key)"
              >
                {{ option.label }}
              </button>
            </div>
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
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import SubscribeModal from '@/components/ui/SubscribeModal.vue'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import * as userApi from '@/api/user'
import { copyText } from '@/utils/clipboard'
import { listFromPayload } from '@/api/normalizers'

const user = useUserStore()
const appStore = useAppStore()
const copied = ref(false)
const qrCopied = ref(false)
const qrModalOpen = ref(false)
const qrLoading = ref(false)
const qrImage = ref('')
const qrContent = ref('')
const qrError = ref('')
const qrCache = new Map()
const activeNotice = ref(null)
const showNoticesDrawer = ref(false)
const showSubscribeModal = ref(false)
const selectedQrKey = ref('flclash')
const recommendedPlan = ref(null)
const appLinks = ref({
  ios: '',
  android: '',
  macos: '',
  windows: ''
})
const activeNoticeObj = computed(() => {
  if (!activeNotice.value) return null
  return user.notices.find(n => n.id === activeNotice.value)
})
const importantNotices = computed(() => (user.notices || []).slice(0, 3))
const tutorialCards = [
  { title: 'iOS 使用教程', tag: 'Shadowrocket / Stash', desc: '扫码或一键导入订阅，快速完成移动端配置。', icon: 'shield', to: '/knowledge' },
  { title: 'Windows 使用教程', tag: 'Clash Meta / FlClash', desc: '适合桌面端长期使用，支持规则分流和测速。', icon: 'monitor', to: '/knowledge' },
  { title: 'Android 使用教程', tag: 'Hiddify / V2rayNG', desc: '支持常见安卓客户端订阅导入与节点切换。', icon: 'zap', to: '/knowledge' }
]
const periodDict = {
  month_price: '月付',
  quarter_price: '季付',
  half_year_price: '半年付',
  year_price: '年付',
  two_year_price: '两年付',
  three_year_price: '三年付',
  onetime_price: '一次性',
  reset_price: '重置流量包'
}
const periodKeys = {
  monthly: '月付',
  quarterly: '季付',
  half_yearly: '半年付',
  yearly: '年付',
  two_yearly: '两年付',
  three_yearly: '三年付',
  onetime: '一次性',
  reset_traffic: '重置包'
}

const circumference = 2 * Math.PI * 52
const dashOffset = computed(() => {
  return circumference - (user.trafficPercent / 100) * circumference
})

const subscribeUrl = computed(() => normalizeSubscribeUrl(user.subscribe?.subscribe_url || ''))
const subscribeName = computed(() => window.settings?.title || 'Xboard')
const subApps = computed(() => buildSubscribeApps(subscribeUrl.value, subscribeName.value))
const qrOptions = [
  { key: 'shadowrocket', label: 'Shadowrocket', target: 'shadowrocket', flag: 'shadowrocket', mode: 'subscribe' },
  { key: 'flclash', label: 'FlClash', target: 'flclash', flag: 'flclash', mode: 'subscribe' },
  { key: 'meta', label: 'Clash Meta', target: 'meta', flag: 'meta', mode: 'subscribe' },
  { key: 'stash', label: 'Stash', target: 'stash', flag: 'stash', mode: 'subscribe' },
  { key: 'surge', label: 'Surge', target: 'surge', flag: 'surge', mode: 'subscribe' },
  { key: 'surfboard', label: 'Surfboard', target: 'surfboard', flag: 'surfboard', mode: 'subscribe' },
  { key: 'sing-box', label: 'Sing-box', target: 'sing-box', flag: 'sing-box', mode: 'subscribe' },
  { key: 'hiddify', label: 'Hiddify', target: 'hiddify', flag: 'hiddify', mode: 'subscribe' },
  { key: 'nekobox', label: 'NekoBox', target: 'nekobox', flag: 'nekobox', mode: 'subscribe' },
  { key: 'v2rayng', label: 'V2rayNG', target: 'v2rayng', flag: 'v2rayng', mode: 'subscribe' },
  { key: 'loon', label: 'Loon', target: 'loon', flag: 'loon', mode: 'subscribe' },
  { key: 'raw', label: '原始订阅', target: 'raw', flag: '', mode: 'subscribe' }
]
const activeQrOption = computed(() => qrOptions.find(option => option.key === selectedQrKey.value) || qrOptions[0])
const qrSubscribeLink = computed(() => buildQrContent(activeQrOption.value))
const planName = computed(() => user.subscribe?.plan?.name || user.info?.plan?.name || '未订阅')
const recommendedPrimaryPeriod = computed(() => getPrimaryPeriod(recommendedPlan.value))
const recommendedPlanTitle = computed(() => recommendedPlan.value?.name || '暂无可售套餐')
const recommendedPlanPrice = computed(() => {
  if (!recommendedPrimaryPeriod.value) return '0.00'
  return (recommendedPrimaryPeriod.value.price / 100).toFixed(2)
})
const recommendedPlanLink = computed(() => {
  if (!recommendedPlan.value?.id) return '/shop'
  return { path: '/shop', query: { plan: recommendedPlan.value.id } }
})
const recommendedPlanMeta = computed(() => {
  if (!recommendedPlan.value) return '暂无套餐数据'
  return `套餐流量:${formatPlanTrafficCompact(recommendedPlan.value.transfer_enable)} / 带宽速率:${formatPlanSpeed(recommendedPlan.value.speed_limit)}`
})
const displayName = computed(() => {
  const email = user.info?.email || ''
  return user.info?.name || (email ? email.split('@')[0] : 'User')
})
const avatarInitial = computed(() => {
  const source = displayName.value || user.info?.email || 'U'
  return String(source).trim().charAt(0).toUpperCase()
})
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
      link: buildImportLink('hiddify', rawUrl('hiddify'), name)
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
  return buildImportLink('flclash', appendSubscribeFlag(url, 'flclash'), name)
}

function buildQrContent(option) {
  if (!subscribeUrl.value || !option) return ''
  const targetUrl = option.target === 'raw'
    ? subscribeUrl.value
    : appendSubscribeFlag(subscribeUrl.value, option.flag || option.target)
  return option.mode === 'import'
    ? buildImportLink(option.target, targetUrl, subscribeName.value)
    : targetUrl
}

function buildImportLink(target, url, name) {
  if (!url) return ''
  const encodedName = encodeURIComponent(name)
  const encodedUrl = encodeURIComponent(url)
  switch (target) {
    case 'stash':
      return `stash://install-config?url=${encodedUrl}&name=${encodedName}`
    case 'surge':
      return `surge:///install-config?url=${encodedUrl}&name=${encodedName}`
    case 'surfboard':
      return `surfboard:///install-config?url=${encodedUrl}&name=${encodedName}`
    case 'sing-box':
      return `sing-box://import-remote-profile?url=${encodedUrl}#${encodedName}`
    case 'hiddify':
      return `hiddify://import/${encodedUrl}#${encodedName}`
    case 'v2rayng':
      return `v2rayng://install-sub?url=${encodedUrl}&name=${encodedName}`
    case 'loon':
      return `loon://import?sub=${encodedUrl}`
    case 'raw':
      return url
    default:
      return `clash://install-config?url=${encodedUrl}&name=${encodedName}`
  }
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

function getAvailablePeriodsForPlan(plan) {
  if (!plan) return []
  const periods = []

  if (plan.prices && typeof plan.prices === 'object') {
    Object.entries(plan.prices).forEach(([key, price]) => {
      const normalizedPrice = normalizePrice(price)
      if (normalizedPrice !== null && periodKeys[key]) {
        periods.push({ key, name: periodKeys[key], price: normalizedPrice })
      }
    })
  } else {
    Object.entries(periodDict).forEach(([key, name]) => {
      const normalizedPrice = normalizePrice(plan[key])
      if (normalizedPrice !== null) {
        periods.push({ key, name, price: normalizedPrice })
      }
    })
  }

  return periods
}

function getPrimaryPeriod(plan) {
  const periods = getAvailablePeriodsForPlan(plan)
  if (periods.length === 0) return null
  return periods.reduce((lowest, period) => period.price < lowest.price ? period : lowest, periods[0])
}

function normalizePrice(price) {
  if (price === null || price === undefined || price === '') return null
  const normalized = Number(price)
  if (!Number.isFinite(normalized) || normalized < 0) return null
  return normalized
}

function formatPlanTrafficCompact(traffic) {
  const value = Number(traffic || 0)
  if (!value) return '0GB'
  if (value >= 1024 && value % 1024 === 0) return `${value / 1024}TB`
  if (value >= 1024) return `${(value / 1024).toFixed(1)}TB`
  return `${value}GB`
}

function formatPlanSpeed(speed) {
  const value = Number(speed || 0)
  if (!value) return '1000Mbps以上'
  return `${value}Mbps`
}

async function fetchRecommendedPlan() {
  try {
    const res = await userApi.getPlans()
    const plans = listFromPayload(res)
      .filter(plan => plan.show !== false && plan.sell !== false && getAvailablePeriodsForPlan(plan).length > 0)
      .sort((a, b) => {
        const aPeriod = getPrimaryPeriod(a)
        const bPeriod = getPrimaryPeriod(b)
        const priceDiff = (aPeriod?.price || Number.MAX_SAFE_INTEGER) - (bPeriod?.price || Number.MAX_SAFE_INTEGER)
        if (priceDiff !== 0) return priceDiff
        return Number(a.sort || 0) - Number(b.sort || 0)
      })
    recommendedPlan.value = plans[0] || null
  } catch (error) {
    console.warn('Failed to fetch recommended plan', error)
  }
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

function selectQrOption(key) {
  if (selectedQrKey.value === key) return
  selectedQrKey.value = key
  if (qrModalOpen.value) fetchSubscribeQrCode()
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
  const option = activeQrOption.value
  const fallbackContent = buildQrContent(option)
  const cacheKey = `${option.target}:${option.mode}:${fallbackContent}`
  qrLoading.value = true
  qrError.value = ''
  qrImage.value = ''
  qrContent.value = ''
  try {
    qrContent.value = fallbackContent
    if (!fallbackContent) throw new Error('无效的订阅链接')

    if (qrCache.has(cacheKey)) {
      const cached = qrCache.get(cacheKey)
      qrImage.value = cached.image
      qrContent.value = cached.content
      return
    }
    
    const data = await userApi.getSubscribeQrCode({
      target: option.target,
      mode: option.mode,
      size: 360,
      use_current_host: 1
    })
    qrImage.value = data.image || ''
    qrContent.value = data.content || data.subscribe_url || fallbackContent
    if (!qrImage.value) throw new Error('二维码生成失败')
    qrCache.set(cacheKey, {
      image: qrImage.value,
      content: qrContent.value
    })
  } catch (error) {
    qrError.value = error.message || '二维码生成失败'
    qrContent.value = fallbackContent
    qrImage.value = ''
    if (!qrContent.value) appStore.showToast('二维码生成失败，请复制链接导入', 'error')
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

async function fetchAppVersion() {
  try {
    if (!user.isLoaded) {
      await user.fetchAll()
    }
    const url = user.subscribe?.subscribe_url || ''
    let token = ''
    const match = url.match(/token=([^&]+)/)
    if (match) {
      token = match[1]
    } else {
      const paths = url.split('/')
      token = paths[paths.length - 1] || ''
    }
    
    if (!token) return

    const res = await userApi.getAppVersion(token)
    if (res) {
      appLinks.value.android = res.android_download_url || ''
      appLinks.value.macos = res.macos_download_url || ''
      appLinks.value.windows = res.windows_download_url || ''
      appLinks.value.ios = res.macos_version || ''
    }
  } catch (error) {
    console.warn('Failed to fetch app version', error)
  }
}

onMounted(() => {
  if (!user.isLoaded) user.fetchAll()
  fetchRecommendedPlan()
  fetchAppVersion()
})
</script>
<style scoped>
.dashboard-page {
  width: 100%;
}
.dash-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 24px;
}
.dash-main {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Sub Hero */
.official-clients {
  padding: 24px;
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}
.clients-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 16px;
  margin-top: 0;
}
.clients-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.client-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px 10px;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
  text-decoration: none;
  color: #334155;
  transition: all 0.2s ease;
}
.client-item:hover:not(.disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border-color: var(--c-primary);
}
.client-item.disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.client-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
}
.client-icon--ios {
  background: #eff6ff;
  color: #3b82f6;
}
.client-icon--android {
  background: #f0fdf4;
  color: #22c55e;
}
.client-icon--macos {
  background: #f1f5f9;
  color: #64748b;
}
.client-icon--windows {
  background: #eff6ff;
  color: #0ea5e9;
}
.client-name {
  font-size: 0.95rem;
  font-weight: 600;
}
@media (max-width: 640px) {
  .clients-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Sub Hero */
.sub-hero {
  display: flex;
  background: linear-gradient(135deg, #a7f3d0 0%, #6ee7b7 100%);
  border-radius: var(--r-xl);
  padding: 30px;
  gap: 24px;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.sub-hero__left {
  flex: 1;
  color: #065f46;
}
.user-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
}
.user-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 800;
  color: var(--c-primary);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.user-avatar .avatar-text {
  font-size: 20px;
  font-weight: 800;
  color: var(--c-primary);
}
.user-name {
  font-size: 1.4rem;
  font-weight: 800;
  margin: 0;
}
.sub-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.sub-label {
  font-size: var(--fs-xs);
  opacity: 0.8;
  font-weight: 600;
}
.sub-title {
  font-size: 2rem;
  font-weight: 800;
  margin-bottom: 12px;
  letter-spacing: -0.5px;
}
.sub-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: var(--fs-sm);
  opacity: 0.85;
}

/* Recommend Card */
.sub-hero__right {
  width: 300px;
  display: flex;
  align-items: center;
}
.recommend-card {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border-radius: var(--r-lg);
  padding: 20px;
  width: 100%;
  box-shadow: var(--shadow-float);
}
.recommend-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.recommend-tag {
  font-size: var(--fs-xs);
  color: var(--c-text-3);
  font-weight: 600;
}
.recommend-icon { color: #10b981; }
.recommend-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #10b981;
  margin: 0 0 16px 0;
}
.recommend-price-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 16px;
}
.recommend-price-row .price {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--c-text-1);
}
.recommend-price-row .currency {
  font-size: 1rem;
}
.recommend-footer {
  font-size: var(--fs-xs);
  color: var(--c-text-3);
  text-align: center;
  padding-top: 12px;
  border-top: 1px dashed var(--c-border);
}

/* App Tutorials */
.app-tutorials {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-top: 24px;
}
.app-card {
  position: relative;
  border-radius: var(--r-lg);
  padding: 24px;
  display: flex;
  flex-direction: column;
  text-decoration: none;
  overflow: hidden;
  transition: transform var(--t-base);
}
.app-card:hover { transform: translateY(-4px); }
.app-card--ios {
  background: linear-gradient(135deg, #c4b5fd 0%, #a78bfa 100%);
  color: #4c1d95;
}
.app-card--windows {
  background: linear-gradient(135deg, #bae6fd 0%, #7dd3fc 100%);
  color: #0c4a6e;
}
.app-card--android {
  background: linear-gradient(135deg, #a7f3d0 0%, #6ee7b7 100%);
  color: #064e3b;
}
.app-tag {
  position: absolute;
  top: 16px; left: 16px;
  background: rgba(255, 255, 255, 0.6);
  padding: 2px 8px;
  border-radius: var(--r-full);
  font-size: 10px;
  font-weight: 800;
}
.app-card--ios .app-tag { color: #dc2626; }
.app-card--windows .app-tag { color: #0284c7; }
.app-icon-wrap {
  text-align: center;
  margin-top: 16px;
  margin-bottom: 24px;
}
.app-logo {
  width: 48px; height: 48px;
  opacity: 0.8;
}
.app-info h3 {
  font-size: 1.1rem;
  font-weight: 800;
  margin: 0 0 4px 0;
  text-align: center;
}
.app-info p {
  font-size: var(--fs-xs);
  opacity: 0.8;
  margin: 0;
  text-align: center;
}
.app-action {
  margin-top: 24px;
  padding-top: 12px;
  border-top: 1px solid rgba(0,0,0,0.05);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: var(--fs-xs);
  font-weight: 600;
}

/* Import Section */
.import-section {
  display: flex;
  padding: 24px;
  gap: 32px;
  margin-top: 24px;
  border-radius: var(--r-xl);
}
.import-left {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 24px;
}
.import-field label, .client-icons label {
  display: block;
  font-size: var(--fs-sm);
  color: var(--c-text-2);
  font-weight: 600;
}
.client-list {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.copyable-input {
  position: relative;
  cursor: pointer;
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  border-radius: var(--r-md);
  transition: all var(--t-fast);
  display: block; /* override flex */
}
.copyable-input:hover {
  border-color: var(--c-primary);
}
.copyable-input .form-input {
  width: 100%;
  padding: 12px 48px 12px 16px;
  background: transparent;
  border: none;
  color: var(--c-text-1);
  cursor: pointer;
  outline: none;
}
.copyable-input .copy-icon {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--c-text-1);
}
.client-svg {
  width: 22px;
  height: 22px;
}
.client-btn {
  width: 44px; height: 44px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid var(--c-border);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  color: var(--c-text-1);
  transition: all var(--t-fast);
  box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.client-btn:hover {
  background: #fff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
  border-color: var(--c-border);
}

.import-right {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding-left: 32px;
  border-left: 1px dashed var(--c-border);
}
.qr-container {
  padding: 8px;
  background: #fff;
  border-radius: var(--r-md);
  box-shadow: var(--shadow-sm);
}
.qr-text {
  font-size: var(--fs-xs);
  color: var(--c-text-3);
  text-align: center;
  line-height: 1.5;
}

/* Invite Banner */
.invite-banner {
  margin-top: 24px;
  background: linear-gradient(90deg, #f59e0b 0%, #ea580c 100%);
  border-radius: var(--r-xl);
  display: flex;
  position: relative;
  overflow: hidden;
  color: #fff;
  min-height: 200px;
}
.invite-content {
  padding: 32px;
  flex: 1;
  position: relative;
  z-index: 2;
}
.invite-content h2 {
  font-size: 1.8rem;
  font-weight: 800;
  margin: 0 0 16px 0;
  line-height: 1.2;
}
.invite-tags {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.tag-percent {
  background: #fff;
  color: #ea580c;
  padding: 4px 16px;
  border-radius: var(--r-full);
  font-size: 1.5rem;
  font-weight: 800;
}
.tag-percent small { font-size: 1rem; }
.tag-label {
  background: rgba(0,0,0,0.3);
  padding: 6px 16px;
  border-radius: var(--r-full);
  font-size: var(--fs-sm);
  font-weight: 600;
}
.invite-content p {
  font-size: var(--fs-sm);
  opacity: 0.9;
  margin: 0 0 20px 0;
}
.invite-btn {
  background: #451a03;
  color: #fff;
  border: none;
  padding: 0 24px;
}
.invite-btn:hover {
  background: #78350f;
  box-shadow: var(--shadow-glow);
}
.invite-image {
  position: absolute;
  right: -20px;
  bottom: -20px;
  width: 320px;
  height: 240px;
  z-index: 1;
}
.invite-image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

/* Sidebar Promo */
.dash-sidebar {
  display: flex;
  flex-direction: column;
}
.promo-card {
  padding: 24px;
  border-radius: var(--r-xl);
}
.promo-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.promo-graphic {
  position: relative;
  border-radius: var(--r-lg);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20px;
  background: radial-gradient(circle at center, #fbcfe8 0%, #fce7f3 100%);
}
.promo-img {
  width: 100%;
  max-width: 200px;
  height: auto;
  margin-bottom: 20px;
  position: relative;
  z-index: 1;
  filter: drop-shadow(0 10px 20px rgba(244, 63, 94, 0.4));
}
.promo-overlay {
  text-align: center;
  position: relative;
  z-index: 2;
  margin-bottom: 20px;
}
.promo-timer {
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
  text-shadow: 0 2px 4px rgba(225, 29, 72, 0.5);
  margin-bottom: 8px;
}
.promo-price {
  font-size: 3rem;
  font-weight: 800;
  color: #fff;
  text-shadow: 0 2px 8px rgba(225, 29, 72, 0.6);
  line-height: 1;
  margin-bottom: 8px;
}
.promo-desc {
  font-size: var(--fs-sm);
  color: #fff;
  font-weight: 600;
}
.promo-btn {
  background: linear-gradient(90deg, #f43f5e 0%, #e11d48 100%);
  color: #fff;
  border: none;
  width: 100%;
  border-radius: var(--r-full);
  height: 44px;
  font-size: var(--fs-md);
  position: relative;
  z-index: 2;
  box-shadow: 0 8px 16px rgba(225, 29, 72, 0.3);
}

/* Notice Bell Button */
.notice-bell-btn {
  position: relative;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 8px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--t-fast);
}
.notice-bell-btn:hover {
  background: rgba(0,0,0,0.05);
}
.notice-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 8px;
  height: 8px;
  background-color: #ef4444;
  border-radius: 50%;
  border: 2px solid #fff;
}

/* Notice Drawer */
.notice-drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(2px);
  z-index: 1000;
}
.notice-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 400px;
  max-width: 100vw;
  background: var(--c-bg-base);
  box-shadow: var(--shadow-xl);
  z-index: 1001;
  transform: translateX(100%);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
}
.notice-drawer--open {
  transform: translateX(0);
}
.notice-drawer__header {
  display: flex;
  align-items: center;
  padding: 24px;
  border-bottom: 1px solid var(--c-border);
  gap: 16px;
}
.notice-drawer__close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--c-text-2);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: var(--r-sm);
  transition: all var(--t-fast);
}
.notice-drawer__close:hover {
  background: var(--c-bg-hover);
  color: var(--c-text-1);
}
.notice-drawer__title {
  font-size: 1.1rem;
  font-weight: 800;
  margin: 0;
  color: var(--c-text-1);
}
.notice-drawer__body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.notice-empty {
  text-align: center;
  color: var(--c-text-3);
  padding: 40px 0;
}
.notice-item {
  background: var(--c-surface);
  border-radius: var(--r-lg);
  padding: 16px;
  border: 1px solid var(--c-border);
}
.notice-item__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  user-select: none;
}
.notice-item__title {
  font-weight: 700;
  color: var(--c-text-1);
  font-size: var(--fs-base);
}
.notice-item__icon {
  color: var(--c-text-3);
  transition: transform var(--t-fast);
}
.notice-item__date {
  font-size: 12px;
  color: var(--c-text-3);
  margin-top: 4px;
}
.notice-item__content {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px dashed var(--c-border);
  font-size: var(--fs-sm);
  color: var(--c-text-2);
  line-height: 1.6;
}

.notice-expand-enter-active,
.notice-expand-leave-active {
  transition: all 0.2s ease-out;
  overflow: hidden;
}
.notice-expand-enter-from,
.notice-expand-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0;
  margin-top: 0;
  border-color: transparent;
}
.notice-expand-enter-to,
.notice-expand-leave-from {
  opacity: 1;
  max-height: 1000px; /* arbitrary large value */
}

@media (max-width: 1080px) {
  .dash-layout { grid-template-columns: 1fr; }
  .app-tutorials { grid-template-columns: 1fr; }
  .import-section { flex-direction: column; }
  .import-right { border-left: none; border-top: 1px dashed var(--c-border); padding-left: 0; padding-top: 24px; }
  .sub-hero { flex-direction: column; }
  .sub-hero__right { width: 100%; }
}

/* QR Modal Styles */
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
  width: min(460px, 100%);
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}
.qr-modal__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f1f5f9;
}
.qr-modal__header h3 {
  margin: 0 0 4px;
  color: #1e293b;
  font-size: 1.125rem;
  font-weight: 700;
}
.qr-modal__header p {
  margin: 0;
  color: #64748b;
  font-size: 0.875rem;
}
.qr-modal__close {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  border-radius: 8px;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}
.qr-modal__close:hover {
  color: #0f172a;
  background: #f1f5f9;
}
.qr-modal__body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 24px;
}
.qr-modal__code {
  width: 280px;
  height: 280px;
  padding: 12px;
  box-sizing: border-box;
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.qr-modal__image {
  display: block;
  width: 100%;
  height: 100%;
  image-rendering: pixelated;
}
.qr-modal__empty {
  color: #94a3b8;
  font-size: 0.875rem;
}
.qr-modal__targets {
  width: 100%;
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px;
}
.qr-target {
  height: 32px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.qr-target:hover {
  border-color: #0f766e;
  color: #0f766e;
}
.qr-target--active {
  border-color: #0f766e;
  background: #0f766e;
  color: #fff;
}
.qr-target--active:hover {
  color: #fff;
}
.qr-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px 20px;
  border-top: 1px solid #f1f5f9;
}
</style>

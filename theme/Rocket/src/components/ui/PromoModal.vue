<template>
  <div v-if="visible" class="promo-overlay" @click.self="dismiss">
      <section class="promo-modal" role="dialog" aria-modal="true" aria-labelledby="promo-title">
        <button class="promo-modal__close" type="button" title="关闭" @click="dismiss">
          <Icon name="x" :size="18" />
        </button>

        <div class="promo-modal__icon">
          <Icon name="gift" :size="38" />
        </div>
        <span class="promo-modal__eyebrow">Holiday Gift</span>
        <h2 id="promo-title">{{ title }}</h2>
        <p>{{ subtitle }}</p>

        <div class="promo-modal__amount">
          <span>¥</span>
          <strong>{{ amount }}</strong>
        </div>

        <div v-if="couponCode" class="promo-modal__coupon">
          <span>优惠码</span>
          <code>{{ couponCode }}</code>
        </div>

        <div v-if="countdown" class="promo-modal__countdown">
          <Icon name="clock" :size="15" />
          <span>{{ countdown }}</span>
        </div>

        <button class="promo-modal__action" type="button" @click="goShop">
          点击领券下单
        </button>
      </section>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const visible = ref(false)
const now = ref(Date.now())
let timer = null

const settings = computed(() => window.settings || {})
const enabled = computed(() => String(settings.value.promo_enable ?? '1') !== '0')
const title = computed(() => settings.value.promo_title || 'EveryOne 收下这份礼物吧！')
const subtitle = computed(() => settings.value.promo_subtitle || '本次优惠金额')
const amount = computed(() => settings.value.promo_amount || '0.00')
const couponCode = computed(() => String(settings.value.promo_coupon_code || '').trim())
const endsAt = computed(() => parseDate(settings.value.promo_ends_at))
const storageKey = computed(() => [
  'rocket_promo_dismissed',
  title.value,
  couponCode.value,
  settings.value.promo_ends_at || ''
].join(':'))

const shouldRender = computed(() => {
  if (!enabled.value || !auth.isLoggedIn) return false
  if (route.meta?.guest || route.path === '/home') return false
  if (endsAt.value && endsAt.value.getTime() <= now.value) return false
  return localStorage.getItem(storageKey.value) !== '1'
})

const countdown = computed(() => {
  if (!endsAt.value) return ''
  const distance = endsAt.value.getTime() - now.value
  if (distance <= 0) return ''
  const days = Math.floor(distance / 86400000)
  const hours = Math.floor((distance % 86400000) / 3600000)
  const minutes = Math.floor((distance % 3600000) / 60000)
  if (days > 0) return `${days} 天 ${hours} 小时后结束`
  return `${hours} 小时 ${minutes} 分钟后结束`
})

watch(shouldRender, (value) => {
  visible.value = value
}, { immediate: true })

function parseDate(value) {
  if (!value) return null
  const normalized = String(value).replace(/-/g, '/')
  const date = new Date(normalized)
  return Number.isNaN(date.getTime()) ? null : date
}

function dismiss() {
  localStorage.setItem(storageKey.value, '1')
  visible.value = false
}

function goShop() {
  localStorage.setItem(storageKey.value, '1')
  visible.value = false
  router.push({
    path: '/shop',
    query: couponCode.value ? { coupon: couponCode.value } : {}
  })
}

onMounted(() => {
  timer = window.setInterval(() => {
    now.value = Date.now()
  }, 30000)
})

onBeforeUnmount(() => {
  if (timer) window.clearInterval(timer)
})
</script>

<style scoped>
.promo-overlay {
  position: fixed;
  inset: 0;
  z-index: 2200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.56);
  backdrop-filter: blur(12px);
}
.promo-modal {
  position: relative;
  width: min(430px, 100%);
  padding: 42px 32px 32px;
  text-align: center;
  color: #fff;
  background:
    radial-gradient(circle at 50% 0, rgba(255,255,255,0.34), transparent 32%),
    linear-gradient(155deg, #ff738a, #f34a78 48%, #cf2d65);
  border: 1px solid rgba(255,255,255,0.28);
  border-radius: 18px;
  box-shadow: 0 34px 90px rgba(214, 45, 101, 0.38);
  overflow: hidden;
}
.promo-modal::before,
.promo-modal::after {
  content: "";
  position: absolute;
  width: 180px;
  height: 180px;
  border: 1px solid rgba(255,255,255,0.16);
  border-radius: 999px;
  pointer-events: none;
}
.promo-modal::before { left: -78px; top: -92px; }
.promo-modal::after { right: -96px; bottom: -96px; }
.promo-modal__close {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 2;
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: rgba(255,255,255,0.88);
  background: rgba(255,255,255,0.14);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: var(--r-sm);
}
.promo-modal__icon {
  position: relative;
  z-index: 1;
  width: 76px;
  height: 76px;
  margin: 0 auto 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #f24973;
  background: #fff;
  border-radius: 999px;
  box-shadow: 0 18px 38px rgba(152, 28, 74, 0.24);
}
.promo-modal__eyebrow {
  position: relative;
  z-index: 1;
  display: inline-flex;
  margin-bottom: 8px;
  color: rgba(255,255,255,0.76);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
}
.promo-modal h2 {
  position: relative;
  z-index: 1;
  margin: 0;
  color: #fff;
  font-size: clamp(1.45rem, 5vw, 2rem);
  line-height: 1.24;
}
.promo-modal p {
  position: relative;
  z-index: 1;
  margin: 10px 0 0;
  color: rgba(255,255,255,0.82);
  font-size: var(--fs-sm);
}
.promo-modal__amount {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
  margin-top: 20px;
  color: #fff;
}
.promo-modal__amount span {
  font-size: 1.6rem;
  font-weight: 800;
}
.promo-modal__amount strong {
  font-size: clamp(3.1rem, 13vw, 5.1rem);
  line-height: 0.95;
  font-weight: 900;
}
.promo-modal__coupon {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 18px auto 0;
  padding: 10px 12px;
  color: rgba(255,255,255,0.82);
  background: rgba(255,255,255,0.15);
  border: 1px solid rgba(255,255,255,0.22);
  border-radius: var(--r-sm);
}
.promo-modal__coupon code {
  color: #fff;
  font-weight: 800;
  letter-spacing: 0;
}
.promo-modal__countdown {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 14px;
  color: rgba(255,255,255,0.76);
  font-size: var(--fs-xs);
}
.promo-modal__action {
  position: relative;
  z-index: 1;
  width: 100%;
  min-height: 46px;
  margin-top: 22px;
  color: #d92f68;
  background: #fff;
  border-radius: var(--r-sm);
  font-size: var(--fs-base);
  font-weight: 900;
  box-shadow: 0 16px 32px rgba(122, 14, 52, 0.2);
}
@media (max-width: 520px) {
  .promo-modal { padding: 36px 22px 24px; }
}
</style>

<template>
  <div class="page shop-page">
    
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="anim-spin"><Icon name="loader" :size="32" /></div>
      <p>正在加载套餐数据...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <Icon name="alert-circle" :size="32" class="text-danger" />
      <p>{{ error }}</p>
      <button class="btn btn--primary" @click="fetchPlans">重试</button>
    </div>

    <div v-else class="shop-layout">
      <!-- Main Content (Left) -->
      <div class="shop-main">
        
        <!-- Horizontal Plan List -->
        <div class="plan-scroller-wrap">
          <div class="plan-scroller" ref="scrollerRef">
            <div 
              v-for="plan in plans" 
              :key="plan.id" 
              class="plan-card" 
              :class="{ 'plan-card--active': selectedPlan?.id === plan.id }"
              @click="selectPlan(plan)"
            >
              <div class="plan-card__icon">
                <Icon name="diamond" :size="16" />
                <span>{{ getPlanCardTypeLabel(plan) }}</span>
              </div>
              <h3 class="plan-card__name">{{ plan.name }}</h3>
              <div class="plan-card__price">
                <span class="currency">¥</span>
                <span class="amount">{{ getMinPrice(plan) }}</span>
                <span class="period" v-if="getMinPeriodSuffix(plan)">{{ getMinPeriodSuffix(plan) }}</span>
              </div>
              <div class="plan-card__desc">{{ getPlanTrafficDescription(plan) }}</div>
            </div>
          </div>
          <div class="scroller-nav">
            <span class="nav-text">向右滑动查看更多>>></span>
            <div class="nav-arrows">
              <button class="nav-btn" @click="scrollPlan('left')"><Icon name="chevron-left" :size="18" /></button>
              <button class="nav-btn" @click="scrollPlan('right')"><Icon name="chevron-right" :size="18" /></button>
            </div>
          </div>
        </div>

        <div class="plan-details" v-if="selectedPlan">
          <!-- Features -->
          <div class="section-group">
            <div class="section-title">
              <div class="title-bar"></div>
              <h3>{{ selectedPlan.name }} - 套餐特点</h3>
            </div>
            <div class="feature-grid" v-if="parsedFeatures && parsedFeatures.length > 0">
              <div class="feature-item" v-for="(feat, idx) in parsedFeatures" :key="idx">
                <Icon v-if="feat.isNegative" name="x" :size="16" class="icon-x" style="color: #ef4444" />
                <Icon v-else name="check" :size="16" class="icon-check" style="color: #10b981" />
                <span :style="{ color: feat.isNegative ? '#94a3b8' : 'inherit' }">{{ feat.text }}</span>
              </div>
            </div>
            <div class="feature-grid" v-else-if="selectedPlan.content" v-html="selectedPlan.content"></div>
            <div class="feature-grid" v-else>
              <div class="feature-item">
                <Icon name="check" :size="16" class="icon-check" />
                <span>{{ getPlanTrafficFeature(selectedPlan, selectedPeriod) }}</span>
              </div>
              <div class="feature-item">
                <Icon name="check" :size="16" class="icon-check" />
                <span v-if="selectedPlan.device_limit">设备: {{ selectedPlan.device_limit }} 台设备同时在线</span>
                <span v-else>设备: 不限制在线设备数</span>
              </div>
              <div class="feature-item">
                <Icon name="check" :size="16" class="icon-check" />
                <span v-if="selectedPlan.speed_limit">带宽: 限速 {{ selectedPlan.speed_limit }}Mbps</span>
                <span v-else>带宽: 不限速</span>
              </div>
              <div class="feature-item">
                <Icon name="check" :size="16" class="icon-check" />
                <span>线路: 高速节点</span>
              </div>
            </div>
          </div>

          <!-- Billing Cycle -->
          <div class="section-group">
            <div class="section-title">
              <div class="title-bar"></div>
              <h3>套餐周期</h3>
            </div>
            <div v-if="availablePeriods.length > 0" class="period-grid">
              <div 
                v-for="period in availablePeriods" 
                :key="period.key"
                class="period-card"
                :class="{ 'period-card--active': selectedPeriod === period.key }"
                @click="selectPeriod(period.key)"
              >
                <div class="period-name">{{ period.name }}</div>
                <div class="period-price">¥{{ (period.price / 100).toFixed(2) }}</div>
              </div>
            </div>
            <div v-else class="empty-periods">当前套餐暂无可购买周期</div>
          </div>

          <!-- Payment Method -->
          <div class="section-group">
            <div class="section-title">
              <div class="title-bar"></div>
              <h3>支付方式</h3>
            </div>
            <div v-if="!needsPaymentMethod" class="payment-state payment-state--success">
              <Icon name="shield" :size="18" />
              <span>{{ canUseBalanceOnly ? '账户余额足够，本单将自动抵扣余额' : '当前订单无需选择支付方式' }}</span>
            </div>
            <div v-else-if="paymentLoading" class="payment-state">
              <span class="anim-spin">⟳</span>
              <span>正在加载支付方式...</span>
            </div>
            <div v-else-if="paymentMethods.length > 0" class="payment-grid">
              <div 
                v-for="method in paymentMethods" 
                :key="method.id"
                class="method-card"
                :class="{ 'method-card--active': selectedPaymentId === method.id }"
                @click="selectedPaymentId = method.id"
              >
                <div class="method-icon">
                  <Icon v-if="method.icon" :name="method.icon" :size="24" />
                  <!-- 支付宝/微信 图标判断 -->
                  <Icon v-else-if="method.name.includes('支付宝') || method.name.toLowerCase().includes('alipay')" name="check-circle" :size="20" class="icon-alipay" />
                  <Icon v-else-if="method.name.includes('微信') || method.name.toLowerCase().includes('wechat')" name="message-circle" :size="20" class="icon-wechat" />
                  <Icon v-else name="credit-card" :size="20" />
                </div>
                <span>{{ method.name }}</span>
              </div>
            </div>
            <div v-else class="payment-state payment-state--warning">
              <Icon name="alert-circle" :size="18" />
              <span>{{ paymentFetchError || '暂无可用支付方式，请联系管理员在后台启用支付接口' }}</span>
              <button class="btn-reload-payment" @click="fetchPaymentMethods" :disabled="paymentLoading">重新加载</button>
            </div>
          </div>

          <!-- Coupon -->
          <div class="section-group">
            <div class="section-title">
              <div class="title-bar"></div>
              <h3>优惠券</h3>
            </div>
            <div class="coupon-box">
              <div class="coupon-input-wrap">
                <span class="coupon-icon">🏷️</span>
                <input type="text" v-model="couponCode" placeholder="有优惠券？请在此输入，没有则忽略" />
                <button class="btn-verify" @click="handleCheckCoupon" :disabled="!couponCode || checkingCoupon">验证优惠券</button>
              </div>
            </div>
            <div v-if="couponSuccess" class="coupon-success-msg">折扣码已生效，将为您节省 ¥{{ (discountAmount / 100).toFixed(2) }}</div>
            <div class="telegram-hint">关注官方 Telegram 频道获取最新优惠信息</div>
          </div>

        </div>
      </div>

      <!-- Receipt Sidebar (Right) -->
      <div class="shop-sidebar">
        <div class="receipt-card">
          <div class="receipt-cutout receipt-cutout--left"></div>
          <div class="receipt-cutout receipt-cutout--right"></div>
          
          <div class="receipt-header">
            <Icon name="grid" :size="32" class="receipt-logo" />
            <h3>支付账单</h3>
          </div>
          
          <div class="receipt-body">
            <div class="receipt-row">
              <span class="label">套餐名称</span>
              <strong class="value">{{ selectedPlan?.name || '-' }}</strong>
            </div>
            <div class="receipt-row">
              <span class="label">套餐周期</span>
              <span class="pill pill--green">{{ selectedPeriodName || '-' }}</span>
            </div>
            <div class="receipt-row" v-if="needsPaymentMethod">
              <span class="label">支付方式</span>
              <span class="pill" :class="selectedPaymentName ? 'pill--green' : 'pill--muted'">
                {{ selectedPaymentName || (paymentLoading ? '加载中' : '未选择') }}
              </span>
            </div>
            <div class="receipt-row" v-else-if="canUseBalanceOnly">
              <span class="label">支付方式</span>
              <span class="pill pill--green">余额抵扣</span>
            </div>
            <div class="receipt-row">
              <span class="label">原价</span>
              <strong class="value">¥ {{ (originalPrice / 100).toFixed(2) }}</strong>
            </div>
            <div class="receipt-row" v-if="discountAmount > 0">
              <span class="label">优惠</span>
              <strong class="value text-success">- ¥ {{ (discountAmount / 100).toFixed(2) }}</strong>
            </div>
            
            <div class="receipt-total">
              <span class="label">实付金额</span>
              <div class="total-price">
                <span class="currency">¥</span>
                <span class="amount">{{ (finalPrice / 100).toFixed(2) }}</span>
              </div>
            </div>
          </div>
          
          <div class="receipt-footer">
            <button class="btn btn--primary btn-pay w-full" @click="handlePay" :disabled="payDisabled">
              <span v-if="purchasing" class="anim-spin" style="margin-right: 8px">⟳</span>
              {{ payButtonText }}
            </button>
            <div class="secure-pay">
              <Icon name="shield" :size="16" class="text-primary" />
              <span>安全支付</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { getPlans, saveOrder, checkCoupon, getPaymentMethod, checkout } from '@/api/user'
import { listFromPayload, objectFromPayload, unwrapPayload } from '@/api/normalizers'
import { useAppStore } from '@/stores/app'
import { useUserStore } from '@/stores/user'

const app = useAppStore()
const userStore = useUserStore()
const router = useRouter()
const route = useRoute()

const loading = ref(true)
const plans = ref([])
const error = ref(null)

const selectedPlan = ref(null)
const selectedPeriod = ref('')
const paymentMethods = ref([])
const selectedPaymentId = ref(null)
const paymentLoading = ref(false)
const paymentFetchError = ref('')

const purchasing = ref(false)

const couponCode = ref('')
const checkingCoupon = ref(false)
const couponSuccess = ref(false)
const couponData = ref(null)

const scrollerRef = ref(null)

function scrollPlan(direction) {
  if (!scrollerRef.value) return
  const amount = 260
  scrollerRef.value.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' })
}

const parsedFeatures = computed(() => {
  if (!selectedPlan.value?.content) return null;
  const content = selectedPlan.value.content;
  if (!content.includes('---')) return null;
  
  const parts = content.split('---').map(s => s.trim()).filter(Boolean);
  return parts.map(part => {
    const isNegative = /^(x|X|❌|✖️|✕|不支持)/i.test(part);
    // Remove only standard negative marks if they exist at the beginning, but keep emojis.
    let text = part.replace(/^(x|X|❌|✖️|✕)\s*/i, '').trim();
    return { text: text || part, isNegative };
  });
});

function getMinPeriodSuffix(plan) {
  const period = getPrimaryPeriod(plan)
  if (!period) return ''
  if (['monthly', 'month_price'].includes(period.key)) return '/ 月'
  if (['quarterly', 'quarter_price'].includes(period.key)) return '/ 季'
  if (['half_yearly', 'half_year_price'].includes(period.key)) return '/ 半年'
  if (['yearly', 'year_price'].includes(period.key)) return '/ 年'
  if (['two_yearly', 'two_year_price'].includes(period.key)) return '/ 两年'
  if (['three_yearly', 'three_year_price'].includes(period.key)) return '/ 三年'
  if (['onetime', 'onetime_price'].includes(period.key)) return '一次性'
  if (['reset_traffic', 'reset_price'].includes(period.key)) return '重置包'
  return ''
}

// 周期字典映射
const periodDict = {
  month_price: '月付',
  quarter_price: '季付',
  half_year_price: '半年付',
  year_price: '年付',
  two_year_price: '两年付',
  three_year_price: '三年付',
  onetime_price: '一次性',
  reset_price: '重置流量包',
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

async function fetchPlans() {
  loading.value = true
  error.value = null
  try {
    const res = await getPlans()
    const dataArray = listFromPayload(res)
    plans.value = dataArray.filter(p => p.show !== false && p.show !== 0 && p.sell !== false)
    
    // Auto select requested plan from recommendation, otherwise first plan.
    if (plans.value.length > 0) {
      const requestedPlanId = String(route.query.plan || '')
      const initialPlan = plans.value.find(plan => String(plan.id) === requestedPlanId) || plans.value[0]
      selectPlan(initialPlan)
    }
    
    // Fetch payment methods
    await fetchPaymentMethods()
  } catch (err) {
    error.value = err.message || '获取套餐失败，请稍后重试'
  } finally {
    loading.value = false
  }
}

async function fetchPaymentMethods() {
  paymentLoading.value = true
  paymentFetchError.value = ''
  try {
    const res = await getPaymentMethod()
    paymentMethods.value = listFromPayload(res)
    const selectedExists = paymentMethods.value.some(method => String(method.id) === String(selectedPaymentId.value))
    if (!selectedExists && paymentMethods.value.length > 0) {
      selectedPaymentId.value = paymentMethods.value[0].id
    } else if (!paymentMethods.value.length) {
      selectedPaymentId.value = null
    }
  } catch (err) {
    paymentMethods.value = []
    selectedPaymentId.value = null
    paymentFetchError.value = err.message || '支付方式加载失败，请稍后重试'
    console.warn('获取支付方式失败', err)
  } finally {
    paymentLoading.value = false
  }
}

function getAvailablePeriodsForPlan(plan) {
  const periods = []
  
  if (plan.prices && typeof plan.prices === 'object') {
    Object.entries(plan.prices).forEach(([key, price]) => {
      const normalizedPrice = normalizePrice(price)
      if (normalizedPrice !== null && periodKeys[key]) {
        periods.push({ key, name: periodKeys[key], price: normalizedPrice })
      }
    })
  } else {
    // 兼容 legacy v2board schema
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

function getPeriodType(periodKey) {
  if (['onetime', 'onetime_price'].includes(periodKey)) return 'onetime'
  if (['reset_traffic', 'reset_price'].includes(periodKey)) return 'reset'
  return 'periodic'
}

function getPlanCardTypeLabel(plan) {
  const period = getPrimaryPeriod(plan)
  const type = getPeriodType(period?.key || '')
  if (type === 'onetime') return '一次性流量'
  if (type === 'reset') return '流量重置包'
  return '周期订阅'
}

function getPlanTrafficDescription(plan) {
  const period = getPrimaryPeriod(plan)
  const type = getPeriodType(period?.key || '')
  const traffic = formatTraffic(plan.transfer_enable)
  if (type === 'onetime') return `不限时流量 : ${traffic}`
  if (type === 'reset') return `重置流量 : ${traffic}`
  return `每月流量 : ${traffic}`
}

function getPlanTrafficFeature(plan, periodKey) {
  const type = getPeriodType(periodKey)
  const traffic = formatTraffic(plan.transfer_enable)
  if (type === 'onetime') return `流量: 不限时 ${traffic} 流量，不自动重置`
  if (type === 'reset') return `流量: 购买后重置 ${traffic} 流量`
  return `流量: 每月 ${traffic} 流量, 每月重置`
}

function normalizePrice(price) {
  if (price === null || price === undefined || price === '') return null
  const normalized = Number(price)
  if (!Number.isFinite(normalized) || normalized < 0) return null
  return normalized
}

function selectPlan(plan) {
  selectedPlan.value = plan
  const periods = getAvailablePeriodsForPlan(plan)
  selectedPeriod.value = periods.length > 0 ? periods[0].key : ''
  couponCode.value = String(route.query.coupon || '').trim()
  couponSuccess.value = false
  couponData.value = null
  if (couponCode.value && selectedPeriod.value) {
    handleCheckCoupon()
  }
}

const availablePeriods = computed(() => {
  if (!selectedPlan.value) return []
  return getAvailablePeriodsForPlan(selectedPlan.value)
})

const selectedPeriodName = computed(() => {
  return availablePeriods.value.find(p => p.key === selectedPeriod.value)?.name || ''
})

const selectedPaymentName = computed(() => {
  return paymentMethods.value.find(p => p.id === selectedPaymentId.value)?.name || ''
})

const originalPrice = computed(() => {
  if (!selectedPeriod.value || !availablePeriods.value.length) return 0
  const period = availablePeriods.value.find(p => p.key === selectedPeriod.value)
  return period ? period.price : 0
})

const discountAmount = computed(() => {
  if (!couponSuccess.value || !couponData.value) return 0
  let amount = 0
  if (couponData.value.type === 1) { // Fixed
    amount = couponData.value.value
  } else if (couponData.value.type === 2) { // Percentage
    amount = Math.floor(originalPrice.value * (couponData.value.value / 100))
  }
  return Math.min(originalPrice.value, Math.max(0, Number(amount) || 0))
})

const finalPrice = computed(() => {
  return Math.max(0, originalPrice.value - discountAmount.value)
})

const userBalance = computed(() => Number(userStore.info?.balance || 0))

const canUseBalanceOnly = computed(() => {
  return finalPrice.value > 0 && userBalance.value >= finalPrice.value
})

const needsPaymentMethod = computed(() => {
  return finalPrice.value > 0 && !canUseBalanceOnly.value
})

const payDisabled = computed(() => {
  return purchasing.value || !selectedPeriod.value || (needsPaymentMethod.value && (paymentLoading.value || !selectedPaymentId.value))
})

const payButtonText = computed(() => {
  if (purchasing.value) return '处理中...'
  if (!selectedPeriod.value) return '请选择周期'
  if (!needsPaymentMethod.value) return '立即开通'
  if (paymentLoading.value) return '加载支付方式...'
  return '立即支付'
})

function getMinPrice(plan) {
  const period = getPrimaryPeriod(plan)
  if (!period) return '0.00'
  return (period.price / 100).toFixed(2)
}

function formatTraffic(kb) {
  if (!kb) return '0 GB'
  if (kb > 1000000) return (kb / 1024 / 1024 / 1024).toFixed(0) + ' GB'
  return kb + ' GB'
}

function selectPeriod(period) {
  if (selectedPeriod.value === period) return
  selectedPeriod.value = period
  couponSuccess.value = false
  couponData.value = null
  if (couponCode.value) {
    handleCheckCoupon()
  }
}

async function handleCheckCoupon() {
  if (!couponCode.value) return
  checkingCoupon.value = true
  try {
    const res = await checkCoupon({
      code: couponCode.value,
      plan_id: selectedPlan.value?.id,
      period: selectedPeriod.value
    })
    couponData.value = objectFromPayload(res)
    couponSuccess.value = true
    app.showToast('折扣码可正常使用', 'success')
  } catch (err) {
    couponSuccess.value = false
    couponData.value = null
    app.showToast(err.message || '无效的折扣码', 'error')
  } finally {
    checkingCoupon.value = false
  }
}

async function handlePay() {
  if (!selectedPeriod.value || !selectedPlan.value) {
    return app.showToast('请选择套餐周期', 'warning')
  }
  if (needsPaymentMethod.value && !selectedPaymentId.value) {
    if (!paymentMethods.value.length) {
      return app.showToast(paymentFetchError.value || '当前没有可用支付方式，请联系管理员', 'warning')
    }
    return app.showToast('请选择支付方式', 'warning')
  }
  
  purchasing.value = true
  try {
    // 1. 创建订单
    const orderRes = await saveOrder({
      plan_id: selectedPlan.value.id,
      period: selectedPeriod.value,
      coupon_code: couponSuccess.value ? couponCode.value : ''
    })
    
    const orderData = objectFromPayload(orderRes, orderRes)
    const tradeNo = typeof orderRes === 'string' ? orderRes : (orderData?.trade_no || orderData)
    
    if (!tradeNo) throw new Error('未获取到订单号')

    // 免费订单或余额足够时，后端会将订单金额处理为 0，直接触发开通。
    if (!needsPaymentMethod.value) {
      await checkout({ trade_no: tradeNo })
      app.showToast('下单成功，自动开通中...', 'success')
      router.push(`/order/${tradeNo}`)
      return
    }

    // 2. 发起支付
    const payRes = await checkout({
      trade_no: tradeNo,
      method: selectedPaymentId.value || ''
    })
    
    const payUrl = unwrapPayload(payRes)
    
    if (payUrl && typeof payUrl === 'string') {
      if (payUrl.startsWith('http') || payUrl.startsWith('alipay')) {
        app.showToast('正在跳转支付网关...', 'success')
        setTimeout(() => {
          window.location.href = payUrl
        }, 500)
      } else {
        app.showToast('请在新窗口完成支付', 'success')
        router.push(`/order/${tradeNo}`)
      }
    } else {
      app.showToast('请求支付网关成功，请核对账单状态', 'success')
      router.push(`/order/${tradeNo}`)
    }
  } catch (err) {
    app.showToast(err.message || '下单或支付失败', 'error')
    // 如果订单创建成功但支付失败，可以考虑引导用户去订单页
  } finally {
    purchasing.value = false
  }
}

onMounted(() => {
  userStore.fetchAll().catch(() => {})
  fetchPlans()
})

watch(() => route.query.coupon, (coupon) => {
  couponCode.value = String(coupon || '').trim()
  couponSuccess.value = false
  couponData.value = null
  if (couponCode.value && selectedPeriod.value) {
    handleCheckCoupon()
  }
})
</script>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  padding-bottom: 40px;
}

.shop-layout {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

@media (min-width: 1024px) {
  .shop-layout {
    flex-direction: row;
    align-items: flex-start;
  }
  .shop-main {
    flex: 1;
    min-width: 0;
  }
  .shop-sidebar {
    width: 320px;
    flex-shrink: 0;
    position: sticky;
    top: 24px;
  }
}

/* Plan Scroller */
.plan-scroller-wrap {
  margin-bottom: 32px;
}
.plan-scroller {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 16px;
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none;  /* Internet Explorer 10+ */
}
.plan-scroller::-webkit-scrollbar {
  display: none; /* Safari and Chrome */
}

.plan-card {
  flex: 0 0 240px;
  background: #fff;
  border: 2px solid transparent;
  border-radius: 12px;
  padding: 20px;
  cursor: pointer;
  box-shadow: 0 4px 20px rgba(0,0,0,0.03);
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}
.plan-card::after {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  pointer-events: none;
  transition: all 0.2s;
}
.plan-card:hover::after {
  border-color: #cbd5e1;
}
.plan-card--active {
  background: #f0fdf4; /* bg-green-50 */
}
.plan-card--active::after {
  border: 1.5px solid #10b981; /* primary green border */
}

.plan-card__icon {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #64748b;
  font-size: 0.8rem;
  margin-bottom: 16px;
}
.plan-card__name {
  font-size: 1.125rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 8px;
}
.plan-card__price {
  color: #0f172a;
  margin-bottom: 8px;
  display: flex;
  align-items:baseline;
}
.plan-card__price .currency { font-size: 0.9rem; margin-right: 2px; }
.plan-card__price .amount { font-size: 1.5rem; font-weight: 800; font-family: monospace;}
.plan-card__price .period { font-size: 0.85rem; color: #64748b; margin-left: 4px; }
.plan-card__desc {
  font-size: 0.85rem;
  color: #64748b;
}

.scroller-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 4px;
}
.nav-text {
  font-size: 0.8rem;
  color: #94a3b8;
}
.nav-arrows {
  display: flex;
  gap: 8px;
}
.nav-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 4px;
}
.nav-btn:hover { color: #1e293b; }

/* Plan Details Sections */
.plan-details {
  display: flex;
  flex-direction: column;
  gap: 32px;
}
.section-group {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
}
.title-bar {
  width: 4px;
  height: 18px;
  background: var(--c-primary, #10b981);
  border-radius: 2px;
}
.section-title h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
}

/* Features */
.feature-grid {
  background: #fafafa;
  border-radius: 8px;
  padding: 20px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
@media (min-width: 600px) {
  .feature-grid { grid-template-columns: 1fr 1fr; }
}
.feature-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: #475569;
}
.icon-check { color: #10b981; }

/* Period & Payment Grid */
.period-grid, .payment-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}
.period-card, .method-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 24px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.2s;
  color: #1e293b;
}
.period-card:hover, .method-card:hover {
  border-color: #cbd5e1;
}
.period-card--active, .method-card--active {
  background: #e6f6ef; /* very light green from screenshot */
  border-color: #79d6b5; /* green border */
  color: #1e293b;
  font-weight: 500;
}
.period-card { flex-direction: row; justify-content: space-between; }
.method-card { justify-content: center; font-weight: 500; }
.method-icon { display: flex; align-items: center; justify-content: center; color: #1e293b; }
.icon-alipay { color: #1e293b; }
.icon-wechat { color: #1e293b; }

.payment-state {
  min-height: 48px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #64748b;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
  font-size: 0.9rem;
}
.payment-state--success {
  color: #0f766e;
  background: #f0fdfa;
  border-color: rgba(20, 184, 166, 0.28);
}
.payment-state--warning {
  color: #b45309;
  background: #fffbeb;
  border-color: rgba(245, 158, 11, 0.3);
}
.btn-reload-payment {
  margin-left: auto;
  color: #0f766e;
  background: #fff;
  border: 1px solid rgba(15, 118, 110, 0.24);
  border-radius: 6px;
  padding: 6px 12px;
  font-weight: 600;
  cursor: pointer;
}
.btn-reload-payment:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.empty-periods {
  color: #94a3b8;
  font-size: 0.9rem;
}

/* Coupon Box */
.coupon-input-wrap {
  display: flex;
  align-items: center;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0 0 0 16px;
  overflow: hidden;
}
.coupon-icon { margin-right: 8px; font-size: 1.1rem; }
.coupon-input-wrap input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 0.95rem;
  color: #1e293b;
  min-width: 0;
  padding: 12px 0;
}
.coupon-input-wrap input::placeholder { color: #cbd5e1; }
.btn-verify {
  background: #fff;
  color: #1e293b;
  border: none;
  border-left: 1px solid #e2e8f0;
  padding: 12px 24px;
  border-radius: 0;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  height: 100%;
}
.btn-verify:hover:not(:disabled) {
  background: #f8fafc;
}
.btn-verify:disabled { opacity: 0.6; cursor: not-allowed; }
.coupon-success-msg { color: #10b981; font-size: 0.85rem; margin-top: -8px; }
.telegram-hint { text-align: right; color: #94a3b8; font-size: 0.8rem; margin-top: -4px;}

/* Receipt Sidebar */
.receipt-card {
  background: #f8fafc; /* slightly off-white like a ticket */
  border-radius: 16px;
  padding: 32px 24px 24px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.06);
  position: relative;
}
/* Ticket cutouts */
.receipt-cutout {
  position: absolute;
  top: 90px;
  width: 24px;
  height: 24px;
  background: #fff; /* matching page bg */
  border-radius: 50%;
  box-shadow: inset 0 0 10px rgba(0,0,0,0.02);
}
.receipt-cutout--left { left: -12px; }
.receipt-cutout--right { right: -12px; }

.receipt-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  margin-bottom: 32px;
  border-bottom: 2px dashed #e2e8f0;
  padding-bottom: 32px;
}
.receipt-logo { color: #1e293b; }
.receipt-header h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 800;
  color: #1e293b;
}

.receipt-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 32px;
}
.receipt-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.95rem;
}
.receipt-row .label { color: #64748b; }
.receipt-row .value { color: #1e293b; font-weight: 600; font-family: monospace; font-size: 1rem;}
.pill {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
}
.pill--green {
  color: #10b981;
  border: 1px solid #10b981;
  background: #f0fdf4;
}
.pill--muted {
  color: #64748b;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
}
.text-success { color: #10b981 !important; }

.receipt-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}
.receipt-total .label {
  font-size: 1rem;
  color: #475569;
  font-weight: 600;
}
.receipt-total .total-price {
  font-size: 2rem;
  font-weight: 800;
  color: #1e293b;
  display: flex;
  align-items: baseline;
}
.total-price .currency { font-size: 1.2rem; margin-right: 4px; }

.receipt-footer {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}
.btn-pay {
  background: var(--c-primary, #10b981);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 14px 24px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  justify-content: center;
  align-items: center;
}
.btn-pay:hover:not(:disabled) {
  filter: brightness(1.05);
  transform: translateY(-1px);
}
.btn-pay:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.secure-pay {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  color: #10b981;
  font-weight: 500;
}

/* Animations */
.anim-spin {
  animation: spin 1s linear infinite;
  display: inline-block;
}
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

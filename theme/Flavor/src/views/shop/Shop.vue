<template>
  <div class="page shop-page">
    <div class="shop-hero anim-fade-up">
      <div>
        <span class="shop-hero__eyebrow">Pricing</span>
        <h2 class="shop-hero__title">购买套餐</h2>
        <p class="shop-hero__desc">选择适合当前使用场景的订阅周期</p>
      </div>
      <div class="shop-hero__badges">
        <span>高速节点</span>
        <span>全终端支持</span>
        <span>流媒体解锁</span>
        <span>隧道加密</span>
      </div>
    </div>

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

    <!-- Plans Grid -->
    <div v-else class="plans-grid">
      <div v-for="plan in plans" :key="plan.id" class="plan-card" :class="{ 'plan-card--popular': plan.sort > 5 }">
        <div v-if="plan.sort > 5" class="plan-card__badge">热门推荐</div>
        
        <div class="plan-card__header">
          <h3 class="plan-card__name">{{ plan.name }}</h3>
          <div class="plan-card__price">
            <span class="currency">¥</span>
            <span class="amount">{{ getMinPrice(plan) }}</span>
            <span class="period">起</span>
          </div>
        </div>

        <div class="plan-card__features">
          <div class="plan-card__feature-title">套餐特点</div>
          <div class="feature-item">
            <Icon name="database" :size="16" class="text-primary" />
            <span v-if="hasRecurring(plan)">每个月 <strong>{{ formatTraffic(plan.transfer_enable) }}</strong> 流量</span>
            <span v-else>包含 <strong>{{ formatTraffic(plan.transfer_enable) }}</strong> 流量</span>
          </div>
          <div v-if="plan.speed_limit" class="feature-item">
            <Icon name="activity" :size="16" class="text-primary" />
            <span>最高 <strong>{{ plan.speed_limit }}</strong> Mbps 速率</span>
          </div>
          <div v-else class="feature-item">
            <Icon name="activity" :size="16" class="text-primary" />
            <span>无速率限制</span>
          </div>
          <div v-if="plan.device_limit" class="feature-item">
            <Icon name="monitor" :size="16" class="text-primary" />
            <span>最多支持 <strong>{{ plan.device_limit }}</strong> 台设备</span>
          </div>
          
          <div v-if="plan.content" class="plan-content" v-html="plan.content"></div>
        </div>

        <div class="plan-card__footer">
          <button
            class="btn btn--primary btn--lg w-full"
            @click="openPurchaseModal(plan)"
            :disabled="getAvailablePeriodsForPlan(plan).length === 0"
          >
            {{ getAvailablePeriodsForPlan(plan).length > 0 ? '立即购买' : '暂不可购买' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Purchase Modal -->
    <div v-if="isModalOpen" class="modal-overlay" @click.self="isModalOpen = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>订阅确认</h3>
          <button class="close-btn" @click="isModalOpen = false">
            <Icon name="x" :size="20" />
          </button>
        </div>
        
        <div class="modal-body" v-if="selectedPlan">
          <div class="selected-plan-info">
            <h4>{{ selectedPlan.name }}</h4>
            <p>{{ formatTraffic(selectedPlan.transfer_enable) }} 流量套餐</p>
          </div>

          <div class="period-selection">
            <label class="form-label">选择计费周期</label>
            <div v-if="availablePeriods.length > 0" class="period-grid">
              <div 
                v-for="period in availablePeriods" 
                :key="period.key"
                class="period-card"
                :class="{ 'period-card--active': selectedPeriod === period.key }"
                @click="selectedPeriod = period.key"
              >
                <div class="period-name">{{ period.name }}</div>
                <div class="period-price">¥{{ (period.price / 100).toFixed(2) }}</div>
              </div>
            </div>
            <div v-else class="empty-periods">当前套餐暂无可购买周期</div>
          </div>

          <div class="coupon-section mt-4">
            <label class="form-label">使用折扣码 (可选)</label>
            <div class="coupon-input">
              <input type="text" v-model="couponCode" class="form-input" placeholder="输入折扣码">
              <button class="btn btn--secondary btn--sm" @click="handleCheckCoupon" :disabled="!couponCode || checkingCoupon">验证</button>
            </div>
            <div v-if="couponSuccess" class="text-success text-xs mt-1">折扣码生效！将节省 ¥{{ (discountAmount / 100).toFixed(2) }}</div>
            <div class="price-summary mt-3 text-right">
               <div class="text-2 text-sm line-through" v-if="discountAmount > 0">原价: ¥{{ (originalPrice / 100).toFixed(2) }}</div>
               <div class="text-primary font-bold text-xl">实付金额: ¥{{ (finalPrice / 100).toFixed(2) }}</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn--secondary" @click="isModalOpen = false">取消</button>
          <button class="btn btn--primary" @click="handlePurchase" :disabled="!selectedPeriod || purchasing">
            <span v-if="purchasing" class="anim-spin" style="margin-right: 8px">⟳</span>
            确认下单
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { getPlans, saveOrder, checkCoupon } from '@/api/user'
import { listFromPayload, objectFromPayload } from '@/api/normalizers'
import { useAppStore } from '@/stores/app'

const app = useAppStore()
const router = useRouter()

const loading = ref(true)
const plans = ref([])
const error = ref(null)

const isModalOpen = ref(false)
const selectedPlan = ref(null)
const selectedPeriod = ref('')
const purchasing = ref(false)

const couponCode = ref('')
const checkingCoupon = ref(false)
const couponSuccess = ref(false)
const couponData = ref(null)

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

// 现代 Xboard API 的周期映射 (如果 API 返回的是 prices 数组)
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
    plans.value = dataArray.filter(p => p.show !== 0)
  } catch (err) {
    error.value = err.message || '获取套餐失败，请稍后重试'
  } finally {
    loading.value = false
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

function normalizePrice(price) {
  if (price === null || price === undefined || price === '') return null
  const normalized = Number(price)
  if (!Number.isFinite(normalized) || normalized < 0) return null
  return normalized
}

const availablePeriods = computed(() => {
  if (!selectedPlan.value) return []
  return getAvailablePeriodsForPlan(selectedPlan.value)
})

const originalPrice = computed(() => {
  if (!selectedPeriod.value || !availablePeriods.value.length) return 0
  const period = availablePeriods.value.find(p => p.key === selectedPeriod.value)
  return period ? period.price : 0
})

const discountAmount = computed(() => {
  if (!couponSuccess.value || !couponData.value) return 0
  if (couponData.value.type === 1) { // Fixed Absolute Amount (in cents or yuan depending on backend)
    // Assume value is standard cents if prices are cents
    return couponData.value.value
  } else if (couponData.value.type === 2) { // Percentage
    // example: value = 20 means 20% OFF or 20% price?. Xboard percent value usually means reduction percentage. (i.e., value 10 = 10% discount).
    return Math.floor(originalPrice.value * (couponData.value.value / 100))
  }
  return 0
})

const finalPrice = computed(() => {
  return Math.max(0, originalPrice.value - discountAmount.value)
})

function getMinPrice(plan) {
  const periods = getAvailablePeriodsForPlan(plan)
  if (periods.length === 0) return '0.00'
  const min = Math.min(...periods.map(p => p.price))
  return (min / 100).toFixed(2)
}

function hasRecurring(plan) {
  const periods = getAvailablePeriodsForPlan(plan)
  return periods.some(p => !['onetime_price', 'onetime', 'reset_price', 'reset_traffic'].includes(p.key))
}

function formatTraffic(kb) {
  if (!kb) return '0 GB'
  if (kb > 1000000) return (kb / 1024 / 1024 / 1024).toFixed(0) + ' GB'
  return kb + ' GB'
}

function openPurchaseModal(plan) {
  selectedPlan.value = plan
  const periods = getAvailablePeriodsForPlan(plan)
  selectedPeriod.value = periods.length > 0 ? periods[0].key : ''
  couponCode.value = ''
  couponSuccess.value = false
  couponData.value = null
  isModalOpen.value = true
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

async function handlePurchase() {
  if (!selectedPeriod.value || !selectedPlan.value) return
  
  purchasing.value = true
  try {
    const res = await saveOrder({
      plan_id: selectedPlan.value.id,
      period: selectedPeriod.value,
      coupon_code: couponSuccess.value ? couponCode.value : ''
    })
    
    // 如果返回的是直接 trade_no
    const orderData = objectFromPayload(res, res)
    const tradeNo = typeof res === 'string' ? res : (orderData?.trade_no || orderData)
    if (tradeNo) {
      app.showToast('下单成功', 'success')
      isModalOpen.value = false
      router.push(`/order/${tradeNo}`)
    } else {
      throw new Error('未获取到订单号')
    }
  } catch (err) {
    app.showToast(err.message || '下单失败', 'error')
  } finally {
    purchasing.value = false
  }
}

onMounted(() => {
  fetchPlans()
})
</script>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.shop-hero {
  position: relative;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  padding: 30px;
  color: #fff;
  background: var(--gradient-hero);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-float);
  overflow: hidden;
}
.shop-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    linear-gradient(115deg, rgba(255,255,255,0.18), transparent 34%),
    repeating-linear-gradient(90deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 96px);
  pointer-events: none;
}
.shop-hero > * { position: relative; z-index: 1; }
.shop-hero__eyebrow {
  display: inline-flex;
  margin-bottom: 8px;
  color: rgba(255,255,255,0.72);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
}
.shop-hero__title {
  margin: 0;
  font-size: clamp(1.75rem, 3vw, 2.5rem);
  line-height: 1.1;
  letter-spacing: 0;
}
.shop-hero__desc {
  margin-top: 8px;
  color: rgba(255,255,255,0.78);
  font-size: var(--fs-sm);
}
.shop-hero__badges {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  max-width: 460px;
}
.shop-hero__badges span {
  padding: 7px 10px;
  background: rgba(255,255,255,0.14);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: var(--r-full);
  color: rgba(255,255,255,0.9);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
}

.page-title {
  font-size: var(--fs-2xl);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
  margin-bottom: 4px;
}
.page-desc {
  color: var(--c-text-3);
  font-size: var(--fs-md);
}

.loading-state, .error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background: var(--gradient-panel);
  border-radius: var(--r-lg);
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-card);
  gap: 16px;
  color: var(--c-text-2);
}

/* Plans Grid */
.plans-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

.plan-card {
  position: relative;
  background: var(--gradient-panel);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  padding: 26px 22px;
  display: flex;
  flex-direction: column;
  transition: transform var(--t-base), box-shadow var(--t-base), border-color var(--t-base);
  box-shadow: var(--shadow-card);
  overflow: hidden;
  backdrop-filter: blur(16px);
}
.plan-card::before {
  content: "";
  position: absolute;
  inset: 0 0 auto;
  height: 4px;
  background: var(--gradient-accent);
}
.plan-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: var(--c-border-light);
}

.plan-card--popular {
  border-color: color-mix(in srgb, var(--c-primary) 48%, var(--c-border));
  box-shadow: var(--shadow-float);
}
.plan-card--popular::before { background: var(--gradient-primary); }

.plan-card__badge {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 1;
  background: var(--gradient-primary);
  color: #fff;
  padding: 4px 10px;
  border-radius: var(--r-full);
  font-size: 12px;
  font-weight: var(--fw-bold);
  box-shadow: var(--shadow-glow);
}

.plan-card__header {
  text-align: left;
  margin-bottom: 22px;
  padding-bottom: 22px;
  border-bottom: 1px dashed var(--c-border);
}

.plan-card__name {
  padding-right: 88px;
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
  margin-bottom: 14px;
}

.plan-card__price {
  display: flex;
  align-items: baseline;
  justify-content: flex-start;
  gap: 4px;
  color: var(--c-primary);
}
.plan-card__price .currency {
  font-size: var(--fs-lg);
  font-weight: var(--fw-semibold);
}
.plan-card__price .amount {
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1;
}
.plan-card__price .period {
  font-size: var(--fs-sm);
  color: var(--c-text-3);
  font-weight: var(--fw-medium);
}

.plan-card__features {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 13px;
  margin-bottom: 28px;
}
.plan-card__feature-title {
  color: var(--c-text-1);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
}
.feature-item {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--c-text-2);
  font-size: var(--fs-sm);
}
.feature-item svg { flex-shrink: 0; }
.feature-item strong {
  color: var(--c-text-1);
  font-weight: var(--fw-semibold);
}

.plan-content {
  margin-top: 10px;
  padding: 14px;
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
  font-size: var(--fs-sm);
  color: var(--c-text-3);
  line-height: 1.6;
  white-space: pre-wrap;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-content {
  background: var(--gradient-panel);
  border-radius: var(--r-xl);
  width: 100%;
  max-width: 520px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--c-border);
  overflow: hidden;
  animation: modalIn 0.3s ease;
  backdrop-filter: blur(18px);
}
@keyframes modalIn {
  from { opacity: 0; transform: translateY(20px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px;
  border-bottom: 1px solid var(--c-border);
}
.modal-header h3 {
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
}
.close-btn {
  color: var(--c-text-3);
  transition: color var(--t-fast);
  padding: 4px;
}
.close-btn:hover {
  color: var(--c-text-1);
}

.modal-body {
  padding: 24px;
}

.selected-plan-info {
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  padding: 16px;
  border-radius: var(--r-md);
  margin-bottom: 24px;
  text-align: center;
}
.selected-plan-info h4 {
  font-size: var(--fs-lg);
  color: var(--c-primary);
  margin-bottom: 4px;
}
.selected-plan-info p {
  color: var(--c-text-2);
  font-size: var(--fs-sm);
}

.period-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-top: 8px;
}
.period-card {
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
  padding: 16px;
  text-align: center;
  cursor: pointer;
  transition: all var(--t-fast);
  background: var(--c-bg-input);
}
.period-card:hover {
  border-color: var(--c-primary-light);
  background: var(--c-bg-hover);
}
.period-card--active {
  border-color: var(--c-primary);
  background: var(--c-primary-light);
  box-shadow: inset 0 0 0 1px var(--c-primary-light);
}
.empty-periods {
  color: var(--c-text-3);
  font-size: var(--fs-sm);
  padding: 16px;
  text-align: center;
  background: var(--c-bg-elevated);
  border: 1px dashed var(--c-border);
  border-radius: var(--r-sm);
  margin-top: 8px;
}
.period-name {
  font-size: var(--fs-md);
  color: var(--c-text-1);
  font-weight: var(--fw-medium);
  margin-bottom: 4px;
}
.period-price {
  font-size: var(--fs-lg);
  color: var(--c-primary);
  font-weight: var(--fw-bold);
}

.modal-footer {
  padding: 20px 24px;
  border-top: 1px solid var(--c-border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: var(--c-bg-elevated);
}

/* Button overrides */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 10px 20px;
  border-radius: var(--r-sm);
  font-weight: var(--fw-medium);
  transition: all var(--t-fast);
  cursor: pointer;
}
.btn--lg {
  height: 48px;
  font-size: var(--fs-md);
  border-radius: var(--r-md);
}
.btn--primary {
  background: var(--gradient-primary);
  color: #fff;
  border: none;
  box-shadow: var(--shadow-glow);
}
.btn--primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: var(--shadow-float);
}
.btn--primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.btn--secondary {
  background: transparent;
  color: var(--c-text-2);
  border: 1px solid var(--c-border);
}
.btn--secondary:hover {
  background: var(--c-bg-hover);
  color: var(--c-text-1);
}

/* Coupon Styles */
.coupon-section {
  padding-top: 16px;
  border-top: 1px dashed var(--c-border);
}
.coupon-input {
  display: flex; gap: 8px; margin-top: 8px;
}
.coupon-input .form-input { flex: 1; padding: 10px 12px; font-size: var(--fs-md); }
.line-through { text-decoration: line-through; }
.text-2 { color: var(--c-text-2); }
.text-xs { font-size: var(--fs-xs); }
.text-sm { font-size: var(--fs-sm); }
.text-xl { font-size: var(--fs-xl); }
.text-right { text-align: right; }
.text-success { color: var(--c-success); }
.font-bold { font-weight: var(--fw-bold); }
.mt-1 { margin-top: 4px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }

@media (max-width: 768px) {
  .shop-hero { align-items: flex-start; flex-direction: column; padding: 24px; }
  .shop-hero__badges { justify-content: flex-start; }
  .plans-grid { grid-template-columns: 1fr; }
  .period-grid { grid-template-columns: 1fr; }
  .coupon-input { flex-direction: column; }
}
</style>

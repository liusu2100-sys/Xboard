<template>
  <div class="page order-detail-page">
    <div class="page-header">
      <button class="btn-back" @click="router.back()">
        <Icon name="arrow-left" :size="20" />
        返回
      </button>
      <h2 class="page-title">订单详情</h2>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="anim-spin"><Icon name="loader" :size="32" /></div>
      <p>正在获取订单信息...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <Icon name="alert-circle" :size="32" class="text-danger" />
      <p>{{ error }}</p>
      <button class="btn btn--primary" @click="fetchDetail">重试</button>
    </div>

    <div v-else-if="order" class="order-content">
      <div class="order-grid">
        <!-- Left: Order Info -->
        <div class="card order-info">
          <div class="card-header">
            <h3>单号 {{ order.trade_no }}</h3>
            <span class="order-status" :class="getStatusClass(order.status)">
              {{ getStatusText(order.status) }}
            </span>
          </div>

          <div class="info-list">
            <div class="info-row">
              <span class="label">套餐名称</span>
              <span class="value">{{ order.plan ? order.plan.name : '未知套餐' }}</span>
            </div>
            <div class="info-row">
              <span class="label">订阅周期</span>
              <span class="value">{{ getPeriodName(order.period) }}</span>
            </div>
            <div class="info-row">
              <span class="label">订单金额</span>
              <span class="value price">¥{{ (order.total_amount / 100).toFixed(2) }}</span>
            </div>
            <div class="info-row">
              <span class="label">下单时间</span>
              <span class="value">{{ formatDate(order.created_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Right: Checkout / Actions -->
        <div class="card checkout-section" v-if="order.status === 0">
          <h3>支付订单</h3>
          
          <div v-if="isFreeOrder" class="free-order-note">
            该订单无需支付，确认后将自动开通套餐。
          </div>

          <div class="payment-methods" v-else-if="paymentMethods.length > 0">
            <label class="form-label">选择支付方式</label>
            <div class="method-grid">
              <div 
                v-for="method in paymentMethods" 
                :key="method.id"
                class="method-card"
                :class="{ 'method-card--active': selectedPaymentId === method.id }"
                @click="selectedPaymentId = method.id"
              >
                <div class="method-icon">
                  <Icon v-if="method.icon" :name="method.icon" :size="24" />
                  <Icon v-else name="credit-card" :size="24" />
                </div>
                <span>{{ method.name }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-payment">
            该站点尚未配置任何支付方式。
          </div>

          <!-- Coupon (Future addition here) -->

          <div class="checkout-footer">
            <div class="checkout-total">
              实付: <span class="total-price">¥{{ (order.total_amount / 100).toFixed(2) }}</span>
            </div>
            <button 
              class="btn btn--primary btn--lg w-full" 
              @click="handleCheckout"
              :disabled="(!isFreeOrder && !selectedPaymentId) || processing"
            >
              <span v-if="processing" class="anim-spin" style="margin-right: 8px">⟳</span>
              {{ isFreeOrder ? '立即开通' : '立即支付' }}
            </button>
            <button class="btn btn--secondary btn--lg w-full mt-2" @click="handleCancel" :disabled="processing">
              取消订单
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { getOrderDetail, getPaymentMethod, checkout, cancelOrder } from '@/api/user'
import { listFromPayload, objectFromPayload, unwrapPayload } from '@/api/normalizers'
import { useAppStore } from '@/stores/app'

const route = useRoute()
const router = useRouter()
const app = useAppStore()

const tradeNo = route.params.tradeNo
const loading = ref(true)
const error = ref(null)
const order = ref(null)

const paymentMethods = ref([])
const selectedPaymentId = ref(null)
const processing = ref(false)

const isFreeOrder = computed(() => Number(order.value?.total_amount || 0) <= 0)

const periodDict = {
  month_price: '月付', monthly: '月付',
  quarter_price: '季付', quarterly: '季付',
  half_year_price: '半年付', half_yearly: '半年付',
  year_price: '年付', yearly: '年付',
  two_year_price: '两年付', two_yearly: '两年付',
  three_year_price: '三年付', three_yearly: '三年付',
  onetime_price: '一次性', onetime: '一次性',
  reset_price: '重置流量包', reset_traffic: '流量包'
}

async function fetchDetail() {
  loading.value = true
  error.value = null
  try {
    const res = await getOrderDetail(tradeNo)
    order.value = objectFromPayload(res, null)

    // 如果状态为0(待支付)，获取支付方式
    if (order.value.status === 0 && !isFreeOrder.value) {
      await fetchPaymentMethods()
    }
  } catch (err) {
    error.value = err.message || '无法获取订单详情'
  } finally {
    loading.value = false
  }
}

async function fetchPaymentMethods() {
  try {
    const res = await getPaymentMethod()
    paymentMethods.value = listFromPayload(res)
    if (paymentMethods.value.length > 0) {
      selectedPaymentId.value = paymentMethods.value[0].id
    }
  } catch (err) {
    console.warn('获取支付方式失败', err)
  }
}

async function handleCheckout() {
  if (!isFreeOrder.value && !selectedPaymentId.value) return
  processing.value = true
  try {
    const res = await checkout({
      trade_no: tradeNo,
      method: selectedPaymentId.value || ''
    })
    
    // Xboard 返回通常为直接付款URL或布尔值
    const payUrl = unwrapPayload(res)
    
    if (payUrl && typeof payUrl === 'string') {
      if (payUrl.startsWith('http') || payUrl.startsWith('alipay')) {
        window.location.href = payUrl
      } else {
        // 如果是 HTML 或其他结构，可以在这里优化
        app.showToast('请在新窗口完成支付', 'success')
      }
    } else {
      app.showToast('请求支付网关成功，请核对账单状态', 'success')
      fetchDetail()
    }
  } catch (err) {
    app.showToast(err.message || '发起支付失败', 'error')
  } finally {
    processing.value = false
  }
}

async function handleCancel() {
  if (!confirm('确定要取消此订单吗？')) return
  processing.value = true
  try {
    await cancelOrder({ trade_no: tradeNo })
    app.showToast('订单已取消', 'success')
    fetchDetail()
  } catch (err) {
    app.showToast(err.message || '取消订单失败', 'error')
  } finally {
    processing.value = false
  }
}

function getStatusText(status) {
  switch (status) {
    case 0: return '待支付'
    case 1: return '开通中'
    case 2: return '已取消'
    case 3: return '已完成'
    case 4: return '已折算'
    default: return '未知'
  }
}

function getStatusClass(status) {
  switch (status) {
    case 0: return 'status--pending'
    case 1: return 'status--processing'
    case 2: return 'status--cancelled'
    case 3: return 'status--completed'
    case 4: return 'status--converted'
    default: return 'status--unknown'
  }
}

function getPeriodName(period) {
  return periodDict[period] || period || '默认'
}

function formatDate(timestamp) {
  if (!timestamp) return '-'
  const date = new Date(timestamp * 1000)
  return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

onMounted(() => {
  fetchDetail()
})
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 24px; padding-bottom: 40px; }

.page-header { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; margin-bottom: 8px; }
.btn-back { display: inline-flex; align-items: center; gap: 6px; color: var(--c-text-2); font-weight: var(--fw-medium); transition: color var(--t-fast); }
.btn-back:hover { color: var(--c-text-1); }
.page-title { font-size: var(--fs-2xl); font-weight: var(--fw-bold); color: var(--c-text-1); }

.loading-state, .error-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 60px 20px; background: var(--c-bg-card); border-radius: var(--r-lg);
  border: 1px solid var(--c-border); gap: 12px; color: var(--c-text-2);
}

.order-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
@media (min-width: 900px) {
  .order-grid { grid-template-columns: 3fr 2fr; align-items: start; }
}

.card {
  background: var(--c-bg-card); border: 1px solid var(--c-border);
  border-radius: var(--r-xl); padding: 24px; box-shadow: var(--shadow-sm);
}

/* Order Info */
.order-info .card-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px dashed var(--c-border);
}
.order-info h3 { font-family: monospace; font-size: var(--fs-lg); color: var(--c-text-1); }

.order-status {
  font-size: 13px; font-weight: var(--fw-semibold); padding: 5px 12px; border-radius: var(--r-full);
}
.status--pending { background: rgba(245, 158, 11, 0.1); color: var(--c-warning); border: 1px solid rgba(245, 158, 11, 0.2); }
.status--completed { background: rgba(16, 185, 129, 0.1); color: var(--c-success); border: 1px solid rgba(16, 185, 129, 0.2); }
.status--cancelled { background: var(--c-bg-hover); color: var(--c-text-3); border: 1px solid var(--c-border); }
.status--processing { background: rgba(59, 130, 246, 0.1); color: var(--c-info); border: 1px solid rgba(59, 130, 246, 0.2); }

.info-list { display: flex; flex-direction: column; gap: 16px; }
.info-row { display: flex; justify-content: space-between; align-items: center; }
.info-row .label { color: var(--c-text-2); font-size: var(--fs-md); }
.info-row .value { color: var(--c-text-1); font-weight: var(--fw-medium); font-size: var(--fs-md); }
.info-row .price { color: var(--c-primary); font-weight: var(--fw-bold); font-size: var(--fs-lg); }

/* Checkout Section */
.checkout-section h3 { font-size: var(--fs-xl); margin-bottom: 24px; color: var(--c-text-1); }

.form-label { display: block; font-size: var(--fs-sm); color: var(--c-text-2); margin-bottom: 12px; }

.method-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 24px; }
.method-card {
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
  border: 1px solid var(--c-border); border-radius: var(--r-sm); padding: 16px;
  cursor: pointer; transition: all var(--t-fast); background: var(--c-bg-input);
}
.method-card:hover { border-color: var(--c-primary-light); background: var(--c-bg-hover); }
.method-card--active { border-color: var(--c-primary); background: var(--c-primary-light); color: var(--c-primary); }
.method-icon { color: inherit; }
.empty-payment, .free-order-note { color: var(--c-text-3); font-size: var(--fs-sm); padding: 20px; text-align: center; background: var(--c-bg-elevated); border-radius: var(--r-sm); margin-bottom: 24px; }
.free-order-note { color: var(--c-success); border: 1px solid color-mix(in srgb, var(--c-success) 24%, transparent); }

.checkout-footer { background: var(--c-bg-elevated); padding: 20px; border-radius: var(--r-md); margin-top: auto; }
.checkout-total { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; color: var(--c-text-2); }
.total-price { font-size: var(--fs-2xl); font-weight: var(--fw-bold); color: var(--c-text-1); }

.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 10px 20px; border-radius: var(--r-sm); font-weight: var(--fw-medium);
  transition: all var(--t-fast); cursor: pointer; text-decoration: none; border: none; font-size: var(--fs-md);
}
.btn--lg { height: 48px; border-radius: var(--r-md); }
.w-full { width: 100%; }
.mt-2 { margin-top: 12px; }

.btn--primary {
  background: linear-gradient(135deg, var(--c-primary), var(--c-primary-hover));
  color: #fff; box-shadow: 0 2px 12px var(--c-primary-glow);
}
.btn--primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 16px var(--c-primary-glow); }
.btn--primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.btn--secondary { background: transparent; color: var(--c-text-2); border: 2px solid var(--c-border); }
.btn--secondary:hover:not(:disabled) { background: var(--c-bg-hover); color: var(--c-text-1); }
</style>

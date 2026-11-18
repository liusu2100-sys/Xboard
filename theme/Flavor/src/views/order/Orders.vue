<template>
  <div class="page orders-page">
    <div class="page-header">
      <h2 class="page-title">我的订单</h2>
      <p class="page-desc">查看和管理您的订阅订单和账单记录</p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="anim-spin"><Icon name="loader" :size="32" /></div>
      <p>正在加载订单数据...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!orders.length" class="empty-state">
      <Icon name="inbox" :size="48" class="text-3" />
      <p>暂无订单记录</p>
      <router-link to="/shop" class="btn btn--primary" style="margin-top: 16px">前往商店</router-link>
    </div>

    <!-- Orders List -->
    <div v-else class="orders-list">
      <div v-for="order in orders" :key="order.trade_no" class="order-card" @click="goToDetail(order.trade_no)">
        <div class="order-card__header">
          <div class="order-no">订单号: {{ order.trade_no }}</div>
          <div class="order-status" :class="getStatusClass(order.status)">
            {{ getStatusText(order.status) }}
          </div>
        </div>
        
        <div class="order-card__body">
          <div class="order-plan-name">{{ order.plan ? order.plan.name : '未知套餐' }} - {{ getPeriodName(order.period) }}</div>
          <div class="order-price">¥{{ (order.total_amount / 100).toFixed(2) }}</div>
        </div>

        <div class="order-card__footer">
          <div class="order-date">创建时间: {{ formatDate(order.created_at) }}</div>
          <div class="order-action">
            <button v-if="order.status === 0" class="btn btn--primary btn--sm" @click.stop="goToDetail(order.trade_no)">
              去支付
            </button>
            <button v-else class="btn btn--secondary btn--sm">查看详情</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { getOrders } from '@/api/user'
import { listFromPayload } from '@/api/normalizers'

const router = useRouter()
const loading = ref(true)
const orders = ref([])

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

async function fetchOrders() {
  loading.value = true
  try {
    const res = await getOrders()
    orders.value = listFromPayload(res)
  } catch (err) {
    console.error('获取订单失败', err)
  } finally {
    loading.value = false
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

function goToDetail(tradeNo) {
  router.push(`/order/${tradeNo}`)
}

onMounted(() => {
  fetchOrders()
})
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 24px; }
.page-header { margin-bottom: 8px; }
.page-title { font-size: var(--fs-2xl); font-weight: var(--fw-bold); color: var(--c-text-1); margin-bottom: 4px; }
.page-desc { color: var(--c-text-3); font-size: var(--fs-md); }

.loading-state, .empty-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 60px 20px; background: var(--c-bg-card); border-radius: var(--r-lg);
  border: 1px solid var(--c-border); gap: 12px; color: var(--c-text-2);
}

.orders-list { display: flex; flex-direction: column; gap: 16px; }

.order-card {
  background: var(--c-bg-card);
  border: 1px solid var(--c-border);
  border-radius: var(--r-md);
  padding: 20px 24px;
  cursor: pointer;
  transition: all var(--t-fast);
  box-shadow: var(--shadow-sm);
}
.order-card:hover {
  border-color: var(--c-primary-light);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.order-card__header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px dashed var(--c-border);
}
.order-no { font-family: monospace; color: var(--c-text-3); font-size: var(--fs-sm); }
.order-status {
  font-size: 12px; font-weight: var(--fw-semibold); padding: 4px 10px; border-radius: var(--r-full);
}
.status--pending { background: rgba(245, 158, 11, 0.1); color: var(--c-warning); border: 1px solid rgba(245, 158, 11, 0.2); }
.status--completed { background: rgba(16, 185, 129, 0.1); color: var(--c-success); border: 1px solid rgba(16, 185, 129, 0.2); }
.status--cancelled { background: var(--c-bg-hover); color: var(--c-text-3); border: 1px solid var(--c-border); }
.status--processing { background: rgba(59, 130, 246, 0.1); color: var(--c-info); border: 1px solid rgba(59, 130, 246, 0.2); }
.status--converted { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.2); }

.order-card__body { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.order-plan-name { font-size: var(--fs-lg); font-weight: var(--fw-bold); color: var(--c-text-1); }
.order-price { font-size: var(--fs-xl); font-weight: var(--fw-bold); color: var(--c-primary); }

.order-card__footer { display: flex; justify-content: space-between; align-items: center; }
.order-date { font-size: var(--fs-xs); color: var(--c-text-3); }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 8px 16px; border-radius: var(--r-sm); font-weight: var(--fw-medium);
  transition: all var(--t-fast); cursor: pointer; text-decoration: none; border: none; font-size: var(--fs-sm);
}
.btn--sm { height: 32px; padding: 0 16px; border-radius: var(--r-full); }
.btn--primary {
  background: linear-gradient(135deg, var(--c-primary), var(--c-primary-hover));
  color: #fff; box-shadow: 0 2px 8px var(--c-primary-glow);
}
.btn--primary:hover { box-shadow: 0 4px 12px var(--c-primary-glow); }
.btn--secondary { background: transparent; color: var(--c-text-2); border: 1px solid var(--c-border); }
.btn--secondary:hover { background: var(--c-bg-hover); color: var(--c-text-1); }
</style>

<template>
  <div class="gift-page">
    <section class="gift-hero">
      <div>
        <span>{{ isCdkey ? 'CDKEY Redeem' : 'Gift Card' }}</span>
        <h2>{{ pageTitle }}</h2>
        <p>{{ pageDesc }}</p>
      </div>
      <Icon :name="isCdkey ? 'credit-card' : 'gift'" :size="72" />
    </section>

    <section class="gift-grid">
      <div class="gift-card card">
        <div class="card__header">
          <Icon :name="isCdkey ? 'credit-card' : 'gift'" :size="20" class="text-primary" />
          <span class="card__title">{{ pageTitle }}</span>
        </div>
        <div class="form-group">
          <label class="form-label">兑换码</label>
          <input v-model.trim="code" class="form-input gift-input" :placeholder="placeholder" maxlength="32" autocomplete="off">
        </div>
        <div class="gift-actions">
          <button class="btn btn--secondary" type="button" :disabled="checking || !canSubmit" @click="handleCheck">
            <span v-if="checking" class="anim-spin">⟳</span>
            查询
          </button>
          <button class="btn btn--primary" type="button" :disabled="redeeming || !canSubmit" @click="handleRedeem">
            <span v-if="redeeming" class="anim-spin">⟳</span>
            立即兑换
          </button>
        </div>
        <p class="gift-tip">支持 8-32 位兑换码。兑换成功后奖励会自动发放到当前账号。</p>
      </div>

      <div class="gift-preview card">
        <div class="card__header">
          <Icon name="info" :size="20" class="text-primary" />
          <span class="card__title">奖励预览</span>
        </div>
        <div v-if="preview" class="preview-box">
          <h3>{{ preview.code_info?.template_name || preview.code_info?.name || '兑换码可用' }}</h3>
          <p v-if="preview.reason" class="text-warning">{{ preview.reason }}</p>
          <div class="preview-status" :class="{ 'preview-status--ok': preview.can_redeem }">
            {{ preview.can_redeem ? '当前账号可兑换' : '当前账号暂不可兑换' }}
          </div>
          <div class="reward-list">
            <div v-for="item in rewardItems" :key="item.label" class="reward-item">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </div>
        <div v-else class="preview-empty">
          <Icon name="inbox" :size="42" />
          <p>输入兑换码后可先查询奖励内容</p>
        </div>
      </div>
    </section>

    <section class="card p-0 overflow-hidden">
      <div class="gift-history-head">
        <div>
          <h3>兑换记录</h3>
          <p>查看当前账号历史兑换结果</p>
        </div>
        <button class="btn btn--secondary btn--sm" type="button" :disabled="historyLoading" @click="fetchHistory">
          <Icon name="refresh" :size="14" />
          刷新
        </button>
      </div>
      <div v-if="historyLoading" class="p-6 text-center text-3">正在加载兑换记录...</div>
      <div v-else-if="history.length === 0" class="p-6 text-center text-3">
        <Icon name="inbox" :size="42" class="mb-3 mx-auto" />
        <p>暂无兑换记录</p>
      </div>
      <div v-else class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>兑换码</th>
              <th>类型</th>
              <th>奖励</th>
              <th>倍率</th>
              <th>时间</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in history" :key="item.id">
              <td class="fw-semibold text-primary">{{ item.code || '-' }}</td>
              <td>{{ item.template_type_name || item.template_name || '-' }}</td>
              <td>{{ formatRewards(item.rewards_given) }}</td>
              <td>{{ item.multiplier_applied || 1 }}x</td>
              <td>{{ formatDate(item.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import { checkGiftCard, redeemGiftCard, getGiftCardHistory } from '@/api/user'
import { listFromPayload, objectFromPayload } from '@/api/normalizers'
import { useAppStore } from '@/stores/app'
import { useUserStore } from '@/stores/user'

const props = defineProps({
  mode: { type: String, default: 'gift' }
})

const app = useAppStore()
const user = useUserStore()
const code = ref('')
const checking = ref(false)
const redeeming = ref(false)
const preview = ref(null)
const history = ref([])
const historyLoading = ref(false)

const isCdkey = computed(() => props.mode === 'cdkey')
const pageTitle = computed(() => isCdkey.value ? 'CDKEY 兑换' : '礼品卡兑换')
const pageDesc = computed(() => isCdkey.value ? '输入 CDKEY 后可兑换余额、套餐、流量或其他奖励。' : '输入礼品卡兑换码，领取面板配置的专属奖励。')
const placeholder = computed(() => isCdkey.value ? '请输入 CDKEY' : '请输入礼品卡兑换码')
const canSubmit = computed(() => code.value.length >= 8 && code.value.length <= 32)

const rewardItems = computed(() => {
  const rewards = preview.value?.reward_preview || preview.value?.rewards || {}
  if (!rewards || typeof rewards !== 'object') return []
  return Object.entries(rewards).map(([label, value]) => ({
    label: rewardLabel(label),
    value: formatRewardValue(label, value)
  }))
})

watch(() => props.mode, () => {
  code.value = ''
  preview.value = null
})

function rewardLabel(key) {
  const map = {
    balance: '账户余额',
    transfer_enable: '套餐流量',
    traffic: '流量',
    plan: '套餐',
    plan_id: '套餐',
    expired_at: '有效期',
    commission: '佣金',
    reset_traffic: '重置流量'
  }
  return map[key] || key
}

function formatRewardValue(key, value) {
  if (value === null || value === undefined || value === '') return '-'
  if (['balance', 'commission'].includes(key)) return `¥ ${(Number(value) / 100).toFixed(2)}`
  if (['transfer_enable', 'traffic', 'reset_traffic'].includes(key)) return formatTraffic(Number(value))
  if (typeof value === 'object') return value.name || JSON.stringify(value)
  return String(value)
}

function formatRewards(rewards) {
  if (!rewards || typeof rewards !== 'object') return '-'
  return Object.entries(rewards)
    .map(([key, value]) => `${rewardLabel(key)}: ${formatRewardValue(key, value)}`)
    .join(' / ') || '-'
}

function formatTraffic(value) {
  if (!Number.isFinite(value) || value <= 0) return '0 B'
  const gb = value > 1024 * 1024 ? value / 1024 / 1024 / 1024 : value
  return `${Number(gb).toFixed(gb >= 10 ? 0 : 2)} GB`
}

function formatDate(value) {
  if (!value) return '-'
  const timestamp = typeof value === 'number' && value < 10000000000 ? value * 1000 : value
  return new Date(timestamp).toLocaleString('zh-CN')
}

async function handleCheck() {
  if (!canSubmit.value) return app.showToast('请输入有效兑换码', 'warning')
  checking.value = true
  try {
    preview.value = objectFromPayload(await checkGiftCard({ code: code.value }))
    app.showToast('兑换码查询成功', 'success')
  } catch (error) {
    preview.value = null
    app.showToast(error.message || '兑换码查询失败', 'error')
  } finally {
    checking.value = false
  }
}

async function handleRedeem() {
  if (!canSubmit.value) return app.showToast('请输入有效兑换码', 'warning')
  redeeming.value = true
  try {
    const result = objectFromPayload(await redeemGiftCard({ code: code.value }))
    app.showToast(result.message || '兑换成功', 'success')
    code.value = ''
    preview.value = null
    user.fetchAll()
    fetchHistory()
  } catch (error) {
    app.showToast(error.message || '兑换失败', 'error')
  } finally {
    redeeming.value = false
  }
}

async function fetchHistory() {
  historyLoading.value = true
  try {
    const res = await getGiftCardHistory({ page: 1, per_page: 20 })
    history.value = listFromPayload(res)
  } catch (error) {
    history.value = []
  } finally {
    historyLoading.value = false
  }
}

onMounted(fetchHistory)
</script>

<style scoped>
.gift-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.gift-hero {
  position: relative;
  min-height: 172px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 30px;
  color: #fff;
  background: var(--gradient-primary);
  border: 1px solid rgba(20,184,166,0.32);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-float);
  overflow: hidden;
}
.gift-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(110deg, rgba(255,255,255,0.08), transparent 36%);
  pointer-events: none;
}
.gift-hero > * {
  position: relative;
  z-index: 1;
}
.gift-hero span {
  color: var(--c-primary-hover);
  font-weight: 800;
  font-size: var(--fs-xs);
  text-transform: uppercase;
}
.gift-hero h2 {
  margin: 8px 0;
  font-size: clamp(1.8rem, 3vw, 2.5rem);
  letter-spacing: 0;
}
.gift-hero p {
  max-width: 560px;
  margin: 0;
  color: rgba(255, 255, 255, 0.85);
}
.gift-hero svg {
  color: rgba(255, 255, 255, 0.4);
}
.gift-grid {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  gap: 20px;
}
.gift-card,
.gift-preview {
  padding: 22px;
}
.gift-input {
  height: 48px;
  font-size: var(--fs-md);
  font-weight: var(--fw-semibold);
  letter-spacing: 0;
}
.gift-actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
}
.gift-actions .btn {
  flex: 1;
}
.gift-tip {
  margin: 14px 0 0;
  color: var(--c-text-3);
  font-size: var(--fs-sm);
}
.preview-box h3 {
  margin: 0 0 10px;
  color: var(--c-text-1);
}
.preview-status {
  display: inline-flex;
  margin: 8px 0 16px;
  padding: 5px 10px;
  color: var(--c-warning);
  background: rgba(245,158,11,0.12);
  border-radius: var(--r-full);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
}
.preview-status--ok {
  color: #2dd4bf;
  background: rgba(20,184,166,0.12);
}
.reward-list {
  display: grid;
  gap: 10px;
}
.reward-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  background: rgba(255,255,255,0.035);
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
}
.reward-item span {
  color: var(--c-text-2);
}
.reward-item strong {
  color: var(--c-text-1);
}
.preview-empty {
  min-height: 206px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--c-text-3);
  text-align: center;
}
.gift-history-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 20px;
  border-bottom: 1px solid var(--c-border);
}
.gift-history-head h3 {
  margin: 0;
  color: var(--c-text-1);
}
.gift-history-head p {
  margin: 4px 0 0;
  color: var(--c-text-3);
  font-size: var(--fs-sm);
}
@media (max-width: 860px) {
  .gift-grid { grid-template-columns: 1fr; }
  .gift-hero { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 520px) {
  .gift-actions { flex-direction: column; }
  .gift-history-head { align-items: flex-start; flex-direction: column; }
}
</style>

<template>
  <div class="page invite-page">
    
    <div v-if="loading" class="loading-state">
      <div class="anim-spin"><Icon name="loader" :size="32" /></div>
      <p>正在加载邀请数据...</p>
    </div>
    
    <div v-else-if="error" class="card text-center text-danger p-6">
      <Icon name="alert-circle" :size="32" class="mb-2 mx-auto" />
      <p>{{ error }}</p>
    </div>

    <template v-else>
      <!-- Commission Stats Card -->
      <div class="card commission-card anim-fade-up">
        <div class="commission-header">
          <div class="commission-balance">
            <h3 class="title">邀请佣金</h3>
            <div class="amount"><span class="currency">¥</span>{{ ((stat[4] || 0) / 100).toFixed(2) }}</div>
            <div class="subtitle">当前佣金余额</div>
          </div>
          <div class="commission-actions">
            <button class="btn btn--primary" @click="openTransferModal">划转</button>
            <button class="btn btn--outline" @click="openWithdrawModal" :disabled="withdrawClosed">提现</button>
          </div>
        </div>
        <div class="commission-stats">
          <div class="stat-item">
            <div class="stat-val">{{ stat[0] || 0 }}</div>
            <div class="stat-lbl">邀请数量</div>
          </div>
          <div class="stat-item">
            <div class="stat-val">{{ stat[3] || 0 }}<span class="percent">%</span></div>
            <div class="stat-lbl">佣金比例</div>
          </div>
          <div class="stat-item">
            <div class="stat-val"><span class="currency">¥</span>{{ ((stat[2] || 0) / 100).toFixed(2) }}</div>
            <div class="stat-lbl">确认中的佣金</div>
          </div>
          <div class="stat-item">
            <div class="stat-val"><span class="currency">¥</span>{{ ((stat[1] || 0) / 100).toFixed(2) }}</div>
            <div class="stat-lbl">累计佣金</div>
          </div>
        </div>
      </div>

      <!-- Invite Codes Management -->
      <div class="card codes-card mt-4 anim-fade-up" style="animation-delay: 0.1s">
        <div class="card-header-flex">
          <h3 class="card-title">邀请码管理</h3>
          <button class="btn btn--primary btn--sm" @click="handleCreateCode" :disabled="creating">
            <span v-if="creating" class="anim-spin" style="margin-right: 4px">⟳</span>
            生成邀请码
          </button>
        </div>
        
        <div class="codes-list" v-if="codes.length > 0">
          <div class="code-row" v-for="c in codes" :key="c.id || c.code">
            <div class="code-label">邀请码：{{ c.code }}</div>
            <div class="code-link-wrap">
              <Icon name="link" :size="16" />
              <span class="code-link">{{ getInviteUrl(c.code) }}</span>
            </div>
            <div class="code-actions">
              <button class="icon-btn" @click="copyLink(c.code)" title="复制链接"><Icon name="copy" :size="16" /></button>
            </div>
          </div>
        </div>
        <div v-else class="empty-state text-center py-4">
          您还未生成任何邀请码
        </div>
        
        <div class="codes-footer-hint">
          复制邀请链接，分享给好友，好友通过链接注册，您将获得佣金
        </div>
      </div>

      <!-- Commission Records -->
      <div class="card records-card mt-4 anim-fade-up" style="animation-delay: 0.2s">
        <div class="card-header-flex">
          <h3 class="card-title">佣金记录</h3>
        </div>
        
        <div class="records-table-wrap">
          <table class="records-table">
            <thead>
              <tr>
                <th>创建时间</th>
                <th>订单金额</th>
                <th>佣金</th>
              </tr>
            </thead>
            <tbody v-if="details.length > 0">
              <tr v-for="d in details" :key="d.id">
                <td>{{ formatDate(d.created_at) }}</td>
                <td>¥ {{ (d.order_amount / 100).toFixed(2) }}</td>
                <td>¥ {{ (d.get_amount / 100).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="details.length === 0" class="empty-state-large">
            <Icon name="inbox" :size="48" class="empty-icon" />
            <p>暂无佣金记录</p>
          </div>
        </div>
      </div>
      
      <!-- Transfer Modal -->
      <div v-if="showTransferModal" class="modal-overlay" @click.self="showTransferModal = false">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h3>佣金划转</h3>
            <button class="close-btn" @click="showTransferModal = false">
              <Icon name="x" :size="20" />
            </button>
          </div>
          <div class="modal-body pt-0">
            <div class="transfer-card">
              <div class="transfer-info">
                <div class="transfer-amount"><span class="currency">¥</span>{{ ((stat[4] || 0) / 100).toFixed(2) }}</div>
                <div class="transfer-label">佣金余额</div>
              </div>
              <div class="transfer-icon"><Icon name="credit-card" :size="28" /></div>
              <div class="transfer-info right-align">
                <div class="transfer-amount"><span class="currency">¥</span>{{ ((user.info?.balance || 0) / 100).toFixed(2) }}</div>
                <div class="transfer-label">账户余额</div>
              </div>
            </div>

            <div class="transfer-input-wrap">
              <span class="prefix">划转金额</span>
              <input type="number" v-model="transferAmount" class="transfer-input" placeholder="0.00" min="0.01" :max="((stat[4] || 0) / 100).toFixed(2)" step="0.01">
              <button class="btn-max" @click="transferAmount = ((stat[4] || 0) / 100).toFixed(2)" title="全部划转">
                <Icon name="arrow-up-right" :size="16" class="rotate-down-left" />
              </button>
            </div>

            <button class="btn btn--primary btn--block mt-4" @click="handleTransfer" :disabled="transferring || !transferAmount || transferAmount <= 0">
              <span v-if="transferring" class="anim-spin" style="margin-right: 8px">⟳</span>
              立即划转
            </button>
          </div>
        </div>
      </div>

      <!-- Withdraw Modal -->
      <div v-if="showWithdrawModal" class="modal-overlay" @click.self="showWithdrawModal = false">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h3>佣金提现</h3>
            <button class="close-btn" @click="showWithdrawModal = false">
              <Icon name="x" :size="20" />
            </button>
          </div>
          <div class="modal-body pt-0">
            <div class="withdraw-card">
              <div class="dollar-circle">$</div>
              <div class="withdraw-amount"><span class="currency">¥</span>{{ ((stat[4] || 0) / 100).toFixed(2) }}</div>
              <div class="withdraw-hint">提现会清空当前佣金余额，提现金额将在 1-3 个工作日内到账</div>
            </div>

            <div class="form-group mb-4">
               <label class="form-label text-1">提现方式</label>
               <div class="select-wrapper">
                 <select v-model="withdrawForm.method" class="form-input">
                   <option v-for="method in withdrawMethods" :key="method" :value="method">{{ method }}</option>
                 </select>
                 <Icon name="chevron-down" class="select-icon" :size="16" />
               </div>
            </div>
            <div class="form-group mb-4">
               <label class="form-label text-1">提现账号</label>
               <input type="text" v-model="withdrawForm.account" class="form-input" placeholder="请输入您的收款账号或地址">
            </div>

            <button class="btn btn--primary btn--block mt-2" @click="handleWithdraw" :disabled="withdrawing || !withdrawForm.account || (stat[4] || 0) <= 0">
              <span v-if="withdrawing" class="anim-spin" style="margin-right: 8px">⟳</span>
              立即提现
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue'
import { getInviteCodes, createInviteCode, deleteInviteCode, getInviteDetails, transfer, withdrawTicket } from '@/api/user'
import { listFromPayload, objectFromPayload } from '@/api/normalizers'
import { useAppStore } from '@/stores/app'
import { useUserStore } from '@/stores/user'
import { copyText } from '@/utils/clipboard'
import Icon from '@/components/ui/Icon.vue'

const app = useAppStore()
const user = useUserStore()
const loading = ref(true)
const creating = ref(false)
const deletingCode = ref('')
const error = ref('')

const codes = ref([])
// stat defaults to [registered, valid, uncheck, rate, available]
const stat = ref([0, 0, 0, 0, 0])
const details = ref([])
const withdrawMethods = computed(() => {
  const methods = user.config?.withdraw_methods
  return Array.isArray(methods) && methods.length ? methods : ['USDT', 'Alipay', 'Bank']
})
const withdrawClosed = computed(() => Number(user.config?.withdraw_close || 0) === 1)

const showTransferModal = ref(false)
const transferAmount = ref('')
const transferring = ref(false)

function formatDate(ts) {
  if (!ts) return '-'
  return new Date(ts * 1000).toLocaleString('zh-CN', {
    month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit'
  })
}

function getInviteUrl(code) {
  // Try to use app_url if configured, else fallback to current origin
  const origin = window.location.origin
  return `${origin}/#/register?code=${code}`
}

async function fetchAll() {
  loading.value = true
  error.value = ''
  try {
    const res = objectFromPayload(await getInviteCodes())
    codes.value = listFromPayload(res.codes)
    stat.value = Array.isArray(res.stat) ? res.stat : [0, 0, 0, 0, 0]
    
    const detRes = await getInviteDetails()
    details.value = listFromPayload(detRes)
  } catch (err) {
    error.value = err.message || '获取邀请数据失败'
  } finally {
    loading.value = false
  }
}

async function handleCreateCode() {
  creating.value = true
  try {
    await createInviteCode()
    app.showToast('邀请码生成成功', 'success')
    await fetchAll()
  } catch (err) {
    app.showToast(err.message || '生成失败，请检查是否已达到生成上限', 'error')
    await fetchAll()
  } finally {
    creating.value = false
  }
}

async function handleDeleteCode(code) {
  if (!code) return
  if (!confirm('确定要删除这个邀请码吗？删除后该邀请码将无法继续用于注册。')) return

  deletingCode.value = code
  try {
    await deleteInviteCode({ code })
    app.showToast('邀请码已删除', 'success')
    await fetchAll()
  } catch (err) {
    app.showToast(err.message || '删除邀请码失败', 'error')
    await fetchAll()
  } finally {
    deletingCode.value = ''
  }
}

async function copyLink(code) {
  const url = getInviteUrl(code)
  const ok = await copyText(url)
  if (ok) {
    app.showToast('邀请链接已复制到剪贴板', 'success')
  } else {
    app.showToast('复制失败，请手动复制', 'error')
  }
}

function openTransferModal() {
  transferAmount.value = ''
  showTransferModal.value = true
}

async function handleTransfer() {
  if (!transferAmount.value || transferAmount.value <= 0) return
  transferring.value = true
  try {
    const amountInCents = Math.round(parseFloat(transferAmount.value) * 100)
    await transfer({ transfer_amount: amountInCents })
    app.showToast('佣金划转成功', 'success')
    showTransferModal.value = false
    await fetchAll()
    user.fetchAll() // refresh balance in store
  } catch (err) {
    app.showToast(err.message || '划转失败', 'error')
  } finally {
    transferring.value = false
  }
}

const showWithdrawModal = ref(false)
const withdrawForm = reactive({ method: 'USDT', account: '' })
const withdrawing = ref(false)

function openWithdrawModal() {
  withdrawForm.account = ''
  withdrawForm.method = withdrawMethods.value[0] || ''
  showWithdrawModal.value = true
}

async function handleWithdraw() {
  const maxCents = stat.value[4] || 0
  if (maxCents <= 0 || !withdrawForm.account) return
  withdrawing.value = true
  try {
    await withdrawTicket({ withdraw_method: withdrawForm.method, withdraw_account: withdrawForm.account, withdraw_amount: maxCents })
    app.showToast('提现申请成功，请前往工单查看进度', 'success')
    showWithdrawModal.value = false
    await fetchAll()
    user.fetchAll()
  } catch (err) {
    app.showToast(err.message || '提现申请失败', 'error')
  } finally {
    withdrawing.value = false
  }
}

onMounted(() => {
  if (!user.config) user.fetchConfig()
  fetchAll()
})
</script>

<style scoped>
.invite-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 200px;
  color: #64748b;
  gap: 16px;
}

/* Card General */
.card {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  padding: 24px;
}
.card-header-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}
.card-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

/* Commission Card */
.commission-card {
  display: flex;
  flex-direction: column;
  gap: 32px;
}
.commission-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.commission-balance .title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 12px 0;
}
.commission-balance .amount {
  font-size: 2.5rem;
  font-weight: 800;
  color: #1e293b;
  line-height: 1;
  margin-bottom: 8px;
}
.commission-balance .amount .currency {
  font-size: 1.25rem;
  font-weight: 600;
  margin-right: 4px;
}
.commission-balance .subtitle {
  font-size: 0.9rem;
  color: #94a3b8;
}
.commission-actions {
  display: flex;
  gap: 12px;
}
.btn--outline {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #475569;
  border-radius: 6px;
  padding: 8px 16px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.btn--outline:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #1e293b;
}

.commission-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}
.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
}
.stat-val {
  font-size: 1.15rem;
  font-weight: 700;
  color: #1e293b;
}
.stat-val .currency, .stat-val .percent {
  font-size: 0.9rem;
  font-weight: 500;
}
.stat-val .currency { margin-right: 2px; }
.stat-val .percent { margin-left: 2px; }
.stat-lbl {
  font-size: 0.85rem;
  color: #64748b;
}

/* Codes Card */
.codes-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 24px;
}
.code-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 8px;
}
.code-label {
  font-size: 0.95rem;
  font-weight: 500;
  color: #475569;
  min-width: 140px;
}
.code-link-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #64748b;
  font-size: 0.95rem;
  margin: 0 20px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.code-actions .icon-btn {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.code-actions .icon-btn:hover {
  background: #e2e8f0;
  color: #1e293b;
}
.codes-footer-hint {
  font-size: 0.85rem;
  color: #94a3b8;
}

/* Records Card */
.records-table-wrap {
  overflow-x: auto;
}
.records-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.records-table th {
  padding: 16px 20px;
  background: #f8fafc;
  font-weight: 600;
  font-size: 0.9rem;
  color: #1e293b;
  border-bottom: 1px solid #e2e8f0;
}
.records-table td {
  padding: 16px 20px;
  font-size: 0.95rem;
  color: #475569;
  border-bottom: 1px solid #f1f5f9;
}
.records-table tbody tr:hover td {
  background: #f8fafc;
}
.records-table tbody tr:last-child td {
  border-bottom: none;
}
.empty-state-large {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 0;
  color: #94a3b8;
  gap: 16px;
}
.empty-icon {
  opacity: 0.5;
}

/* Utilities */
.text-danger { color: #ef4444; }
.py-4 { padding-top: 24px; padding-bottom: 24px; }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
  z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal-content {
  background: var(--c-bg-card, #fff); border-radius: 12px; width: 100%; max-width: 420px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden;
  animation: modalIn 0.3s ease;
}
@keyframes modalIn {
  from { opacity: 0; transform: translateY(20px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between; padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
}
.modal-header h3 { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0; }
.close-btn { background: none; border: none; cursor: pointer; color: #64748b; transition: color 0.2s; padding: 4px; }
.close-btn:hover { color: #1e293b; }
.modal-body { padding: 24px; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 0.9rem; color: #475569; margin-bottom: 8px; }
.input-with-currency { position: relative; display: flex; align-items: center; }
.currency-symbol { position: absolute; left: 16px; color: #475569; font-weight: 600; }
.custom-input { padding-left: 36px; padding-right: 60px; width: 100%; }
.inside-btn { position: absolute; right: 8px; }
.form-input {
  width: 100%; padding: 12px 16px; background: #f8fafc;
  border: 1px solid #e2e8f0; border-radius: 6px; color: #1e293b; outline: none;
  font-size: 0.95rem; transition: all 0.2s;
}
.form-input:focus { border-color: #10b981; background: #fff; }
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  border: none; border-radius: 6px; font-weight: 500; cursor: pointer; transition: all 0.2s;
}
.btn--block { width: 100%; padding: 12px; font-size: 1rem; border-radius: 8px; }
.btn--primary { background: #26c2a3; color: #fff; padding: 10px 20px; }
.btn--primary:hover:not(:disabled) { background: #1fa48a; }
.btn--secondary { background: #e2e8f0; color: #475569; padding: 10px 20px; }
.btn--secondary:hover:not(:disabled) { background: #cbd5e1; }
.btn--sm { padding: 8px 16px; font-size: 0.9rem; }
.btn--xs { padding: 4px 12px; font-size: 0.85rem; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* Custom Modal Additions */
.border-0 { border: none !important; }
.pt-0 { padding-top: 0 !important; }
.text-1 { color: #1e293b; font-weight: 500; }
.mt-4 { margin-top: 24px; }
.mt-2 { margin-top: 12px; }
.mb-4 { margin-bottom: 24px; }

/* Transfer Card */
.transfer-card {
  background: #f5fcf9;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.transfer-info { display: flex; flex-direction: column; gap: 4px; }
.transfer-info.right-align { align-items: flex-end; }
.transfer-amount { font-size: 1.5rem; font-weight: 800; color: #1e293b; }
.transfer-amount .currency { font-size: 1rem; font-weight: 600; margin-right: 4px; }
.transfer-label { font-size: 0.85rem; color: #94a3b8; }
.transfer-icon { color: #1e293b; }

/* Transfer Input */
.transfer-input-wrap {
  display: flex;
  align-items: center;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0;
  overflow: hidden;
}
.transfer-input-wrap .prefix {
  padding: 12px 16px;
  color: #64748b;
  font-size: 0.95rem;
  background: #ffffff;
}
.transfer-input {
  flex: 1;
  border: none;
  outline: none;
  padding: 12px 0;
  font-size: 1rem;
  color: #1e293b;
  min-width: 0;
  background: #ffffff;
}
.btn-max {
  background: #fff;
  border: none;
  border-left: 1px solid #e2e8f0;
  padding: 12px 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-max:hover { background: #f8fafc; }
.rotate-down-left { transform: rotate(180deg); color: #1e293b; background: #1e293b; color: white; border-radius: 50%; padding: 2px; }

/* Withdraw Card */
.withdraw-card {
  background: #f5fcf9;
  border-radius: 12px;
  padding: 32px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
  text-align: center;
}
.dollar-circle {
  width: 32px;
  height: 32px;
  background: #1e293b;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 1.2rem;
  margin-bottom: 12px;
}
.withdraw-amount {
  font-size: 2rem;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 12px;
}
.withdraw-amount .currency {
  font-size: 1.2rem;
  font-weight: 600;
  margin-right: 4px;
}
.withdraw-hint {
  font-size: 0.85rem;
  color: #94a3b8;
}

/* Select wrapper */
.select-wrapper {
  position: relative;
}
.select-wrapper select {
  appearance: none;
  padding-right: 36px;
}
.select-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}
</style>

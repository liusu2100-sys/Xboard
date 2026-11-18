<template>
  <div class="page invite-page">
    <div class="page-header">
      <h2 class="page-title">邀请返利</h2>
      <button class="btn btn--primary btn--sm" @click="handleCreateCode" :disabled="creating">
        <Icon name="plus" :size="16" />
        <span v-if="creating" class="anim-spin" style="margin-right: 4px">⟳</span>
        生成邀请码
      </button>
    </div>
    
    <div v-if="loading" class="dash-grid">
      <div v-for="i in 3" :key="i" class="card skeleton" style="height: 120px"></div>
    </div>
    
    <div v-else-if="error" class="card text-center text-danger p-6">
      <Icon name="alert" :size="32" class="mb-2 mx-auto" />
      <p>{{ error }}</p>
    </div>

    <template v-else>
      <!-- Stats Grid -->
      <div class="dash-grid">
        <div class="card anim-fade-up">
          <div class="card__header mb-2">
            <Icon name="users" :size="20" class="text-primary" />
            <span class="card__title">已注册用户</span>
          </div>
          <div class="stat-value">{{ stat[0] || 0 }} 人</div>
          <div class="stat-label">通过您的链接注册</div>
        </div>
        
        <div class="card anim-fade-up" style="animation-delay: 0.1s">
          <div class="card__header mb-2">
            <Icon name="gift" :size="20" class="text-success" />
            <span class="card__title">有效佣金</span>
          </div>
          <div class="stat-value text-success">¥ {{ ((stat[1] || 0) / 100).toFixed(2) }}</div>
          <div class="stat-label">已结算的返利</div>
        </div>
        
        <div class="card anim-fade-up" style="animation-delay: 0.2s">
          <div class="card__header mb-2">
            <Icon name="wallet" :size="20" class="text-warning" />
            <span class="card__title">可划转佣金 / 比例</span>
          </div>
          <div class="stat-value text-warning">
            ¥ {{ ((stat[4] || 0) / 100).toFixed(2) }} / {{ stat[3] || 0 }}%
          </div>
          <div class="stat-label d-flex justify-between align-center mt-2">
            <span>确认中: ¥ {{ ((stat[2] || 0) / 100).toFixed(2) }}</span>
            <div class="flex gap-1" style="display:flex;gap:4px">
              <button class="btn btn--secondary btn--xs" @click="openTransferModal" :disabled="(stat[4] || 0) <= 0">转余额</button>
              <button class="btn btn--primary btn--xs" @click="openWithdrawModal" :disabled="withdrawClosed || (stat[4] || 0) <= 0" style="padding:4px 8px;font-size:12px;border-radius:4px;">提现</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Invite Codes -->
      <div class="card p-0 mt-4 overflow-hidden anim-fade-up">
        <div class="p-4 border-bottom">
          <h3 class="card__title">我的邀请链接</h3>
        </div>
        <div v-if="codes.length === 0" class="p-6 text-center text-3">
          您还未生成任何邀请码，请点击右上角生成。
        </div>
        <div v-else class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>邀请码</th>
                <th>生成时间</th>
                <th style="width: 180px">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in codes" :key="c.id || c.code">
                <td class="fw-bold text-primary">{{ c.code }}</td>
                <td>{{ formatDate(c.created_at) }}</td>
                <td>
                  <div class="table-actions">
                    <button class="btn btn--secondary btn--sm" @click="copyLink(c.code)">复制链接</button>
                    <button class="btn btn--danger btn--sm" @click="handleDeleteCode(c.code)" :disabled="deletingCode === c.code">
                      {{ deletingCode === c.code ? '删除中' : '删除' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Commission Details -->
      <div class="card p-0 mt-4 overflow-hidden anim-fade-up">
        <div class="p-4 border-bottom">
          <h3 class="card__title">返利明细</h3>
        </div>
        <div v-if="details.length === 0" class="p-6 text-center text-3">
          暂无返利记录
        </div>
        <div v-else class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>订单金额</th>
                <th>获得佣金</th>
                <th>时间</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in details" :key="d.id">
                <td>¥ {{ (d.order_amount / 100).toFixed(2) }}</td>
                <td class="text-success fw-bold">+ ¥ {{ (d.get_amount / 100).toFixed(2) }}</td>
                <td>{{ formatDate(d.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Transfer Modal -->
      <div v-if="showTransferModal" class="modal-overlay" @click.self="showTransferModal = false">
        <div class="modal-content">
          <div class="modal-header">
            <h3>佣金划转</h3>
            <button class="close-btn" @click="showTransferModal = false">
              <Icon name="x" :size="20" />
            </button>
          </div>
          <div class="modal-body">
            <p class="text-2 mb-4">您可以将可用佣金转移至您的账户余额用于购买套餐。</p>
            <div class="form-group">
              <label class="form-label">划转金额 (最多 ¥{{ ((stat[4] || 0) / 100).toFixed(2) }})</label>
              <div class="input-with-currency">
                <span class="currency-symbol">¥</span>
                <input type="number" v-model="transferAmount" class="form-input custom-input" min="0.01" :max="((stat[4] || 0) / 100).toFixed(2)" step="0.01">
                <button class="btn btn--secondary btn--xs inside-btn" @click="transferAmount = ((stat[4] || 0) / 100).toFixed(2)">全额</button>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn--secondary" @click="showTransferModal = false">取消</button>
            <button class="btn btn--primary" @click="handleTransfer" :disabled="transferring || !transferAmount || transferAmount <= 0">
              <span v-if="transferring" class="anim-spin" style="margin-right: 8px">⟳</span>
              确认划转
            </button>
          </div>
        </div>
      </div>

      <!-- Withdraw Modal -->
      <div v-if="showWithdrawModal" class="modal-overlay" @click.self="showWithdrawModal = false">
        <div class="modal-content">
          <div class="modal-header">
            <h3>佣金提现</h3>
            <button class="close-btn" @click="showWithdrawModal = false">
              <Icon name="x" :size="20" />
            </button>
          </div>
          <div class="modal-body">
            <p class="text-2 mb-4 text-sm">申请提现后，系统将为您生成对应的财务工单并扣除对应佣金，等待管理员审核打款。</p>
            <div class="form-group mb-3">
               <label class="form-label">提现方式</label>
               <select v-model="withdrawForm.method" class="form-input">
                 <option v-for="method in withdrawMethods" :key="method" :value="method">{{ method }}</option>
               </select>
            </div>
            <div class="form-group mb-3">
               <label class="form-label">提现账号 / 地址</label>
               <input type="text" v-model="withdrawForm.account" class="form-input" placeholder="请输入您的收款账号或地址">
            </div>
            <div class="form-group">
              <label class="form-label">提现金额 (最多 ¥{{ ((stat[4] || 0) / 100).toFixed(2) }})</label>
              <div class="input-with-currency">
                <span class="currency-symbol">¥</span>
                <input type="number" v-model="withdrawForm.amount" class="form-input custom-input" min="0.01" :max="((stat[4] || 0) / 100).toFixed(2)" step="0.01">
                <button class="btn btn--secondary btn--xs inside-btn" @click="withdrawForm.amount = ((stat[4] || 0) / 100).toFixed(2)">全额</button>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn--secondary" @click="showWithdrawModal = false">取消</button>
            <button class="btn btn--primary" @click="handleWithdraw" :disabled="withdrawing || !withdrawForm.amount || withdrawForm.amount <= 0 || !withdrawForm.account">
              <span v-if="withdrawing" class="anim-spin" style="margin-right: 8px">⟳</span>
              提交提现申请
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

async function fetchAll() {
  loading.value = true
  error.value = ''
  try {
    // The axios interceptor already unwraps response.data.data,
    // so `res` = { codes: [...], stat: [...] }
    const res = objectFromPayload(await getInviteCodes())
    codes.value = listFromPayload(res.codes)
    stat.value = Array.isArray(res.stat) ? res.stat : [0, 0, 0, 0, 0]
    
    // details endpoint returns raw response({ data: [...], total: N })
    // interceptor unwraps to the array directly
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
  const url = `${window.location.origin}/#/register?code=${code}`
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
const withdrawForm = reactive({ method: 'USDT', account: '', amount: '' })
const withdrawing = ref(false)

function openWithdrawModal() {
  withdrawForm.amount = ''
  withdrawForm.account = ''
  withdrawForm.method = withdrawMethods.value[0] || ''
  showWithdrawModal.value = true
}

async function handleWithdraw() {
  if (!withdrawForm.amount || withdrawForm.amount <= 0 || !withdrawForm.account) return
  withdrawing.value = true
  try {
    const amountInCents = Math.round(parseFloat(withdrawForm.amount) * 100)
    await withdrawTicket({ withdraw_method: withdrawForm.method, withdraw_account: withdrawForm.account, withdraw_amount: amountInCents })
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
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.page-title {
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
}

.dash-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.card__header {
  display: flex;
  align-items: center;
  gap: 10px;
}
.card__title {
  font-size: var(--fs-base);
  font-weight: var(--fw-semibold);
  color: var(--c-text-1);
}
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

.border-bottom {
  border-bottom: 1px solid var(--c-border);
}

.table-responsive {
  overflow-x: auto;
}
.table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.table th {
  padding: 16px 20px;
  background: var(--c-bg-elevated);
  font-weight: var(--fw-medium);
  font-size: var(--fs-sm);
  color: var(--c-text-3);
  border-bottom: 1px solid var(--c-border);
  white-space: nowrap;
}
.table td {
  padding: 16px 20px;
  font-size: var(--fs-sm);
  color: var(--c-text-2);
  border-bottom: 1px solid var(--c-border);
}
.table tbody tr:hover td {
  background: var(--c-bg-hover);
}
.table tbody tr:last-child td {
  border-bottom: none;
}
.table-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.fw-bold {
  font-weight: var(--fw-bold);
}
.text-danger { color: var(--c-danger); }
.text-success { color: var(--c-success); }
.text-primary { color: var(--c-primary); }
.text-warning { color: var(--c-warning); }
.d-flex { display: flex; }
.justify-between { justify-content: space-between; }
.mt-2 { margin-top: 8px; }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
  z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal-content {
  background: var(--c-bg-card); border-radius: var(--r-xl); width: 100%; max-width: 420px;
  box-shadow: var(--shadow-lg); border: 1px solid var(--c-border); overflow: hidden;
  animation: modalIn 0.3s ease;
}
@keyframes modalIn {
  from { opacity: 0; transform: translateY(20px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between; padding: 20px 24px;
  border-bottom: 1px solid var(--c-border);
}
.modal-header h3 { font-size: var(--fs-lg); font-weight: var(--fw-bold); color: var(--c-text-1); }
.close-btn { color: var(--c-text-3); transition: color var(--t-fast); padding: 4px; }
.close-btn:hover { color: var(--c-text-1); }
.modal-body { padding: 24px; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: var(--fs-sm); color: var(--c-text-2); margin-bottom: 8px; }
.input-with-currency { position: relative; display: flex; align-items: center; }
.currency-symbol { position: absolute; left: 16px; color: var(--c-text-2); font-weight: var(--fw-bold); }
.custom-input { padding-left: 36px; padding-right: 60px; width: 100%; }
.inside-btn { position: absolute; right: 8px; }
.form-input {
  width: 100%; padding: 12px 16px; background: var(--c-bg-elevated);
  border: 1px solid var(--c-border); border-radius: var(--r-sm); color: var(--c-text-1); outline: none;
  font-size: var(--fs-base); transition: all var(--t-fast);
}
.form-input:focus { border-color: var(--c-primary); background: var(--c-bg-base); }
.modal-footer {
  padding: 16px 24px; border-top: 1px solid var(--c-border);
  display: flex; justify-content: flex-end; gap: 12px; background: var(--c-bg-elevated);
}
</style>

<template>
  <div class="page profile-page">
    <h2 class="page-title">账号设置</h2>
    
    <div class="grid-2">
      <!-- Left Column -->
      <div class="flex-col gap-4">
        <!-- Profile Info -->
        <div class="card p-0 anim-fade-up">
          <div class="card__header p-4 border-bottom">
            <Icon name="user" :size="20" class="text-primary" />
            <h3 class="card__title">个人资料</h3>
          </div>
          <div class="p-6">
            <div class="info-group">
              <label class="info-label">邮箱地址</label>
              <div class="info-value">{{ user.info?.email || '—' }}</div>
            </div>
            <div class="info-group mt-4">
              <label class="info-label">账户余额</label>
              <div class="info-value text-primary font-bold" style="font-size: var(--fs-lg)">
                ¥ {{ ((user.info?.balance || 0) / 100).toFixed(2) }}
              </div>
            </div>
            <div class="info-group mt-4">
              <label class="info-label">注册时间</label>
              <div class="info-value">{{ formatDate(user.info?.created_at) }}</div>
            </div>
          </div>
        </div>

        <!-- Gift Card Redeption -->
        <div class="card p-0 anim-fade-up" style="animation-delay: 0.1s">
          <div class="card__header p-4 border-bottom">
            <Icon name="gift" :size="20" class="text-success" />
            <h3 class="card__title">礼品卡充值</h3>
          </div>
          <div class="p-6 content-flex">
            <p class="text-xs text-3 mb-3">使用您的礼品卡代码兑换账户余额。</p>
            <div class="flex gap-2">
              <input type="text" v-model="giftCardCode" class="form-input" placeholder="请输入礼品卡代码">
              <button class="btn btn--primary flex-shrink-0" @click="handleRedeemGiftCard" :disabled="!giftCardCode || redeeming" style="padding: 0 16px;">
                <span v-if="redeeming" class="anim-spin mr-1">⟳</span> 兑换
              </button>
            </div>
          </div>
        </div>

        <!-- Telegram Binding -->
        <div class="card p-0 anim-fade-up" style="animation-delay: 0.15s">
          <div class="card__header p-4 border-bottom">
            <Icon name="send" :size="20" class="text-info" />
            <h3 class="card__title">Telegram 绑定</h3>
          </div>
          <div class="p-6">
            <div v-if="user.info?.telegram_id" class="text-success flex align-center gap-2">
              <Icon name="check-circle" :size="18" /> 您已绑定 Telegram (ID: {{ user.info.telegram_id }})
            </div>
            <div v-else>
              <p class="text-xs text-3 mb-4">绑定 Telegram 群组助手后，您可以在 TG 接收到期提醒、快速回复工单，甚至快捷登录。</p>
              <button class="btn btn--secondary w-full" @click="handleBindTelegram" :disabled="bindingTg">
                {{ bindingTg ? '获取绑定链接中...' : '前往绑定 Telegram' }}
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Right Column -->
      <div class="flex-col gap-4">
        <!-- Change Password -->
        <div class="card p-0 anim-fade-up" style="animation-delay: 0.2s">
          <div class="card__header p-4 border-bottom">
            <Icon name="shield" :size="20" class="text-warning" />
            <h3 class="card__title">修改密码</h3>
          </div>
          <div class="p-6">
            <form @submit.prevent="handlePasswordChange">
              <div class="form-group mb-4">
                <label class="form-label">当前密码</label>
                <input type="password" v-model="pwdForm.old_password" class="form-input" placeholder="请输入当前密码" required>
              </div>
              <div class="form-group mb-4">
                <label class="form-label">新密码</label>
                <input type="password" v-model="pwdForm.new_password" class="form-input" placeholder="请输入新密码" required>
              </div>
              <div class="form-group mb-4">
                <label class="form-label">确认新密码</label>
                <input type="password" v-model="pwdForm.confirm_password" class="form-input" placeholder="请再次输入新密码" required>
              </div>
              <button type="submit" class="btn btn--primary w-full" :disabled="pwdLoading">
                <span v-if="pwdLoading" class="anim-spin mr-1">⟳</span>修改密码
              </button>
            </form>
          </div>
        </div>
        
        <!-- Notifications -->
        <div class="card p-0 anim-fade-up" style="animation-delay: 0.3s">
          <div class="card__header p-4 border-bottom">
            <Icon name="bell" :size="20" class="text-primary" />
            <h3 class="card__title">通知提醒</h3>
          </div>
          <div class="p-6 flex-col gap-4">
            <div class="flex justify-between align-center">
              <div>
                <div class="text-sm font-bold text-1">服务到期提醒</div>
                <div class="text-xs text-3 pt-1">套餐到期前将通过邮件或 TG 给您发送提醒</div>
              </div>
              <label class="switch">
                <input type="checkbox" v-model="notifForm.remind_expire" @change="updateUserSettings">
                <span class="slider round"></span>
              </label>
            </div>
            <div class="flex justify-between align-center">
              <div>
                <div class="text-sm font-bold text-1">流量耗尽提醒</div>
                <div class="text-xs text-3 pt-1">当可用流量不足时触发告警</div>
              </div>
              <label class="switch">
                <input type="checkbox" v-model="notifForm.remind_traffic" @change="updateUserSettings">
                <span class="slider round"></span>
              </label>
            </div>
          </div>
        </div>

        <!-- Reset Security -->
        <div class="card p-0 anim-fade-up overflow-hidden" style="animation-delay: 0.35s">
          <div class="p-4 border-bottom">
            <h3 class="card-title text-danger">重置订阅链接</h3>
            <p class="text-3 fs-sm mt-1">如果您的订阅发生泄露，请立即重置。此前所有节点和链接将被作废。</p>
          </div>
          <div class="p-4 bg-elevated">
            <button class="btn btn--danger w-full" @click="handleResetSecurity" :disabled="resetting">
              <span v-if="resetting" class="anim-spin mr-1">⟳</span> 立即作废并重置
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Active Sessions (Full Width) -->
    <div class="card p-0 mt-4 anim-fade-up" style="animation-delay: 0.4s">
       <div class="card__header p-4 border-bottom">
          <Icon name="monitor" :size="20" class="text-primary" />
          <h3 class="card__title">活动会话 (登录设备)</h3>
          <button class="btn btn--secondary btn--sm" @click="fetchSessions" :disabled="sessionsLoading">刷新</button>
       </div>
       <div class="p-4">
         <div v-if="sessionsLoading" class="text-center text-3 py-4">正在加载设备列表...</div>
         <div v-else-if="sessions.length === 0" class="text-center text-3 py-4">暂无活动会话</div>
         <div v-else class="table-responsive">
           <table class="table">
             <thead>
               <tr>
                 <th>设备</th>
                 <th>IP 地址</th>
                 <th>客户端</th>
                 <th>登录时间</th>
                 <th>最后活动时间</th>
                 <th align="right">操作</th>
               </tr>
             </thead>
             <tbody>
               <tr v-for="sess in sessions" :key="sess.id || sess.name" :class="{ 'session-row--current': isCurrentSession(sess) }">
                 <td>
                   <div class="session-device">
                     <span class="session-device__name">{{ sessionDevice(sess) }}</span>
                     <span v-if="isCurrentSession(sess)" class="session-badge session-badge--current">当前设备</span>
                     <span v-else-if="isLegacySession(sess)" class="session-badge">旧会话</span>
                   </div>
                   <div class="session-agent" :title="sessionAgent(sess)">{{ sessionAgent(sess) }}</div>
                 </td>
                 <td>
                   <span class="session-ip" :class="{ 'session-ip--empty': !hasSessionIp(sess) }">{{ sessionIp(sess) }}</span>
                 </td>
                 <td class="text-sm">{{ sessionClient(sess) }}</td>
                 <td class="text-sm">{{ sessionLoginDate(sess) }}</td>
                 <td class="text-sm">{{ sessionDate(sess) }}</td>
                 <td align="right">
                   <button class="btn btn--secondary btn--xs text-danger" @click="handleRemoveSession(sess.id)" v-if="!isCurrentSession(sess)">强制下线</button>
                   <span v-else class="text-success text-xs font-bold">已登录</span>
                 </td>
               </tr>
             </tbody>
           </table>
         </div>
       </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted, watch } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import { listFromPayload, objectFromPayload } from '@/api/normalizers'
import { 
  changePassword, resetSecurity, getTelegramBotInfo, 
  getActiveSession, removeActiveSession, redeemGiftCard, updateUser 
} from '@/api/user'

const user = useUserStore()
const app = useAppStore()

const pwdLoading = ref(false)
const resetting = ref(false)
const redeeming = ref(false)
const bindingTg = ref(false)
const sessionsLoading = ref(false)

const pwdForm = reactive({
  old_password: '',
  new_password: '',
  confirm_password: ''
})

const notifForm = reactive({
  remind_expire: false,
  remind_traffic: false
})

const giftCardCode = ref('')
const sessions = ref([])

function formatDate(ts) {
  if (!ts) return '-'
  const date = normalizeDate(ts)
  if (!date) return '-'
  return date.toLocaleString('zh-CN', { hour12: false })
}

function normalizeDate(value) {
  if (!value) return null

  if (typeof value === 'number') {
    const timestamp = value > 1e12 ? value : value * 1000
    const date = new Date(timestamp)
    return Number.isNaN(date.getTime()) ? null : date
  }

  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (!trimmed) return null
    if (/^\d+$/.test(trimmed)) {
      const numeric = Number(trimmed)
      const timestamp = numeric > 1e12 ? numeric : numeric * 1000
      const date = new Date(timestamp)
      return Number.isNaN(date.getTime()) ? null : date
    }
    const date = new Date(trimmed)
    return Number.isNaN(date.getTime()) ? null : date
  }

  return null
}

function rawSessionIp(session) {
  return session.ip || session.ip_address || session.client_ip || ''
}

function hasSessionIp(session) {
  return !!rawSessionIp(session)
}

function sessionIp(session) {
  return rawSessionIp(session) || (isLegacySession(session) ? '旧会话未记录' : '未记录')
}

function sessionDevice(session) {
  const device = session.device || session.device_name || ''
  if (device) return device

  const name = session.name || ''
  if (name && !/^[A-Za-z0-9]{20}$/.test(name)) return name.split(' · ')[0] || name

  return session.id ? `登录会话 #${session.id}` : '未知设备'
}

function sessionClient(session) {
  const parts = [session.browser, session.platform].filter(Boolean)
  return parts.length ? parts.join(' / ') : '未知客户端'
}

function sessionAgent(session) {
  return session.user_agent || session.agent || '未记录 User-Agent'
}

function sessionLoginDate(session) {
  return formatDate(session.login_at || session.created_at)
}

function sessionDate(session) {
  return formatDate(session.last_activity || session.last_used_at || session.updated_at || session.created_at)
}

function isCurrentSession(session) {
  return session.is_current === true || session.is_current === 1 || session.is_current === '1'
}

function isLegacySession(session) {
  return session.is_legacy === true || session.is_legacy === 1 || session.is_legacy === '1'
}

watch(
  () => user.info,
  (info) => {
    if (!info) return
    notifForm.remind_expire = !!info.remind_expire
    notifForm.remind_traffic = !!info.remind_traffic
  },
  { immediate: true }
)

onMounted(async () => {
  if (!user.info) {
    user.fetchAll()
  }
  fetchSessions()
})

async function fetchSessions() {
  sessionsLoading.value = true
  try {
    const res = await getActiveSession()
    sessions.value = listFromPayload(res)
  } catch (err) {
    app.showToast(err.message || '获取活动会话失败', 'error')
  } finally {
    sessionsLoading.value = false
  }
}

async function handleRemoveSession(sessionId) {
  if (!confirm('确定要强制下线该设备吗？')) return
  try {
    await removeActiveSession({ session_id: sessionId })
    app.showToast('设备已下线', 'success')
    fetchSessions()
  } catch (err) {
    app.showToast(err.message || '操作失败', 'error')
  }
}

async function handleRedeemGiftCard() {
  if (!giftCardCode.value) return
  redeeming.value = true
  try {
    await redeemGiftCard({ code: giftCardCode.value })
    app.showToast('兑换成功！余额已更新', 'success')
    giftCardCode.value = ''
    user.fetchAll()
  } catch (err) {
    app.showToast(err.message || '兑换失败或礼品卡无效', 'error')
  } finally {
    redeeming.value = false
  }
}

async function handleBindTelegram() {
  bindingTg.value = true
  try {
    const res = await getTelegramBotInfo()
    const botInfo = objectFromPayload(res)
    const botUrl = botInfo.bot_url || (botInfo.username ? `https://t.me/${botInfo.username}` : '')
    if (botUrl) {
      window.open(botUrl, '_blank')
    } else {
      throw new Error('未获取到 Bot 信息')
    }
  } catch (err) {
    app.showToast(err.message || '获取TG绑定信息失败', 'error')
  } finally {
    bindingTg.value = false
  }
}

async function updateUserSettings() {
  try {
    await updateUser({
      remind_expire: notifForm.remind_expire ? 1 : 0,
      remind_traffic: notifForm.remind_traffic ? 1 : 0
    })
    app.showToast('通知设置已更新', 'success')
    user.fetchAll()
  } catch (err) {
    app.showToast(err.message || '设置更新失败', 'error')
  }
}

async function handlePasswordChange() {
  if (pwdForm.new_password !== pwdForm.confirm_password) {
    return app.showToast('两次输入的新密码不一致', 'warning')
  }
  if (pwdForm.new_password.length < 8) {
    return app.showToast('新密码长度不能少于8位', 'warning')
  }
  
  pwdLoading.value = true
  try {
    await changePassword({
      old_password: pwdForm.old_password,
      new_password: pwdForm.new_password
    })
    app.showToast('密码修改成功，请妥善保管', 'success')
    pwdForm.old_password = ''
    pwdForm.new_password = ''
    pwdForm.confirm_password = ''
  } catch (err) {
    app.showToast(err.message || '密码修改失败', 'error')
  } finally {
    pwdLoading.value = false
  }
}

async function handleResetSecurity() {
  if (!confirm('确定要重置订阅链接吗？之前的链接将永久失效！')) return
  resetting.value = true
  try {
    await resetSecurity()
    app.showToast('重置成功！请前往主页复制新链接', 'success')
    user.fetchAll()
  } catch (err) {
    app.showToast(err.message || '重置失败', 'error')
  } finally {
    resetting.value = false
  }
}
</script>

<style scoped>
.profile-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.page-title {
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
}
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
@media (max-width: 768px) {
  .grid-2 {
    grid-template-columns: 1fr;
  }
}

.flex-col {
  display: flex;
  flex-direction: column;
}
.gap-4 { gap: 16px; }
.gap-2 { gap: 8px; }
.flex { display: flex; }
.align-center { align-items: center; }
.justify-between { justify-content: space-between; }
.flex-shrink-0 { flex-shrink: 0; }
.w-full { width: 100%; }

.border-bottom {
  border-bottom: 1px solid var(--c-border);
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

.info-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.info-label {
  font-size: var(--fs-xs);
  color: var(--c-text-3);
}
.info-value {
  font-size: var(--fs-base);
  font-weight: var(--fw-medium);
  color: var(--c-text-1);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.form-label {
  font-size: var(--fs-sm);
  color: var(--c-text-2);
}
.form-input {
  width: 100%;
  padding: 12px 16px;
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
  color: var(--c-text-1);
  outline: none;
  transition: all var(--t-fast);
}
.form-input:focus {
  border-color: var(--c-primary);
  background: var(--c-bg-base);
}

/* Switch */
.switch { position: relative; display: inline-block; width: 40px; height: 22px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--c-border); transition: .3s; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; }
input:checked + .slider { background-color: var(--c-primary); }
input:checked + .slider:before { transform: translateX(18px); }
.slider.round { border-radius: 22px; }
.slider.round:before { border-radius: 50%; }

/* Table */
.table-responsive { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; min-width: 760px; }
.table th, .table td { padding: 12px 16px; border-bottom: 1px solid var(--c-border); text-align: left; }
.table th { color: var(--c-text-3); font-size: var(--fs-xs); font-weight: var(--fw-medium); }
.table th[align="right"], .table td[align="right"] { text-align: right; }
.table tbody tr:hover { background: var(--c-bg-elevated); }
.session-row--current td {
  background: var(--c-primary-light);
}
.session-device {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.session-device__name {
  font-weight: var(--fw-semibold);
  color: var(--c-text-1);
}
.session-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: var(--r-full);
  background: var(--c-bg-elevated);
  color: var(--c-text-3);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
  white-space: nowrap;
}
.session-badge--current {
  color: var(--c-success);
  background: rgba(34, 197, 94, 0.12);
}
.session-agent {
  max-width: 320px;
  margin-top: 4px;
  color: var(--c-text-3);
  font-size: var(--fs-xs);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.session-ip {
  font-weight: var(--fw-semibold);
  color: var(--c-text-1);
  white-space: nowrap;
}
.session-ip--empty {
  color: var(--c-text-3);
  font-weight: var(--fw-medium);
}

.pt-1 { padding-top: 4px; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }
.px-2 { padding-left: 8px; padding-right: 8px; }
.text-center { text-align: center; }
.btn--xs { padding: 4px 8px; font-size: var(--fs-xs); }

</style>

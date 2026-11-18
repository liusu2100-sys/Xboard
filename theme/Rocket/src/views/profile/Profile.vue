<template>
  <div class="page profile-page">
    
    <div class="profile-grid">
      <!-- 左侧列 -->
      <div class="flex-col gap-4">
        
        <!-- 1. 我的钱包 -->
        <div class="card p-0 anim-fade-up wallet-card">
          <div class="wallet-bg"></div>
          <div class="p-6 relative z-10">
            <div class="flex justify-between">
              <div>
                <h3 class="text-1 font-bold mb-1" style="font-size: 1.1rem;">我的钱包</h3>
                <div class="wallet-balance">
                  <span class="currency">¥</span>
                  <span class="amount">{{ ((user.info?.balance || 0) / 100 + (stat[4] || 0) / 100).toFixed(2) }}</span>
                </div>
                <p class="text-xs text-3 mt-1">包含可用钱包余额和佣金钱包余额</p>
              </div>
              <div class="wallet-stats">
                <div class="stat-item">
                  <div class="stat-label">账户余额</div>
                  <div class="stat-val">{{ ((user.info?.balance || 0) / 100).toFixed(2) }}</div>
                </div>
                <div class="stat-item">
                  <div class="stat-label">佣金余额</div>
                  <div class="stat-val">{{ ((stat[4] || 0) / 100).toFixed(2) }}</div>
                </div>
              </div>
            </div>
            <div class="wallet-actions mt-6">
              <button class="btn btn--primary" @click="showRechargeModal = true">充值</button>
              <button class="btn btn--outline" @click="openTransferModal">划转</button>
              <button class="btn btn--outline" @click="openWithdrawModal" :disabled="withdrawClosed">提现</button>
            </div>
          </div>
        </div>

        <!-- 2. 重置密码 -->
        <div class="card p-0 anim-fade-up" style="animation-delay: 0.1s">
          <div class="p-6 flex-col items-center">
            <h3 class="text-1 font-bold w-full text-left mb-4" style="font-size: 1.25rem; font-weight: 800;">重置密码</h3>
            
            <div class="avatar-wrap">
              <span class="avatar-text">{{ avatarInitial }}</span>
            </div>
            <div class="text-1 font-bold mt-3" style="font-size: 1.25rem;">{{ displayName }}</div>
            <div class="text-3 text-sm mt-1 mb-6">{{ user.info?.email }}</div>

            <!-- Tabs -->
            <div class="pwd-tabs">
              <button class="pwd-tab" :class="{ active: pwdTab === 'old' }" @click="pwdTab = 'old'">修改密码 (旧密码验证)</button>
              <button class="pwd-tab" :class="{ active: pwdTab === 'email' }" @click="pwdTab = 'email'">修改密码 (邮箱验证)</button>
            </div>

            <!-- Form -->
            <form @submit.prevent="handlePasswordChange" class="w-full mt-4 pwd-form">
              <template v-if="pwdTab === 'old'">
                <div class="row-input">
                  <label><span class="required-star">*</span> 输入原密码:</label>
                  <div class="input-with-icon">
                    <input :type="showOldPwd ? 'text' : 'password'" v-model="pwdForm.old_password" class="form-input minimal-input" placeholder="请输入您的原有登录密码" required>
                    <Icon :name="showOldPwd ? 'eye' : 'eye-off'" :size="16" class="text-3 eye-icon" @click="showOldPwd = !showOldPwd" />
                  </div>
                </div>
                <div class="row-input">
                  <label><span class="required-star">*</span> 输入新密码:</label>
                  <div class="input-with-icon">
                    <input :type="showNewPwd ? 'text' : 'password'" v-model="pwdForm.new_password" class="form-input minimal-input" placeholder="请输入你的新登录密码" required>
                    <Icon :name="showNewPwd ? 'eye' : 'eye-off'" :size="16" class="text-3 eye-icon" @click="showNewPwd = !showNewPwd" />
                  </div>
                </div>
                <div class="row-input">
                  <label><span class="required-star">*</span> 确认新密码:</label>
                  <div class="input-with-icon">
                    <input :type="showConfirmPwd ? 'text' : 'password'" v-model="pwdForm.confirm_password" class="form-input minimal-input" placeholder="请再次输入新登录密码" required>
                    <Icon :name="showConfirmPwd ? 'eye' : 'eye-off'" :size="16" class="text-3 eye-icon" @click="showConfirmPwd = !showConfirmPwd" />
                  </div>
                </div>
              </template>
              <template v-else>
                <!-- Email verification form mockup -->
                <p class="text-3 text-sm mb-4 text-center">系统将向您的邮箱发送验证码</p>
                <div class="row-input">
                  <label><span class="required-star">*</span> 邮箱验证码:</label>
                  <div class="input-with-icon">
                    <input type="text" v-model="pwdForm.email_code" class="form-input minimal-input" placeholder="请输入验证码" required>
                    <button type="button" class="btn btn--secondary btn--xs" style="margin-left:8px; position:absolute; right: 4px; top: 4px; bottom: 4px;">发送</button>
                  </div>
                </div>
                <div class="row-input">
                  <label><span class="required-star">*</span> 输入新密码:</label>
                  <div class="input-with-icon">
                    <input :type="showNewPwd ? 'text' : 'password'" v-model="pwdForm.new_password" class="form-input minimal-input" placeholder="请输入你的新登录密码" required>
                    <Icon :name="showNewPwd ? 'eye' : 'eye-off'" :size="16" class="text-3 eye-icon" @click="showNewPwd = !showNewPwd" />
                  </div>
                </div>
              </template>
              
              <button type="submit" class="btn btn--primary w-full mt-6" :disabled="pwdLoading">
                <span v-if="pwdLoading" class="anim-spin mr-1">⟳</span> 重置密码
              </button>
            </form>
          </div>
        </div>

      </div>

      <!-- 右侧列 -->
      <div class="flex-col gap-4">
        
        <!-- 3. 我的订阅 -->
        <div class="card p-0 anim-fade-up sub-card">
          <div class="sub-bg"></div>
          <div class="p-6 relative z-10 flex-col justify-between" style="height: 100%">
            <div>
              <h3 class="text-1 font-bold mb-2" style="font-size: 1.1rem;">我的订阅</h3>
              <div class="sub-title">{{ planName }}</div>
            </div>
            
            <div class="sub-details mt-8">
              <div class="detail-row">流量重置时间: <span class="text-1">{{ isLifetime ? '无需重置' : '无需重置' }}</span></div>
              <div class="detail-row mt-2">到期时间: <span class="text-1">{{ expireDate }}</span></div>
            </div>
          </div>
        </div>

        <!-- 4. 订阅中心 -->
        <div class="card p-0 anim-fade-up" style="animation-delay: 0.15s">
          <div class="p-6">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-1 font-bold" style="font-size: 1.1rem;">订阅中心</h3>
              <router-link v-if="planName === 'Free 无订阅' || !user.subscription" to="/shop" class="btn btn--danger btn--xs" style="border-radius:12px; padding: 4px 12px">重新订阅</router-link>
            </div>
            
            <div class="flex align-start gap-6">
              <div class="flex-1">
                <div class="text-xs text-3 mb-2">订阅链接</div>
                <div class="subscribe-link-box">
                  <input type="text" readonly :value="subUrl" class="sub-input" />
                  <button class="copy-btn" @click="copySubLink" title="复制订阅链接">
                    <Icon name="copy" :size="16" />
                  </button>
                </div>
                
                <div class="text-xs text-3 mt-8 mb-4">快速导入到第三方客户端</div>
                <div class="quick-import-icons">
                  <button class="import-icon" title="Clash" @click="quickImport('clash')"><Icon name="shield" :size="22" /></button>
                  <button class="import-icon" title="Shadowrocket" @click="quickImport('shadowrocket')"><Icon name="zap" :size="22" /></button>
                  <button class="import-icon" title="SingBox" @click="quickImport('singbox')"><Icon name="database" :size="22" /></button>
                  <button class="import-icon" title="Surfboard" @click="quickImport('surfboard')"><Icon name="globe" :size="22" /></button>
                  <button class="import-icon" title="Surge" @click="quickImport('surge')"><Icon name="activity" :size="22" /></button>
                  <button class="import-icon" title="Quantumult" @click="quickImport('quantumult')"><Icon name="grid" :size="22" /></button>
                </div>
              </div>
              
              <div class="qr-code-section text-center">
                <div class="qr-wrapper">
                  <img v-if="qrUrl" :src="qrUrl" alt="QR Code" />
                  <div v-else class="qr-placeholder">加载中...</div>
                </div>
                <p class="text-xs text-3 mt-2" style="max-width: 150px">扫描二维码导入订阅到 Shadowrocket / Clash 等客户端</p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- 其余设置项 -->
    <div class="profile-grid mt-4">
      <div class="flex-col gap-4">
        <!-- 通知提醒 -->
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
      </div>
      <div class="flex-col gap-4">
         <!-- Telegram 绑定 -->
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
    </div>

    <!-- Subscription Devices -->
    <div class="card p-0 mt-4 anim-fade-up" style="animation-delay: 0.35s">
      <div class="card__header p-4 border-bottom">
        <Icon name="activity" :size="20" class="text-primary" />
        <h3 class="card__title">订阅设备</h3>
        <button class="btn btn--secondary btn--sm" @click="fetchSubscriptionDevices" :disabled="devicesLoading">刷新</button>
      </div>
      <div class="p-4">
        <div v-if="devicesLoading" class="text-center text-3 py-4">正在加载订阅设备...</div>
        <div v-else-if="subscriptionDevices.length === 0" class="text-center text-3 py-4">暂无订阅设备，客户端首次更新订阅后会显示在这里</div>
        <div v-else class="table-responsive">
          <table class="table device-table">
            <thead>
              <tr>
                <th>设备 / 客户端</th>
                <th>IP / 地区</th>
                <th>连接状态</th>
                <th>本月流量</th>
                <th>首次加入</th>
                <th>最后活动</th>
                <th align="right">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="device in subscriptionDevices" :key="device.id">
                <td>
                  <div class="session-device">
                    <span class="session-device__name">{{ device.name || '未知订阅设备' }}</span>
                    <span v-if="device.identity_source !== 'device_id'" class="session-badge">IP 识别</span>
                  </div>
                  <div class="session-agent">{{ deviceClientLabel(device) }}</div>
                </td>
                <td>
                  <div class="session-ip">{{ device.ip || '未记录' }}</div>
                  <div class="session-agent">{{ device.region || '地区未知' }}</div>
                </td>
                <td>
                  <span v-if="device.blocked_until" class="device-state device-state--blocked">下线中</span>
                  <span v-else-if="device.is_online" class="device-state device-state--online">在线</span>
                  <span v-else class="device-state">离线</span>
                  <div class="session-agent">{{ deviceNodeLabel(device) }}</div>
                </td>
                <td>
                  <template v-if="device.traffic_supported">
                    <div class="device-traffic">{{ formatBytes((device.traffic?.month_u || 0) + (device.traffic?.month_d || 0)) }}</div>
                    <div class="session-agent">↑ {{ formatBytes(device.traffic?.month_u || 0) }} / ↓ {{ formatBytes(device.traffic?.month_d || 0) }}</div>
                  </template>
                  <span v-else class="text-xs text-3">节点暂未上报</span>
                </td>
                <td class="text-sm">{{ formatDate(device.first_seen_at) }}</td>
                <td class="text-sm">{{ formatDate(device.last_seen_at) }}</td>
                <td align="right">
                  <button
                    v-if="device.is_online && !device.blocked_until"
                    class="btn btn--secondary btn--xs text-danger"
                    :disabled="offliningDeviceId === device.id"
                    @click="handleOfflineDevice(device)"
                  >{{ offliningDeviceId === device.id ? '下线中...' : '设备下线' }}</button>
                  <span v-else class="text-xs text-3">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Active Sessions (Full Width) -->
    <div class="card p-0 mt-4 anim-fade-up" style="animation-delay: 0.4s">
       <div class="card__header p-4 border-bottom">
          <Icon name="monitor" :size="20" class="text-primary" />
          <h3 class="card__title">账号登录会话</h3>
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

    <!-- Modals -->
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

    <!-- Recharge Modal -->
    <div v-if="showRechargeModal" class="modal-overlay" @click.self="showRechargeModal = false">
      <div class="modal-content recharge-modal">
        <div class="modal-header border-0">
          <h3 class="text-1 font-bold">余额充值</h3>
          <button class="close-btn" @click="showRechargeModal = false">
            <Icon name="x" :size="20" />
          </button>
        </div>
        <div class="modal-body pt-0">
          <div class="transfer-card">
            <div class="transfer-info">
              <div class="transfer-amount"><span class="currency">¥</span>{{ ((user.info?.balance || 0) / 100).toFixed(2) }}</div>
              <div class="transfer-label">当前余额</div>
            </div>
            <div class="transfer-icon"><Icon name="credit-card" :size="28" /></div>
            <div class="transfer-info right-align">
              <div class="transfer-amount"><span class="currency">¥</span>{{ (((user.info?.balance || 0) / 100) + Number(rechargeAmount || 0) + currentBonus).toFixed(2) }}</div>
              <div class="transfer-label">充值后余额</div>
            </div>
          </div>

          <div class="transfer-input-wrap mt-4 mb-4">
            <span class="prefix">充值金额</span>
            <input type="number" v-model="rechargeAmount" class="transfer-input" placeholder="0.00" min="0" step="1">
          </div>

          <!-- Preset amounts -->
          <div class="recharge-presets">
            <div v-for="preset in presetRecharges" :key="preset.amount" 
                 class="preset-item" 
                 :class="{ active: Number(rechargeAmount) === preset.amount }"
                 @click="rechargeAmount = preset.amount">
              <div class="preset-amount"><span class="currency">¥</span> {{ preset.amount.toFixed(2) }}</div>
              <div v-if="preset.bonus > 0" class="preset-bonus">送{{ preset.bonus }}</div>
            </div>
          </div>

          <button class="btn btn--primary btn--block mt-6" @click="handleRecharge" :disabled="recharging || !rechargeAmount || rechargeAmount <= 0">
            <span v-if="recharging" class="anim-spin" style="margin-right: 8px">⟳</span>
            立即充值
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { reactive, ref, onMounted, watch, computed } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import { listFromPayload, objectFromPayload } from '@/api/normalizers'
import { copyText } from '@/utils/clipboard'
import { 
  changePassword, resetSecurity, getTelegramBotInfo, 
  getActiveSession, removeActiveSession, redeemGiftCard, updateUser,
  getStat, transfer, withdrawTicket, getSubscribeQrCode, getSubscribe,
  getSubscriptionDevices, offlineSubscriptionDevice
} from '@/api/user'

const user = useUserStore()
const app = useAppStore()

const pwdLoading = ref(false)
const resetting = ref(false)
const redeeming = ref(false)
const bindingTg = ref(false)
const sessionsLoading = ref(false)
const devicesLoading = ref(false)
const offliningDeviceId = ref(null)

const pwdTab = ref('old')
const pwdForm = reactive({
  old_password: '',
  new_password: '',
  confirm_password: '',
  email_code: ''
})

const showOldPwd = ref(false)
const showNewPwd = ref(false)
const showConfirmPwd = ref(false)

const notifForm = reactive({
  remind_expire: false,
  remind_traffic: false
})

const giftCardCode = ref('')
const sessions = ref([])
const subscriptionDevices = ref([])

// My Wallet & Modals
const stat = ref([0, 0, 0, 0, 0])
const withdrawClosed = computed(() => !window.settings?.withdraw_methods || window.settings.withdraw_methods.length === 0)
const withdrawMethods = computed(() => window.settings?.withdraw_methods || ['USDT', 'Alipay'])

const showTransferModal = ref(false)
const transferAmount = ref('')
const transferring = ref(false)

const showWithdrawModal = ref(false)
const withdrawForm = reactive({ method: 'USDT', account: '' })
const withdrawing = ref(false)

const showRechargeModal = ref(false)
const rechargeAmount = ref(500)
const recharging = ref(false)

const presetRecharges = [
  { amount: 30, bonus: 0 },
  { amount: 50, bonus: 0 },
  { amount: 100, bonus: 20 },
  { amount: 200, bonus: 50 },
  { amount: 300, bonus: 100 },
  { amount: 500, bonus: 200 }
]

const currentBonus = computed(() => {
  const amount = Number(rechargeAmount.value) || 0
  const preset = presetRecharges.find(p => p.amount === amount)
  return preset ? preset.bonus : 0
})

// Subscription Info
const displayName = computed(() => user.info?.email ? user.info.email.split('@')[0] : 'User')
const avatarInitial = computed(() => {
  const source = displayName.value || user.info?.email || 'U'
  return String(source).trim().charAt(0).toUpperCase()
})
const planName = computed(() => user.subscription?.plan?.name || 'Free 无订阅')
const isLifetime = computed(() => user.subscription?.reset_day === null)
const expireDate = computed(() => {
  if (!user.subscription?.expired_at) return '永久有效'
  return new Date(user.subscription.expired_at * 1000).toLocaleDateString()
})

const subUrl = ref('')
const qrUrl = ref('')
const qrContent = ref('')

async function loadSubInfo() {
  try {
    const res = await getSubscribe()
    const data = objectFromPayload(res)
    subUrl.value = data.subscribe_url || ''

    if (subUrl.value) {
      const qrRes = await getSubscribeQrCode({
        target: 'flclash',
        mode: 'subscribe',
        size: 320,
        use_current_host: 1
      })
      const qrData = objectFromPayload(qrRes, null)
      qrUrl.value = qrData?.image || ''
      qrContent.value = qrData?.content || qrData?.subscribe_url || appendSubscribeFlag(subUrl.value, 'flclash')
    }
  } catch (err) {
    console.error('Failed to load subscription info', err)
  }
}

async function fetchAllStats() {
  try {
    const res = await getStat()
    if (res && res.data) {
      stat.value = res.data
    }
  } catch (err) {
    console.error(err)
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
    await transfer({ transfer_amount: transferAmount.value })
    app.showToast('划转成功', 'success')
    showTransferModal.value = false
    await fetchAllStats()
    user.fetchAll()
  } catch (err) {
    app.showToast(err.message || '划转失败', 'error')
  } finally {
    transferring.value = false
  }
}

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
    await fetchAllStats()
    user.fetchAll()
  } catch (err) {
    app.showToast(err.message || '提现申请失败', 'error')
  } finally {
    withdrawing.value = false
  }
}

async function handleRecharge() {
  if (!rechargeAmount.value || rechargeAmount.value <= 0) return
  recharging.value = true
  try {
    // Simulated Recharge API handling. Real implementation needs order creation endpoint.
    await new Promise(resolve => setTimeout(resolve, 800))
    app.showToast('当前未绑定在线充值支付网关，请联系管理员配置。', 'warning')
  } catch (err) {
    app.showToast('充值发起失败', 'error')
  } finally {
    recharging.value = false
  }
}

async function copySubLink() {
  if (!subUrl.value) return
  try {
    await copyText(subUrl.value)
    app.showToast('订阅链接已复制', 'success')
  } catch (err) {
    app.showToast('复制失败', 'error')
  }
}

function quickImport(client) {
  if (!subUrl.value) return
  const flaggedUrl = {
    clash: appendSubscribeFlag(subUrl.value, 'clash'),
    shadowrocket: appendSubscribeFlag(subUrl.value, 'shadowrocket'),
    singbox: appendSubscribeFlag(subUrl.value, 'sing-box'),
    surge: appendSubscribeFlag(subUrl.value, 'surge'),
    surfboard: appendSubscribeFlag(subUrl.value, 'surfboard'),
    quantumult: appendSubscribeFlag(subUrl.value, 'quantumult-x')
  }[client] || subUrl.value
  const url = encodeURIComponent(flaggedUrl)
  let target = ''
  switch(client) {
    case 'clash': target = `clash://install-config?url=${url}`; break;
    case 'shadowrocket': target = `shadowrocket://add/sub://${btoa(flaggedUrl)}`; break;
    case 'v2ray': target = `v2rayng://install-config?url=${url}`; break;
    case 'singbox': target = `sing-box://import-remote-profile?url=${url}`; break;
    case 'surge': target = `surge:///install-config?url=${url}`; break;
    case 'surfboard': target = `surfboard:///install-config?url=${url}`; break;
    case 'quantumult': target = `quantumult-x:///update-configuration?remote-resource=${url}`; break;
  }
  if (target) window.open(target)
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

function rawSessionIp(session) { return session.ip || session.ip_address || session.client_ip || '' }
function hasSessionIp(session) { return !!rawSessionIp(session) }
function sessionIp(session) { return rawSessionIp(session) || (isLegacySession(session) ? '旧会话未记录' : '未记录') }

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

function sessionAgent(session) { return session.user_agent || session.agent || '未记录 User-Agent' }
function sessionLoginDate(session) { return formatDate(session.login_at || session.created_at) }
function sessionDate(session) { return formatDate(session.last_activity || session.last_used_at || session.updated_at || session.created_at) }
function isCurrentSession(session) { return session.is_current === true || session.is_current === 1 || session.is_current === '1' }
function isLegacySession(session) { return session.is_legacy === true || session.is_legacy === 1 || session.is_legacy === '1' }

function formatBytes(bytes) {
  const value = Number(bytes) || 0
  if (value <= 0) return '0 B'
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const index = Math.min(Math.floor(Math.log(value) / Math.log(1024)), sizes.length - 1)
  return `${(value / Math.pow(1024, index)).toFixed(index > 1 ? 2 : 1)} ${sizes[index]}`
}

function deviceClientLabel(device) {
  return [device.client_name, device.client_version, device.model || device.platform].filter(Boolean).join(' / ') || '客户端信息待节点上报'
}

function deviceNodeLabel(device) {
  const names = (device.nodes || []).map(node => node.name).filter(Boolean)
  return names.length ? names.join('、') : (device.last_node || '暂无在线节点')
}

watch(() => user.info, (info) => {
  if (!info) return
  notifForm.remind_expire = !!info.remind_expire
  notifForm.remind_traffic = !!info.remind_traffic
}, { immediate: true })

onMounted(async () => {
  if (!user.info) user.fetchAll()
  fetchSessions()
  fetchSubscriptionDevices()
  fetchAllStats()
  loadSubInfo()
})

async function fetchSubscriptionDevices() {
  devicesLoading.value = true
  try {
    const res = await getSubscriptionDevices()
    subscriptionDevices.value = listFromPayload(res)
  } catch (err) {
    app.showToast(err.message || '获取订阅设备失败', 'error')
  } finally {
    devicesLoading.value = false
  }
}

async function handleOfflineDevice(device) {
  if (!confirm(`确定让“${device.name || '该设备'}”下线吗？设备将在 10 分钟内无法重新接入。`)) return
  offliningDeviceId.value = device.id
  try {
    const res = await offlineSubscriptionDevice({ device_id: device.id })
    const data = objectFromPayload(res)
    app.showToast(data.message || '设备下线指令已提交', data.enforcement === 'panel_only' ? 'warning' : 'success')
    await fetchSubscriptionDevices()
  } catch (err) {
    app.showToast(err.message || '设备下线失败', 'error')
  } finally {
    offliningDeviceId.value = null
  }
}

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

async function handleBindTelegram() {
  bindingTg.value = true
  try {
    const res = await getTelegramBotInfo()
    const botInfo = objectFromPayload(res)
    const botUrl = botInfo.bot_url || (botInfo.username ? `https://t.me/${botInfo.username}` : '')
    if (botUrl) window.open(botUrl, '_blank')
    else throw new Error('未获取到 Bot 信息')
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
  if (pwdTab.value === 'old') {
    if (pwdForm.new_password !== pwdForm.confirm_password) return app.showToast('两次输入的新密码不一致', 'warning')
  }
  if (pwdForm.new_password.length < 8) return app.showToast('新密码长度不能少于8位', 'warning')
  
  pwdLoading.value = true
  try {
    // Only implemented old_password for now as standard Xboard API doesn't natively expose changePassword via email code in this context
    await changePassword({
      old_password: pwdForm.old_password,
      new_password: pwdForm.new_password
    })
    app.showToast('密码修改成功，请妥善保管', 'success')
    pwdForm.old_password = ''
    pwdForm.new_password = ''
    pwdForm.confirm_password = ''
    pwdForm.email_code = ''
  } catch (err) {
    app.showToast(err.message || '密码修改失败', 'error')
  } finally {
    pwdLoading.value = false
  }
}
</script>

<style scoped>
.profile-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.profile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
@media (max-width: 768px) {
  .profile-grid { grid-template-columns: 1fr; }
}

.flex-col { display: flex; flex-direction: column; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.align-start { align-items: flex-start; }
.gap-4 { gap: 16px; }
.gap-6 { gap: 24px; }
.w-full { width: 100%; }
.mt-1 { margin-top: 4px; }
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }
.mt-6 { margin-top: 24px; }
.mt-8 { margin-top: 32px; }
.mb-1 { margin-bottom: 4px; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-6 { margin-bottom: 24px; }
.relative { position: relative; }
.z-10 { z-index: 10; }
.flex-1 { flex: 1; }

/* Wallet Card */
.wallet-card, .sub-card {
  position: relative;
  overflow: hidden;
  border-radius: 12px;
}
.wallet-bg {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: linear-gradient(135deg, rgba(20, 184, 166, 0.15) 0%, rgba(20, 184, 166, 0) 60%);
  pointer-events: none;
}
.sub-bg {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: linear-gradient(135deg, rgba(45, 212, 191, 0.15) 0%, rgba(45, 212, 191, 0) 70%);
  pointer-events: none;
}

.wallet-balance {
  font-size: 2.2rem;
  font-weight: 800;
  color: var(--c-text-1);
  display: flex;
  align-items: baseline;
  gap: 4px;
}
.wallet-balance .currency {
  font-size: 1.2rem;
  font-weight: 600;
}
.wallet-stats {
  display: flex;
  gap: 20px;
  text-align: right;
}
.stat-item .stat-label {
  font-size: 0.85rem;
  color: var(--c-text-3);
  margin-bottom: 2px;
}
.stat-item .stat-val {
  font-size: 1rem;
  font-weight: 700;
  color: var(--c-text-1);
}
.wallet-actions {
  display: flex;
  gap: 12px;
}
.wallet-actions .btn {
  border-radius: 6px;
  padding: 8px 24px;
}
.btn--primary {
  background: #26c2a3;
  color: #fff;
  border: none;
}
.btn--primary:hover {
  background: #1fa48a;
}

/* Sub Card */
.sub-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--c-text-1);
  margin-top: 12px;
}

/* Password Card */
.avatar-wrap {
  width: 64px; height: 64px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--c-bg-hover);
  display: flex; align-items: center; justify-content: center;
}
.avatar-text { font-size: 24px; font-weight: bold; color: var(--c-primary); }
.pwd-tabs {
  display: flex;
  gap: 20px;
  border-bottom: 1px solid var(--c-border);
  width: 100%;
  margin-bottom: 20px;
}
.pwd-tab {
  padding: 8px 0;
  font-size: 0.95rem;
  color: var(--c-text-3);
  font-weight: 500;
  position: relative;
  transition: color var(--t-fast);
}
.pwd-tab.active {
  color: #26c2a3;
}
.pwd-tab.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0; right: 0;
  height: 2px;
  background: #26c2a3;
}
.row-input {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}
.row-input label {
  width: 110px;
  font-size: 0.95rem;
  color: var(--c-text-1);
  white-space: nowrap;
  display: flex;
  align-items: center;
}
.required-star {
  color: #ef4444;
  margin-right: 4px;
}
.pwd-form {
  max-width: 480px;
  margin: 0 auto;
}
.minimal-input {
  flex: 1;
  background: var(--c-bg-input);
  border: 1px solid var(--c-border);
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 0.95rem;
  outline: none;
  width: 100%;
  padding-right: 36px;
}
.minimal-input:focus { border-color: #26c2a3; }

.input-with-icon {
  position: relative;
  flex: 1;
  display: flex;
}
.eye-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  cursor: pointer;
  color: var(--c-text-3);
}
.eye-icon:hover { color: var(--c-text-2); }

/* Sub Center Card */
.subscribe-link-box {
  display: flex;
  border: 1px solid var(--c-border);
  border-radius: 8px;
  overflow: hidden;
  background: var(--c-bg-input);
}
.sub-input {
  flex: 1;
  border: none;
  background: transparent;
  padding: 10px 14px;
  font-size: 0.9rem;
  color: var(--c-text-1);
  outline: none;
}
.copy-btn {
  padding: 0 14px;
  color: var(--c-text-2);
  background: var(--c-bg-hover);
  border-left: 1px solid var(--c-border);
}
.copy-btn:hover { color: var(--c-text-1); }
.quick-import-icons {
  display: flex;
  gap: 12px;
}
.import-icon {
  width: 48px; height: 48px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  border: none;
  background: var(--c-bg-hover);
  color: var(--c-text-2);
  padding: 8px;
  cursor: pointer;
  transition: color var(--t-fast), background var(--t-fast), transform var(--t-fast);
}
.import-icon:hover {
  background: var(--c-primary-soft);
  color: var(--c-primary);
  transform: translateY(-1px);
}
.qr-wrapper {
  width: 120px; height: 120px;
  background: var(--c-bg-input);
  border: 1px solid var(--c-border);
  border-radius: 8px;
  padding: 8px;
  margin: 0 auto;
}
.qr-wrapper img { width: 100%; height: 100%; }
.qr-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  color: var(--c-text-3); font-size: 0.85rem;
}

/* Modals */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-content {
  background: var(--c-bg-card);
  border-radius: var(--r-xl);
  width: 100%;
  max-width: 480px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--c-border);
  overflow: hidden;
  animation: modalIn 0.3s ease;
}
.recharge-modal { max-width: 520px; }
@keyframes modalIn {
  from { opacity: 0; transform: translateY(20px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
}
.modal-body { padding: 24px; }
.border-0 { border: none !important; }
.pt-0 { padding-top: 0 !important; }

/* Transfer / Withdraw Card styles (reused) */
.transfer-card {
  background: var(--c-bg-hover);
  border-radius: 12px;
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.transfer-info { display: flex; flex-direction: column; gap: 4px; }
.transfer-info.right-align { align-items: flex-end; }
.transfer-amount { font-size: 1.5rem; font-weight: 800; color: var(--c-text-1); }
.transfer-amount .currency { font-size: 1rem; font-weight: 600; margin-right: 4px; }
.transfer-label { font-size: 0.85rem; color: var(--c-text-3); }
.transfer-icon { color: var(--c-text-1); }

.transfer-input-wrap {
  display: flex;
  align-items: center;
  background: var(--c-bg-input);
  border: 1px solid var(--c-border);
  border-radius: 8px;
  padding: 0;
  overflow: hidden;
}
.transfer-input-wrap .prefix {
  padding: 12px 16px;
  color: var(--c-text-2);
  font-size: 0.95rem;
  background: var(--c-bg-input);
}
.transfer-input {
  flex: 1;
  border: none;
  outline: none;
  padding: 12px 0;
  font-size: 1rem;
  color: var(--c-text-1);
  min-width: 0;
  background: var(--c-bg-input);
}
.btn-max {
  background: transparent;
  border: none;
  border-left: 1px solid var(--c-border);
  padding: 12px 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-max:hover { background: var(--c-bg-hover); }
.rotate-down-left { transform: rotate(180deg); color: #fff; background: var(--c-text-1); border-radius: 50%; padding: 2px; }

.withdraw-card {
  background: var(--c-bg-hover);
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
  width: 32px; height: 32px; background: var(--c-text-1); color: var(--c-bg-card);
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  font-weight: bold; font-size: 1.2rem; margin-bottom: 12px;
}
.withdraw-amount { font-size: 2rem; font-weight: 800; color: var(--c-text-1); margin-bottom: 12px; }
.withdraw-amount .currency { font-size: 1.2rem; font-weight: 600; margin-right: 4px; }
.withdraw-hint { font-size: 0.85rem; color: var(--c-text-3); }

/* Recharge Presets */
.recharge-presets {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 12px;
}
.preset-item {
  position: relative;
  border: 1px solid var(--c-border);
  border-radius: 8px;
  padding: 16px 0;
  text-align: center;
  cursor: pointer;
  background: var(--c-bg-input);
  transition: all var(--t-fast);
}
.preset-item:hover {
  border-color: #26c2a3;
}
.preset-item.active {
  border-color: #26c2a3;
  background: rgba(38, 194, 163, 0.08);
}
.preset-amount {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--c-text-1);
}
.preset-amount .currency {
  font-size: 0.9rem;
  font-weight: 500;
}
.preset-bonus {
  position: absolute;
  top: -8px; right: -8px;
  background: #ff0000;
  color: #fff;
  font-size: 0.75rem;
  font-weight: bold;
  padding: 2px 6px;
  border-radius: 4px;
}

/* Other Utilities from Original Profile */
.select-wrapper { position: relative; }
.select-wrapper select { appearance: none; padding-right: 36px; }
.select-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--c-text-3); pointer-events: none; }
.btn--block { width: 100%; padding: 12px; font-size: 1rem; border-radius: 8px; }

/* Table */
.table-responsive { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; min-width: 760px; }
.table th, .table td { padding: 12px 16px; border-bottom: 1px solid var(--c-border); text-align: left; }
.table th { color: var(--c-text-3); font-size: var(--fs-xs); font-weight: var(--fw-medium); }
.table th[align="right"], .table td[align="right"] { text-align: right; }
.table tbody tr:hover { background: var(--c-bg-hover); }
.device-table { min-width: 1080px; }
.device-state { display: inline-flex; align-items: center; font-size: var(--fs-xs); font-weight: var(--fw-bold); color: var(--c-text-3); }
.device-state::before { content: ''; width: 7px; height: 7px; margin-right: 6px; border-radius: 50%; background: currentColor; }
.device-state--online { color: var(--c-success); }
.device-state--blocked { color: var(--c-danger); }
.device-traffic { color: var(--c-text-1); font-weight: var(--fw-bold); }

/* Switch */
.switch { position: relative; display: inline-block; width: 40px; height: 22px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--c-border); transition: .3s; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; }
input:checked + .slider { background-color: #26c2a3; }
input:checked + .slider:before { transform: translateX(18px); }
.slider.round { border-radius: 22px; }
.slider.round:before { border-radius: 50%; }
</style>

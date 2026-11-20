<template>
  <div class="page tickets-page">
    <div class="page-header">
      <h2 class="page-title">我的工单</h2>
      <button type="button" class="btn btn--primary btn--sm" @click.prevent="openCreateModal">
        <Icon name="plus" :size="16" />
        新建工单
      </button>
    </div>
    
    <div class="card p-0 overflow-hidden">
      <div v-if="loading" class="p-4">
        <div class="skeleton" style="height: 60px; margin-bottom: 10px" v-for="i in 3" :key="i"></div>
      </div>
      
      <div v-else-if="error" class="p-6 text-center text-danger">
        <Icon name="alert" :size="32" class="mb-2 mx-auto" />
        <p>{{ error }}</p>
      </div>
      
      <div v-else-if="tickets.length === 0" class="p-6 text-center">
        <Icon name="ticket" :size="48" class="text-3 mb-3 mx-auto" />
        <p class="text-2">暂无工单记录</p>
      </div>
      
      <div v-else class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>工单主题</th>
              <th>工单级别</th>
              <th>工单状态</th>
              <th>创建时间</th>
              <th style="width: 100px; text-align: center">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in tickets" :key="t.id" class="anim-fade-up">
              <td class="fw-medium text-1">{{ t.subject }}</td>
              <td>
                <span class="badge" :class="getLevelClass(t.level)">{{ getLevelName(t.level) }}</span>
              </td>
              <td>
                <span class="badge" :class="getStatusClass(t)">{{ getStatusName(t) }}</span>
              </td>
              <td>{{ formatDate(t.created_at) }}</td>
              <td style="text-align: center">
                <router-link :to="'/ticket/' + t.id" class="btn btn--secondary btn--sm">
                  查看详情
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Ticket Modal — Teleport to body to avoid stacking context issues -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="ticket-modal-overlay" @click.self="showCreateModal = false">
        <div class="ticket-modal">
          <div class="ticket-modal__header">
            <h3 class="ticket-modal__title">新建工单</h3>
            <button class="ticket-modal__close" @click="showCreateModal = false"><Icon name="x" :size="20" /></button>
          </div>
          <form @submit.prevent="handleCreateTicket" class="ticket-modal__body">
            <div class="ticket-form-group">
              <label class="ticket-form-label">工单主题</label>
              <input type="text" v-model="form.subject" class="ticket-form-input" placeholder="请输入简短的工单主题" required>
            </div>
            <div class="ticket-form-group">
              <label class="ticket-form-label">工单级别</label>
              <select v-model="form.level" class="ticket-form-input" required>
                <option :value="0">低 (一般问题)</option>
                <option :value="1">中 (连接问题)</option>
                <option :value="2">高 (支付或账号问题)</option>
              </select>
            </div>
            <div class="ticket-form-group">
              <label class="ticket-form-label">问题描述</label>
              <textarea v-model="form.message" class="ticket-form-input" rows="4" placeholder="请详细描述您遇到的问题" required></textarea>
            </div>
            
            <div class="ticket-modal__actions">
              <button type="button" class="btn btn--secondary" @click="showCreateModal = false">取消</button>
              <button type="submit" class="btn btn--primary" :disabled="submitting">
                <span v-if="submitting" class="anim-spin" style="margin-right: 8px">⟳</span>
                提交工单
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getTickets, saveTicket } from '@/api/user'
import { listFromPayload } from '@/api/normalizers'
import { useAppStore } from '@/stores/app'
import Icon from '@/components/ui/Icon.vue'

const app = useAppStore()
const loading = ref(true)
const error = ref('')
const tickets = ref([])

const showCreateModal = ref(false)
const submitting = ref(false)
const form = reactive({
  subject: '',
  level: 1,
  message: ''
})

function openCreateModal() {
  form.subject = ''
  form.level = 1
  form.message = ''
  showCreateModal.value = true
}

function getLevelName(level) {
  const map = { 0: '低', 1: '中', 2: '高' }
  return map[level] || '未知'
}
function getLevelClass(level) {
  const map = { 0: 'badge--info', 1: 'badge--warning', 2: 'badge--danger' }
  return map[level] || 'badge--secondary'
}

function getStatusName(ticket) {
  if (ticket.status === 1) return '已关闭'
  return ticket.reply_status === 0 ? '待回复' : '已回复'
}
function getStatusClass(ticket) {
  if (ticket.status === 1) return 'badge--secondary'
  return ticket.reply_status === 0 ? 'badge--warning' : 'badge--success'
}

function formatDate(ts) {
  if (!ts) return '-'
  return new Date(ts * 1000).toLocaleString('zh-CN', {
    month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit'
  })
}

async function fetchTickets() {
  loading.value = true
  error.value = ''
  try {
    const res = await getTickets()
    tickets.value = listFromPayload(res)
  } catch (err) {
    error.value = err.message || '获取工单失败'
  } finally {
    loading.value = false
  }
}

async function handleCreateTicket() {
  if (submitting.value) return
  submitting.value = true
  try {
    await saveTicket(form)
    app.showToast('工单提交成功', 'success')
    showCreateModal.value = false
    form.subject = ''
    form.message = ''
    fetchTickets()
  } catch (err) {
    app.showToast(err.message || '工单提交失败', 'error')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchTickets()
})
</script>

<style scoped>
.tickets-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>

<style>
/* Modal styles must be UNSCOPED because the modal is Teleported to <body> */
.ticket-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 20px;
  animation: ticketFadeIn 0.2s ease;
}
.ticket-modal {
  width: 100%;
  max-width: 500px;
  background: var(--c-bg-card);
  border-radius: var(--r-lg);
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  animation: ticketScaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
}
.ticket-modal__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--c-border);
}
.ticket-modal__title {
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
}
.ticket-modal__close {
  color: var(--c-text-3);
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  border-radius: var(--r-sm);
  transition: all var(--t-fast);
}
.ticket-modal__close:hover {
  color: var(--c-text-1);
  background: var(--c-bg-hover);
}
.ticket-modal__body {
  padding: 24px;
}
.ticket-form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}
.ticket-form-label {
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  color: var(--c-text-2);
}
.ticket-form-input {
  width: 100%;
  padding: 12px 16px;
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
  color: var(--c-text-1);
  outline: none;
  font-family: inherit;
  font-size: var(--fs-base);
  transition: all var(--t-fast);
}
.ticket-form-input:focus {
  border-color: var(--c-primary);
  background: var(--c-bg-base);
  box-shadow: 0 0 0 3px var(--c-primary-light);
}
.ticket-form-input::placeholder {
  color: var(--c-text-3);
}
.ticket-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 16px;
}

@keyframes ticketFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes ticketScaleUp {
  from { opacity: 0; transform: scale(0.95) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

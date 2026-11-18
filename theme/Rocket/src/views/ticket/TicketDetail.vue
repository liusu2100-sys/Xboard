<template>
  <div class="page ticket-detail-page">
    <div class="page-header">
      <div class="header-left">
        <button class="btn btn--secondary btn--icon mb-2" @click="router.back()">
          <Icon name="chevron-left" :size="20" />
          返回
        </button>
        <h2 class="page-title">{{ ticket?.subject || '加载中...' }}</h2>
        <div class="ticket-meta" v-if="ticket">
        <span class="badge" :class="getStatusClass(ticket)">{{ getStatusName(ticket) }}</span>
          <span class="text-3 ms-2">{{ formatDate(ticket.created_at) }}</span>
        </div>
      </div>
      <div>
        <button v-if="ticket && ticket.status !== 1" class="btn btn--danger btn--sm" @click="handleClose" :disabled="closing">
          <span v-if="closing" class="anim-spin" style="margin-right: 4px">⟳</span>
          关闭工单
        </button>
      </div>
    </div>
    
    <div v-if="loading" class="card p-4 mx-auto" style="max-width: 800px; width: 100%">
      <div class="skeleton" style="height: 100px; margin-bottom: 20px" v-for="i in 3" :key="i"></div>
    </div>
    
    <div v-else-if="error" class="card text-center text-danger mx-auto" style="max-width: 800px; width: 100%">
      <Icon name="alert" :size="32" class="mb-2 mx-auto" />
      <p>{{ error }}</p>
    </div>

    <div v-else class="chat-container">
      <div class="chat-messages">
        <!-- Ticket messages list -->
        <div v-for="msg in messages" :key="msg.id" class="chat-msg" :class="msg.is_me ? 'msg-me' : 'msg-reply'">
          <div class="msg-avatar">
            <Icon :name="msg.is_me ? 'user' : 'headphones'" :size="20" />
          </div>
          <div class="msg-content-wrapper">
            <div class="msg-info">
              <span class="msg-author">{{ msg.is_me ? '我' : '技术客服' }}</span>
              <span class="msg-time">{{ formatDate(msg.created_at) }}</span>
            </div>
            <div class="msg-bubble">
              {{ msg.message }}
            </div>
          </div>
        </div>
      </div>
      
      <div class="chat-input-area" v-if="ticket.status !== 1">
        <form @submit.prevent="handleReply" class="reply-form">
          <textarea v-model="replyText" class="form-input" rows="3" placeholder="输入回复内容..." required></textarea>
          <div class="reply-actions mt-3 text-right">
            <button type="submit" class="btn btn--primary" :disabled="replying || !replyText.trim()">
              <span v-if="replying" class="anim-spin" style="margin-right: 6px">⟳</span>
              <Icon name="send" :size="16" v-else style="margin-right: 6px" />
              发送回复
            </button>
          </div>
        </form>
      </div>
      <div v-else class="card text-center bg-elevated border-0 mt-4 p-4 text-3">
        此工单已关闭，无法继续回复。
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getTicketDetail, replyTicket, closeTicket } from '@/api/user'
import { objectFromPayload, listFromPayload } from '@/api/normalizers'
import { useAppStore } from '@/stores/app'
import Icon from '@/components/ui/Icon.vue'

const props = defineProps({
  id: {
    type: String,
    required: true
  }
})

const router = useRouter()
const app = useAppStore()

const loading = ref(true)
const error = ref('')
const ticket = ref(null)
const messages = ref([])

const replying = ref(false)
const replyText = ref('')
const closing = ref(false)

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

async function loadTicketData() {
  loading.value = true
  error.value = ''
  try {
    const res = await getTicketDetail(props.id)
    const data = objectFromPayload(res, null)
    ticket.value = data
    messages.value = listFromPayload(data?.message)
  } catch (err) {
    error.value = err.message || '获取详情失败'
  } finally {
    loading.value = false
  }
}

async function handleReply() {
  if (!replyText.value.trim()) return
  replying.value = true
  try {
    await replyTicket({ id: props.id, message: replyText.value })
    app.showToast('回复成功', 'success')
    replyText.value = ''
    loadTicketData()
  } catch (err) {
    app.showToast(err.message || '回复失败', 'error')
  } finally {
    replying.value = false
  }
}

async function handleClose() {
  if (!confirm('确定要关闭此工单吗？')) return
  closing.value = true
  try {
    await closeTicket({ id: props.id })
    app.showToast('工单已关闭', 'success')
    loadTicketData()
  } catch (err) {
    app.showToast(err.message || '关闭失败', 'error')
  } finally {
    closing.value = false
  }
}

onMounted(() => {
  loadTicketData()
})
</script>

<style scoped>
.ticket-detail-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-bottom: 1px solid var(--c-border);
  padding-bottom: 16px;
  max-width: 800px;
  margin: 0 auto;
  width: 100%;
}

/* Chat Container */
.chat-container {
  max-width: 800px;
  width: 100%;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.chat-messages {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.chat-msg {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}
.msg-me {
  flex-direction: row-reverse;
}

.msg-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  color: var(--c-text-3);
  flex-shrink: 0;
}
.msg-me .msg-avatar {
  background: var(--c-primary-light);
  border-color: transparent;
  color: var(--c-primary);
}
.msg-reply .msg-avatar {
  background: rgba(16, 185, 129, 0.1);
  border-color: transparent;
  color: var(--c-success);
}

.msg-content-wrapper {
  max-width: 70%;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.msg-me .msg-content-wrapper {
  align-items: flex-end;
}

.msg-info {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--fs-xs);
}
.msg-me .msg-info {
  flex-direction: row-reverse;
}
.msg-author {
  font-weight: var(--fw-bold);
  color: var(--c-text-2);
}
.msg-time {
  color: var(--c-text-3);
}

.msg-bubble {
  background: var(--c-bg-card);
  border: 1px solid var(--c-border);
  padding: 16px;
  border-radius: 12px;
  color: var(--c-text-1);
  font-size: var(--fs-sm);
  line-height: 1.6;
  white-space: pre-wrap;
  box-shadow: var(--shadow-sm);
}
.msg-me .msg-bubble {
  background: var(--c-primary);
  color: #fff;
  border-color: transparent;
  border-top-right-radius: 2px;
}
.msg-reply .msg-bubble {
  border-top-left-radius: 2px;
}

/* Chat Input */
.chat-input-area {
  background: var(--c-bg-card);
  padding: 20px;
  border-radius: var(--r-md);
  border: 1px solid var(--c-border);
  box-shadow: var(--shadow-sm);
}
</style>

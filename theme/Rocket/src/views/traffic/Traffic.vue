<template>
  <div class="page traffic-page">
    <div class="page-header">
      <h2 class="page-title">流量记录</h2>
      <button class="btn btn--secondary btn--sm" @click="fetchLogs" :disabled="loading">
        <Icon name="refresh" :size="16" />
        刷新
      </button>
    </div>

    <div class="card p-0 overflow-hidden">
      <div class="traffic-section-header">
        <div>
          <h3>设备流量</h3>
          <span class="text-3 text-xs">近 30 天</span>
        </div>
      </div>
      <div v-if="loading" class="p-4">
        <div class="skeleton" style="height: 40px; margin-bottom: 10px" v-for="i in 3" :key="`device-${i}`"></div>
      </div>
      <div v-else-if="deviceTotals.length === 0" class="p-6 text-center">
        <Icon name="monitor" :size="40" class="text-3 mb-3 mx-auto" />
        <p class="text-2">节点暂未上报设备流量</p>
      </div>
      <div v-else class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>设备</th>
              <th>实际上行</th>
              <th>实际下行</th>
              <th>实际合计</th>
              <th>计费流量</th>
              <th>最后记录</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="device in deviceTotals" :key="device.device_id">
              <td class="fw-bold">{{ device.device_name }}</td>
              <td class="text-primary">{{ formatBytes(device.u) }}</td>
              <td class="text-success">{{ formatBytes(device.d) }}</td>
              <td>{{ formatBytes(device.u + device.d) }}</td>
              <td class="text-danger fw-bold">{{ formatBytes(device.charged_u + device.charged_d) }}</td>
              <td>{{ formatDate(device.last_record_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <div class="card p-0 overflow-hidden">
      <div v-if="loading" class="p-4">
        <div class="skeleton" style="height: 40px; margin-bottom: 10px" v-for="i in 5" :key="i"></div>
      </div>
      
      <div v-else-if="error" class="p-6 text-center text-danger">
        <Icon name="alert" :size="32" class="mb-2 mx-auto" />
        <p>{{ error }}</p>
      </div>
      
      <div v-else-if="logs.length === 0" class="p-6 text-center">
        <Icon name="chart" :size="48" class="text-3 mb-3 mx-auto" />
        <p class="text-2">暂无流量记录数据</p>
      </div>
      
      <div v-else class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>记录时间</th>
              <th>节点名称</th>
              <th>费率</th>
              <th>实际上行</th>
              <th>实际下行</th>
              <th>扣费流量</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(log, index) in logs" :key="log.id || `${log.record_at}-${index}`" class="anim-fade-up">
              <td>{{ formatDate(log.record_at || log.created_at) }}</td>
              <td>{{ log.server_name || '汇总记录' }}</td>
              <td><span class="text-success fw-bold">1.0x</span></td>
              <td class="text-primary">{{ formatBytes(log.u) }}</td>
              <td class="text-success">{{ formatBytes(log.d) }}</td>
              <td class="text-danger fw-bold">{{ formatBytes(((log.u || 0) + (log.d || 0)) * 1.0) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { getDeviceTraffic, getTrafficLog } from '@/api/user'
import { listFromPayload } from '@/api/normalizers'
import Icon from '@/components/ui/Icon.vue'

const logs = ref([])
const deviceLogs = ref([])
const loading = ref(true)
const error = ref('')

const deviceTotals = computed(() => {
  const totals = new Map()
  for (const row of deviceLogs.value) {
    const current = totals.get(row.device_id) || {
      device_id: row.device_id,
      device_name: row.device_name || '未知设备',
      u: 0,
      d: 0,
      charged_u: 0,
      charged_d: 0,
      last_record_at: 0
    }
    current.u += Number(row.u) || 0
    current.d += Number(row.d) || 0
    current.charged_u += Number(row.charged_u) || 0
    current.charged_d += Number(row.charged_d) || 0
    current.last_record_at = Math.max(current.last_record_at, Number(row.record_at) || 0)
    totals.set(row.device_id, current)
  }
  return [...totals.values()].sort((a, b) => (b.u + b.d) - (a.u + a.d))
})

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B'
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i]
}

function formatDate(ts) {
  if (!ts) return '-'
  return new Date(ts * 1000).toLocaleString('zh-CN', {
    month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  })
}

async function fetchLogs() {
  loading.value = true
  error.value = ''
  try {
    const [trafficResult, deviceResult] = await Promise.allSettled([
      getTrafficLog(),
      getDeviceTraffic({ days: 30 })
    ])
    if (trafficResult.status === 'rejected') throw trafficResult.reason
    logs.value = listFromPayload(trafficResult.value)
    deviceLogs.value = deviceResult.status === 'fulfilled' ? listFromPayload(deviceResult.value) : []
  } catch (err) {
    error.value = err.message || '获取流量记录失败'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchLogs()
})
</script>

<style scoped>
.traffic-page {
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
.traffic-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  min-height: 64px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--c-border);
}
.traffic-section-header h3 {
  margin: 0 0 2px;
  color: var(--c-text-1);
  font-size: var(--fs-base);
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
.fw-bold {
  font-weight: var(--fw-bold);
}
.text-danger { color: var(--c-danger); }
.text-success { color: var(--c-success); }
.text-primary { color: var(--c-primary); }
</style>

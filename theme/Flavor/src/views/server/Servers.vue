<template>
  <div class="page servers-page">
    <h2 class="page-title">节点列表</h2>
    <p class="text-2 mb-4">为您提供全球高速稳定的节点选择，实时监控状态确保连接质量。</p>
    
    <div v-if="loading" class="dash-grid">
      <div v-for="i in 4" :key="i" class="card skeleton" style="height: 120px"></div>
    </div>
    
    <div v-else-if="error" class="card text-center" style="padding: 40px">
      <Icon name="alert" :size="48" class="text-danger mb-2" style="margin: 0 auto" />
      <p class="text-1" style="font-size: var(--fs-lg)">{{ error }}</p>
    </div>

    <div v-else-if="!serverList || serverList.length === 0" class="card text-center" style="padding: 60px 20px">
      <Icon name="globe" :size="48" class="text-warning mb-2" style="margin: 0 auto" />
      <p class="text-1" style="font-size: var(--fs-lg); font-weight: var(--fw-semibold)">暂无可用的节点</p>
      <p class="text-3 mt-1">您可能尚未购买套餐或您的套餐已到期，请前往购买或续费。</p>
      <router-link to="/shop" class="btn btn--primary mt-3" style="display: inline-flex; width: auto">
        前往购买套餐
      </router-link>
    </div>

    <template v-else>
      <div class="server-groups">
        <div v-for="(servers, groupName) in groupedServers" :key="groupName" class="server-group">
          <h3 class="server-group__title">
            <span class="server-group__dot"></span>
            {{ groupName || '默认分组' }}
          </h3>
          
          <div class="server-grid">
            <div v-for="server in servers" :key="server.id" class="server-card anim-fade-up">
              <div class="server-card__header">
                <div class="server-card__status" :class="server.is_online ? 'is-online' : 'is-offline'">
                  <span class="status-dot"></span>
                  {{ server.is_online ? '在线' : '离线' }}
                </div>
                <div class="server-card__tags">
                  <span v-for="tag in server.tags" :key="tag" class="tag">{{ tag }}</span>
                  <span class="tag tag--protocol">{{ getProtocolName(server.type) }}</span>
                </div>
              </div>
              
              <div class="server-card__body">
                <h4 class="server-card__name">{{ server.name }}</h4>
                <div class="server-card__stats">
                  <div class="stat">
                    <span class="stat-label">倍率</span>
                    <span class="stat-value" style="color: var(--c-success); font-weight: bold;">1.0x</span>
                  </div>
                  <div class="stat" v-if="server.cache_key">
                    <span class="stat-label">标识</span>
                    <span class="stat-value text-ellipsis" style="max-width: 100px" :title="server.cache_key">{{ server.cache_key }}</span>
                  </div>
                </div>
              </div>
              
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getServers } from '@/api/user'
import { listFromPayload } from '@/api/normalizers'
import Icon from '@/components/ui/Icon.vue'

const loading = ref(true)
const error = ref('')
const serverList = ref([])

const groupedServers = computed(() => {
  const groups = {}
  serverList.value.forEach(server => {
    let gname = '默认分组'
    if (server.tags && server.tags.length > 0) {
      // 提取第一个标签作为分组（类似于原版Xboard的简单处理）
      gname = server.tags[0]
    }
    if (!groups[gname]) {
      groups[gname] = []
    }
    groups[gname].push(server)
  })
  return groups
})

function getProtocolName(type) {
  const protocols = {
    'shadowsocks': 'SS',
    'v2ray': 'Vmess',
    'trojan': 'Trojan',
    'shadowsocks-plugin': 'SS-Plugin',
    'vmess': 'Vmess',
    'vless': 'Vless',
    'hysteria': 'Hysteria',
    'tuic': 'TUIC'
  }
  return protocols[type] || type || 'Unknown'
}

async function fetchServers() {
  loading.value = true
  error.value = ''
  try {
    const res = await getServers()
    const data = listFromPayload(res)
    // 假设 is_online 可能基于返回的 last_check_at 或在线人数, 针对 V1 Resource 可直接有 is_online 字段
    serverList.value = data.map(node => {
      // 提供一个基于时间差的简单离线判断 fallback (如果后端没传)
      let isOnline = false
      if (typeof node.is_online !== 'undefined') {
        isOnline = node.is_online
      } else if (node.last_check_at) {
        // 如果最后检查时间在10分钟内，认为在线
        isOnline = (Date.now()/1000 - node.last_check_at) < 600
      }
      return {
        ...node,
        is_online: isOnline
      }
    })
  } catch (err) {
    if (err.response && err.response.status === 403) {
      // 权限不足说明没购买套餐
      serverList.value = []
    } else {
      error.value = err.message || '获取节点失败'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchServers()
})
</script>

<style scoped>
.servers-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.server-group {
  margin-bottom: 32px;
}
.server-group__title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
  margin-bottom: 16px;
}
.server-group__dot {
  width: 8px;
  height: 8px;
  background: var(--c-primary);
  border-radius: 50%;
  box-shadow: 0 0 10px var(--c-primary);
}

.server-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.server-card {
  background: var(--c-bg-card);
  border: 1px solid var(--c-border);
  border-radius: var(--r-md);
  padding: 16px;
  transition: all var(--t-fast);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.server-card:hover {
  border-color: var(--c-primary);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.server-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.server-card__status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: var(--r-full);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
}
.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
.is-online {
  background: rgba(16, 185, 129, 0.1);
  color: var(--c-success);
}
.is-online .status-dot { background: var(--c-success); }
.is-offline {
  background: rgba(100, 116, 139, 0.1);
  color: var(--c-text-3);
}
.is-offline .status-dot { background: var(--c-text-3); }

.server-card__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
}
.tag {
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  color: var(--c-text-2);
  font-size: var(--fs-xs);
  padding: 2px 8px;
  border-radius: var(--r-sm);
}
.tag--protocol {
  background: var(--c-primary-light);
  border-color: transparent;
  color: var(--c-primary);
  font-weight: var(--fw-medium);
}

.server-card__name {
  font-size: var(--fs-base);
  font-weight: var(--fw-semibold);
  color: var(--c-text-1);
  line-height: 1.4;
  word-break: break-all;
}

.server-card__stats {
  display: flex;
  gap: 16px;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px dashed var(--c-border);
}
.stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.stat-label {
  font-size: 11px;
  color: var(--c-text-3);
  text-transform: uppercase;
}
.stat-value {
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--c-text-2);
}
.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>

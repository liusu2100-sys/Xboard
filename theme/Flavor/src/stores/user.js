import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import * as userApi from '@/api/user'
import { listFromPayload, objectFromPayload } from '@/api/normalizers'

export const useUserStore = defineStore('user', () => {
  const info = ref(null)
  const subscribe = ref(null)
  const stat = ref(null)
  const notices = ref([])
  const config = ref(null)
  const loading = ref(false)

  const isLoaded = computed(() => !!info.value)
  const subscription = computed(() => subscribe.value || info.value || {})

  const trafficUsed = computed(() => {
    return (subscription.value.u || 0) + (subscription.value.d || 0)
  })

  const trafficTotal = computed(() => {
    return subscription.value.transfer_enable || 0
  })

  const trafficPercent = computed(() => {
    if (!trafficTotal.value) return 0
    return Math.min(100, Math.round((trafficUsed.value / trafficTotal.value) * 100))
  })

  const isExpired = computed(() => {
    if (!subscription.value.expired_at) return false
    return subscription.value.expired_at * 1000 < Date.now()
  })

  const daysRemaining = computed(() => {
    if (!subscription.value.expired_at) return 0
    const diff = subscription.value.expired_at * 1000 - Date.now()
    return Math.max(0, Math.ceil(diff / 86400000))
  })

  async function fetchAll() {
    loading.value = true
    try {
      const [infoData, subData, statData, noticeData] = await Promise.all([
        userApi.getUserInfo(),
        userApi.getSubscribe().catch(() => null),
        userApi.getStat().catch(() => null),
        userApi.getNotices().catch(() => [])
      ])
      info.value = objectFromPayload(infoData, null)
      subscribe.value = objectFromPayload(subData, null)
      stat.value = Array.isArray(statData) ? statData : []
      notices.value = listFromPayload(noticeData)
    } finally {
      loading.value = false
    }
  }

  async function fetchConfig() {
    try {
      config.value = objectFromPayload(await userApi.getConfig(), null)
    } catch (e) {
      console.warn('Failed to fetch config', e)
    }
  }

  function reset() {
    info.value = null
    subscribe.value = null
    stat.value = null
    notices.value = []
  }

  return {
    info, subscribe, stat, notices, config, loading, isLoaded, subscription,
    trafficUsed, trafficTotal, trafficPercent, isExpired, daysRemaining,
    fetchAll, fetchConfig, reset
  }
})

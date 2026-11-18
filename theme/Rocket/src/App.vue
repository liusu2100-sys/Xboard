<template>
  <router-view v-slot="{ Component }">
    <component :is="Component" />
  </router-view>
  <PromoModal />
  <ToastContainer />
</template>

<script setup>
import PromoModal from '@/components/ui/PromoModal.vue'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import { useAppStore } from '@/stores/app'
const app = useAppStore()
</script>

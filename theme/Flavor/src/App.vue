<template>
  <router-view v-slot="{ Component }">
    <transition name="page" mode="out-in">
      <component :is="Component" />
    </transition>
  </router-view>
  <ToastContainer />
</template>

<script setup>
import ToastContainer from '@/components/ui/ToastContainer.vue'
import { useAppStore } from '@/stores/app'
const app = useAppStore()
</script>

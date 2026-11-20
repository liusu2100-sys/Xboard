import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/home',
    name: 'Home',
    component: () => import('@/views/home/Home.vue')
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/auth/Login.vue'),
    meta: { guest: true }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/views/auth/Register.vue'),
    meta: { guest: true }
  },
  {
    path: '/forget',
    name: 'Forget',
    component: () => import('@/views/auth/Forget.vue'),
    meta: { guest: true }
  },
  {
    path: '/',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { auth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', name: 'Dashboard', component: () => import('@/views/dashboard/Dashboard.vue') },
      { path: 'shop', name: 'Shop', component: () => import('@/views/shop/Shop.vue') },
      { path: 'order', name: 'Orders', component: () => import('@/views/order/Orders.vue') },
      { path: 'order/:tradeNo', name: 'OrderDetail', component: () => import('@/views/order/OrderDetail.vue'), props: true },
      { path: 'server', name: 'Servers', component: () => import('@/views/server/Servers.vue') },
      { path: 'invite', name: 'Invite', component: () => import('@/views/invite/Invite.vue') },
      { path: 'gift-card', name: 'GiftCard', component: () => import('@/views/gift/GiftCard.vue'), props: { mode: 'gift' } },
      { path: 'cdkey', name: 'Cdkey', component: () => import('@/views/gift/GiftCard.vue'), props: { mode: 'cdkey' } },
      { path: 'ticket', name: 'Tickets', component: () => import('@/views/ticket/Tickets.vue') },
      { path: 'ticket/:id', name: 'TicketDetail', component: () => import('@/views/ticket/TicketDetail.vue'), props: true },
      { path: 'knowledge', name: 'Knowledge', component: () => import('@/views/knowledge/Knowledge.vue') },
      { path: 'profile', name: 'Profile', component: () => import('@/views/profile/Profile.vue') },
      { path: 'traffic', name: 'Traffic', component: () => import('@/views/traffic/Traffic.vue') },
    ]
  },
  { path: '/:pathMatch(.*)*', redirect: '/' }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()
  if (to.meta.auth && !auth.isLoggedIn) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
  } else if (to.meta.guest && auth.isLoggedIn) {
    next({ name: 'Dashboard' })
  } else {
    next()
  }
})

export default router

<template>
  <div class="home-page">
    <header class="home-nav">
      <router-link to="/home" class="home-nav__brand">
        <span class="home-nav__logo"><Icon name="zap" :size="22" /></span>
        <span>{{ siteTitle }}</span>
      </router-link>
      <nav class="home-nav__links">
        <router-link to="/dashboard">我的面板</router-link>
        <router-link to="/shop">购买套餐</router-link>
        <router-link to="/invite">我的邀请</router-link>
        <router-link to="/profile">账号设置</router-link>
        <button type="button" @click="menuOpen = !menuOpen">
          <Icon name="menu" :size="17" />
          <span>全部菜单</span>
        </button>
      </nav>
      <div class="home-nav__actions">
        <router-link :to="auth.isLoggedIn ? '/dashboard' : '/login'" class="home-nav__ghost">
          {{ auth.isLoggedIn ? '进入面板' : '登录' }}
        </router-link>
        <router-link to="/register" class="home-nav__primary">立即体验</router-link>
      </div>
      <button class="home-nav__menu" type="button" @click="menuOpen = !menuOpen">
        <Icon name="menu" :size="22" />
      </button>
    </header>

    <transition name="home-menu">
      <div v-if="menuOpen" class="home-menu">
        <router-link v-for="item in menuItems" :key="item.to" :to="item.to" @click="menuOpen = false">
          <Icon :name="item.icon" :size="18" />
          <span>{{ item.label }}</span>
        </router-link>
      </div>
    </transition>

    <main>
      <section class="hero">
        <div class="hero__content">
          <div class="hero__brand">{{ siteTitle }}</div>
          <h1>{{ homeSlogan }}</h1>
          <p>获取与全球数百内容供应商互联的网络加速服务，高速访问流媒体、AI、社交与开发者工具。</p>
          <div class="hero__actions">
            <router-link to="/register" class="hero__primary">立即体验</router-link>
            <router-link to="/shop" class="hero__secondary">查看套餐</router-link>
          </div>
          <div class="hero__chips">
            <span>流媒体解锁</span>
            <span>全中转节点</span>
            <span>全终端支持</span>
            <span>隧道加密</span>
            <span>稳定在线</span>
          </div>
        </div>
        <div class="hero__visual" aria-hidden="true">
          <div class="orbit orbit--outer"></div>
          <div class="orbit orbit--middle"></div>
          <div class="planet">
            <div class="planet__grid"></div>
            <span>2000Mbps</span>
          </div>
          <div class="signal signal--a">YouTube</div>
          <div class="signal signal--b">ChatGPT</div>
          <div class="signal signal--c">Telegram</div>
          <div class="signal signal--d">Netflix</div>
        </div>
      </section>

      <section class="device-section">
        <div class="section-heading">
          <span>App Clients</span>
          <h2>覆盖常用终端</h2>
        </div>
        <div class="device-grid">
          <router-link v-for="device in devices" :key="device.name" to="/knowledge" class="device-card">
            <Icon :name="device.icon" :size="30" />
            <h3>{{ device.name }}</h3>
            <p>{{ device.desc }}</p>
          </router-link>
        </div>
      </section>

      <section class="nodes-section">
        <div class="section-heading">
          <span>Global Network</span>
          <h2>全球节点覆盖</h2>
        </div>
        <div class="country-grid">
          <div v-for="country in countries" :key="country" class="country-card">
            <span>{{ country }}</span>
            <strong>可用</strong>
          </div>
        </div>
      </section>

      <section class="plans-section">
        <div class="section-heading">
          <span>Pricing</span>
          <h2>选择适合你的套餐</h2>
        </div>
        <div class="home-plan-grid">
          <div v-for="plan in plans" :key="plan.name" class="home-plan">
            <h3>{{ plan.name }}</h3>
            <div class="home-plan__price">
              <span>¥</span>
              <strong>{{ plan.price }}</strong>
              <em>/ 月</em>
            </div>
            <ul>
              <li v-for="feature in plan.features" :key="feature">{{ feature }}</li>
            </ul>
            <router-link to="/shop">立即购买</router-link>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const menuOpen = ref(false)
const siteTitle = computed(() => window.settings?.title || 'Rocket')
const homeSlogan = computed(() => window.settings?.home_slogan || '无限制畅享全球网络加速')

const menuItems = [
  { to: '/dashboard', icon: 'home', label: '我的面板' },
  { to: '/shop', icon: 'shop', label: '购买套餐' },
  { to: '/invite', icon: 'invite', label: '我的邀请' },
  { to: '/profile', icon: 'settings', label: '账号设置' },
  { to: '/gift-card', icon: 'gift', label: '礼品卡兑换' },
  { to: '/cdkey', icon: 'credit-card', label: 'CDKEY 兑换' },
]

const devices = [
  { name: 'MacOS', icon: 'monitor', desc: '桌面端稳定连接' },
  { name: 'iOS', icon: 'shield', desc: '移动端快速导入' },
  { name: 'Windows', icon: 'monitor', desc: '常用客户端支持' },
  { name: 'Android', icon: 'zap', desc: '多协议灵活使用' },
]

const countries = ['中国香港', '日本', '新加坡', '美国', '德国', '英国', '韩国', '加拿大', '澳大利亚', '台湾', '荷兰', '法国']

const plans = [
  { name: 'Basic 基础套餐', price: '18.88', features: ['每月 100G 流量', '3 台设备同时在线', '最高 100Mbps'] },
  { name: 'Standard 进阶套餐', price: '38.88', features: ['每月 300G 流量', '5 台设备同时在线', '最高 300Mbps'] },
  { name: 'Pro 高速套餐', price: '68.88', features: ['每月 500G 流量', '10 台设备同时在线', '高峰稳定优化'] },
]
</script>

<style scoped>
.home-page {
  min-height: 100vh;
  color: var(--c-text-1);
  background:
    linear-gradient(180deg, rgba(20, 184, 166, 0.12), transparent 760px),
    var(--c-bg-shell, var(--c-bg-base));
}
.home-nav {
  position: sticky;
  top: 0;
  z-index: 20;
  max-width: 1320px;
  height: 78px;
  margin: 0 auto;
  padding: 0 28px;
  display: flex;
  align-items: center;
  gap: 24px;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(18px);
}
.home-nav__brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--c-text-1);
  font-size: 1.25rem;
  font-weight: 800;
}
.home-nav__logo {
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--c-primary-hover);
  background: rgba(15,159,138,0.12);
  border: 1px solid rgba(15,159,138,0.34);
  border-radius: var(--r-sm);
}
.home-nav__links {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.home-nav__links a,
.home-nav__links button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  color: var(--c-text-2);
  border-radius: var(--r-sm);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
}
.home-nav__links a:hover,
.home-nav__links button:hover {
  color: var(--c-primary-hover);
  background: rgba(15,159,138,0.1);
}
.home-nav__actions {
  display: flex;
  gap: 10px;
}
.home-nav__ghost,
.home-nav__primary,
.hero__primary,
.hero__secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 0 16px;
  border-radius: var(--r-sm);
  font-weight: var(--fw-bold);
}
.home-nav__ghost,
.hero__secondary {
  color: var(--c-text-1);
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
}
.home-nav__primary,
.hero__primary {
  color: #fff;
  background: var(--gradient-primary);
  box-shadow: var(--shadow-glow);
}
.home-nav__menu { display: none; color: var(--c-text-1); }
.home-menu {
  position: fixed;
  top: 78px;
  right: 18px;
  z-index: 30;
  width: 240px;
  padding: 10px;
  background: var(--gradient-panel);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-lg);
}
.home-menu a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  color: var(--c-text-2);
  border-radius: var(--r-sm);
}
.home-menu a:hover {
  color: var(--c-primary-hover);
  background: rgba(15,159,138,0.1);
}
main {
  max-width: 1320px;
  margin: 0 auto;
  padding: 42px 28px 80px;
}
.hero {
  position: relative;
  min-height: calc(100vh - 120px);
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(360px, 0.8fr);
  align-items: center;
  gap: 48px;
}
.hero__brand {
  color: var(--c-primary-hover);
  font-weight: 800;
  margin-bottom: 18px;
}
.hero h1 {
  max-width: 780px;
  margin: 0;
  font-size: clamp(2.6rem, 7vw, 6.4rem);
  line-height: 1.02;
  letter-spacing: 0;
}
.hero p {
  max-width: 640px;
  margin: 24px 0 0;
  color: var(--c-text-2);
  font-size: 1.1rem;
}
.hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 30px;
}
.hero__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 28px;
}
.hero__chips span {
  padding: 8px 12px;
  color: var(--c-text-2);
  background: var(--c-bg-elevated);
  border: 1px solid var(--c-border);
  border-radius: var(--r-full);
  font-size: var(--fs-sm);
}
.hero__visual {
  position: relative;
  min-height: 560px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.planet {
  position: relative;
  width: min(420px, 72vw);
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background:
    linear-gradient(135deg, var(--c-primary), #3b82f6),
    repeating-linear-gradient(90deg, rgba(255,255,255,0.12) 0 1px, transparent 1px 34px);
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 50%;
  box-shadow: 0 30px 90px rgba(20, 184, 166, 0.24);
}
.planet__grid {
  position: absolute;
  inset: 14%;
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 50%;
}
.planet span {
  font-size: 1.6rem;
  font-weight: 900;
}
.orbit {
  position: absolute;
  border: 1px solid var(--c-border);
  border-radius: 50%;
}
.orbit--outer { width: 108%; aspect-ratio: 1; }
.orbit--middle { width: 82%; aspect-ratio: 1; transform: rotate(28deg) scaleY(0.68); }
.signal {
  position: absolute;
  padding: 9px 13px;
  color: #fff;
  background: var(--gradient-primary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--r-full);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  box-shadow: var(--shadow-float);
}
.signal--a { top: 14%; right: 12%; }
.signal--b { left: 6%; top: 26%; }
.signal--c { right: 3%; bottom: 26%; }
.signal--d { left: 14%; bottom: 18%; }
.section-heading {
  margin-bottom: 22px;
}
.section-heading span {
  color: var(--c-primary-hover);
  font-weight: 800;
  font-size: var(--fs-xs);
  text-transform: uppercase;
}
.section-heading h2 {
  margin: 6px 0 0;
  font-size: clamp(1.65rem, 3vw, 2.4rem);
  letter-spacing: 0;
}
.device-section,
.nodes-section,
.plans-section {
  padding-top: 54px;
}
.device-grid,
.home-plan-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.device-card,
.home-plan,
.country-card {
  background: var(--gradient-panel);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-card);
}
.device-card {
  min-height: 170px;
  padding: 22px;
}
.device-card svg {
  color: var(--c-primary-hover);
}
.device-card h3,
.home-plan h3 {
  margin: 16px 0 8px;
  color: var(--c-text-1);
}
.device-card p,
.home-plan li {
  color: var(--c-text-2);
}
.country-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 10px;
}
.country-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px;
}
.country-card strong {
  color: var(--c-primary-hover);
  font-size: var(--fs-xs);
}
.home-plan {
  padding: 24px;
}
.home-plan__price {
  display: flex;
  align-items: baseline;
  gap: 4px;
  color: var(--c-primary-hover);
}
.home-plan__price strong {
  font-size: 2.6rem;
}
.home-plan__price em {
  color: var(--c-text-3);
  font-style: normal;
}
.home-plan ul {
  display: grid;
  gap: 10px;
  margin: 18px 0 24px;
}
.home-plan a {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 42px;
  color: #fff;
  background: var(--gradient-primary);
  border-radius: var(--r-sm);
  font-weight: var(--fw-bold);
}
.home-menu-enter-active,
.home-menu-leave-active { transition: all var(--t-fast); }
.home-menu-enter-from,
.home-menu-leave-to { opacity: 0; transform: translateY(-8px); }
@media (max-width: 980px) {
  .home-nav__links,
  .home-nav__actions { display: none; }
  .home-nav__menu { display: inline-flex; margin-left: auto; }
  .hero { grid-template-columns: 1fr; min-height: auto; padding-top: 32px; }
  .hero__visual { min-height: 380px; }
  .device-grid,
  .home-plan-grid { grid-template-columns: 1fr 1fr; }
  .country-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px) {
  .home-nav { padding: 0 16px; }
  main { padding: 24px 16px 60px; }
  .hero h1 { font-size: 2.55rem; }
  .device-grid,
  .home-plan-grid,
  .country-grid { grid-template-columns: 1fr; }
  .signal { display: none; }
}
</style>

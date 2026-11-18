<template>
  <div class="layout">
    <!-- Top Navigation Bar -->
    <header class="topbar">
      <div class="topbar__inner">
        <!-- Logo -->
        <router-link to="/" class="topbar__brand">
          <div class="topbar__logo">
            <Icon name="zap" :size="22" />
          </div>
          <span class="topbar__title">{{ siteTitle }}</span>
        </router-link>

        <!-- Main Nav (Desktop) -->
        <nav class="topbar__nav">
          <router-link v-for="item in mainNav" :key="item.to" :to="item.to"
            class="topbar__nav-item" active-class="topbar__nav-item--active">
            {{ item.label }}
          </router-link>
          <button class="topbar__more" :class="{ 'topbar__more--active': subnavOpen }" @click="subnavOpen = !subnavOpen">
            <Icon name="menu" :size="16" />
            <span>{{ i18n.t('allMenu') }}</span>
          </button>
        </nav>

        <!-- Right Actions -->
        <div class="topbar__actions">
          <label v-if="i18n.availableLocales.length > 1" class="topbar__language" :title="i18n.t('language')">
            <Icon name="globe" :size="17" />
            <select v-model="selectedLocale" :aria-label="i18n.t('language')" @change="handleLocaleChange">
              <option v-for="locale in i18n.availableLocales" :key="locale" :value="locale">
                {{ i18n.localeName(locale) }}
              </option>
            </select>
          </label>

          <button class="topbar__action-btn" @click="app.toggleTheme"
            :title="app.theme === 'dark' ? i18n.t('themeLight') : i18n.t('themeDark')">
            <Icon :name="app.theme === 'dark' ? 'sun' : 'moon'" :size="18" />
          </button>

          <!-- User Dropdown -->
          <div class="topbar__user-wrap" @mouseenter="userDropdownOpen = true" @mouseleave="userDropdownOpen = false">
            <div class="topbar__user" @click="userDropdownOpen = !userDropdownOpen">
              <div class="topbar__avatar">{{ userInitial }}</div>
            </div>
            <transition name="dropdown">
              <div v-if="userDropdownOpen" class="user-dropdown">
                <div class="user-dropdown__email">{{ user.info?.email || '' }}</div>
                <div class="user-dropdown__divider"></div>
                <router-link to="/profile" class="user-dropdown__item" @click="userDropdownOpen = false">
                  <Icon name="settings" :size="16" />
                  <span>{{ i18n.t('profile') }}</span>
                </router-link>
                <button class="user-dropdown__item user-dropdown__item--danger" @click="handleLogout">
                  <Icon name="logout" :size="16" />
                  <span>{{ i18n.t('logout') }}</span>
                </button>
              </div>
            </transition>
          </div>
        </div>

        <!-- Mobile hamburger -->
        <button class="topbar__hamburger" @click="mobileMenuOpen = !mobileMenuOpen">
          <Icon :name="mobileMenuOpen ? 'x' : 'menu'" :size="24" />
        </button>
      </div>
    </header>

    <!-- Sub Navigation (toggleable) -->
    <transition name="subnav-slide">
      <div class="subnav" v-show="subnavOpen">
        <div class="subnav__inner">
          <router-link v-for="item in allNav" :key="item.to" :to="item.to"
            class="subnav__item" active-class="subnav__item--active">
            <Icon :name="item.icon" :size="22" />
            <span>{{ item.label }}</span>
          </router-link>
        </div>
      </div>
    </transition>

    <!-- Mobile Menu Overlay -->
    <div v-if="mobileMenuOpen" class="mobile-overlay" @click="mobileMenuOpen = false"></div>
    <div class="mobile-menu" :class="{ 'mobile-menu--open': mobileMenuOpen }">
      <router-link v-for="item in allNav" :key="item.to" :to="item.to"
        class="mobile-menu__item" active-class="mobile-menu__item--active" @click="mobileMenuOpen = false">
        <Icon :name="item.icon" :size="20" />
        <span>{{ item.label }}</span>
      </router-link>
      <label v-if="i18n.availableLocales.length > 1" class="mobile-menu__language">
        <Icon name="globe" :size="20" />
        <select v-model="selectedLocale" :aria-label="i18n.t('language')" @change="handleLocaleChange">
          <option v-for="locale in i18n.availableLocales" :key="locale" :value="locale">
            {{ i18n.localeName(locale) }}
          </option>
        </select>
      </label>
      <div class="mobile-menu__divider"></div>
      <button class="mobile-menu__item" @click="handleLogout">
        <Icon name="logout" :size="20" />
        <span>{{ i18n.t('logout') }}</span>
      </button>
    </div>

    <!-- Page Content -->
    <main class="content">
      <div class="content__inner">
        <router-view v-slot="{ Component }">
          <transition name="page" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </div>
    </main>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav">
      <router-link v-for="item in mobileBottomNav" :key="item.to" :to="item.to"
        class="mobile-nav__item" active-class="mobile-nav__item--active">
        <Icon :name="item.icon" :size="22" />
        <span>{{ item.label }}</span>
      </router-link>
    </nav>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'
import { useI18nStore } from '@/stores/i18n'
import { useUserStore } from '@/stores/user'

const app = useAppStore()
const auth = useAuthStore()
const i18n = useI18nStore()
const user = useUserStore()
const router = useRouter()

const siteTitle = computed(() => window.settings?.title || 'Xboard')
const subnavOpen = ref(false)
const mobileMenuOpen = ref(false)
const userDropdownOpen = ref(false)
const selectedLocale = ref(i18n.locale)

const userInitial = computed(() => {
  const email = user.info?.email || ''
  return email.charAt(0).toUpperCase()
})

const mainNav = computed(() => [
  { to: '/', label: i18n.t('dashboard') },
  { to: '/shop', label: i18n.t('shop') },
  { to: '/invite', label: i18n.t('invite') },
  { to: '/profile', label: i18n.t('profile') },
])

const allNav = computed(() => [
  { to: '/', icon: 'home', label: i18n.t('dashboard') },
  { to: '/knowledge', icon: 'book', label: i18n.t('knowledge') },
  { to: '/shop', icon: 'shop', label: i18n.t('shop') },
  { to: '/server', icon: 'server', label: i18n.t('server') },
  { to: '/order', icon: 'order', label: i18n.t('order') },
  { to: '/invite', icon: 'invite', label: i18n.t('invite') },
  { to: '/profile', icon: 'settings', label: i18n.t('profile') },
  { to: '/ticket', icon: 'ticket', label: i18n.t('ticket') },
  { to: '/traffic', icon: 'chart', label: i18n.t('traffic') },
])

const mobileBottomNav = computed(() => [
  { to: '/', icon: 'home', label: i18n.t('homeShort') },
  { to: '/shop', icon: 'shop', label: i18n.t('shopShort') },
  { to: '/server', icon: 'globe', label: i18n.t('serverShort') },
  { to: '/order', icon: 'order', label: i18n.t('orderShort') },
  { to: '/profile', icon: 'user', label: i18n.t('profileShort') },
])

watch(() => i18n.locale, (locale) => {
  selectedLocale.value = locale
})

function handleLocaleChange() {
  i18n.setLocale(selectedLocale.value)
  selectedLocale.value = i18n.locale
}

function handleLogout() {
  auth.logout()
  user.reset()
  mobileMenuOpen.value = false
  userDropdownOpen.value = false
  router.push('/login')
}

onMounted(() => {
  if (!user.isLoaded) {
    user.fetchAll()
    user.fetchConfig()
  }
})
</script>

<style scoped>
.layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: var(--c-bg-shell, var(--c-bg-base));
  position: relative;
}
.layout::before {
  content: "";
  position: fixed;
  inset: 0;
  pointer-events: none;
  background:
    linear-gradient(135deg, rgba(33,159,136,0.08) 0%, rgba(59,130,246,0.08) 34%, transparent 58%),
    linear-gradient(180deg, rgba(255,255,255,0.42), transparent 42%);
  z-index: 0;
}

/* ========== Top Bar ========== */
.topbar {
  background: var(--c-surface-glass);
  border-bottom: 1px solid var(--c-border);
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: var(--shadow-sm);
  backdrop-filter: blur(18px);
}
.topbar__inner {
  max-width: 1320px;
  margin: 0 auto;
  padding: 0 28px;
  height: 68px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Brand */
.topbar__brand {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  margin-right: 20px;
  flex-shrink: 0;
}
.topbar__logo {
  width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center;
  background: var(--gradient-accent);
  border-radius: var(--r-sm);
  color: #fff;
  box-shadow: 0 10px 24px rgba(59, 130, 246, 0.22);
}
.topbar__title {
  font-size: 1.2rem;
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
  letter-spacing: 0;
}

/* Nav Items */
.topbar__nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  flex: 1;
}
.topbar__nav-item {
  padding: 8px 16px;
  border-radius: var(--r-sm);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--c-text-2);
  text-decoration: none;
  transition: all var(--t-fast);
  white-space: nowrap;
}
.topbar__nav-item:hover {
  background: var(--c-bg-hover);
  color: var(--c-text-1);
}
.topbar__nav-item--active {
  background: var(--gradient-primary);
  color: #fff !important;
  box-shadow: var(--shadow-glow);
}
.topbar__nav-item--active:hover {
  background: var(--gradient-primary);
}

/* 全部菜单 toggle button */
.topbar__more {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: var(--r-sm);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--c-text-2);
  cursor: pointer;
  transition: all var(--t-fast);
  white-space: nowrap;
  border: 1px solid var(--c-border);
  background: var(--c-surface-glass);
}
.topbar__more:hover {
  background: var(--c-bg-hover);
  color: var(--c-text-1);
  border-color: var(--c-border-light);
}
.topbar__more--active {
  background: var(--c-primary-light);
  color: var(--c-primary);
  border-color: var(--c-primary);
}

/* Right Actions */
.topbar__actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
}
.topbar__action-btn {
  width: 38px; height: 38px;
  display: flex; align-items: center; justify-content: center;
  border-radius: var(--r-sm);
  color: var(--c-text-2);
  transition: all var(--t-fast);
  background: var(--c-surface-glass);
  border: 1px solid var(--c-border);
}
.topbar__action-btn:hover {
  background: var(--c-bg-hover);
  color: var(--c-text-1);
}
.topbar__language {
  height: 38px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 8px;
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
  color: var(--c-text-2);
  background: var(--c-surface-glass);
  transition: all var(--t-fast);
}
.topbar__language:hover {
  border-color: var(--c-border-light);
  color: var(--c-text-1);
}
.topbar__language select {
  min-width: 104px;
  border: 0;
  outline: 0;
  color: inherit;
  background: transparent;
  font-family: inherit;
  font-size: var(--fs-sm);
  cursor: pointer;
}

/* User Dropdown */
.topbar__user-wrap {
  position: relative;
}
.topbar__user {
  cursor: pointer;
  transition: opacity var(--t-fast);
}
.topbar__user:hover { opacity: 0.8; }
.topbar__avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: var(--gradient-primary);
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: var(--fs-base);
  font-weight: var(--fw-bold);
}

.user-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 220px;
  background: var(--gradient-panel);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-lg);
  backdrop-filter: blur(18px);
  padding: 8px;
  z-index: 200;
}
.user-dropdown__email {
  padding: 10px 12px;
  font-size: var(--fs-xs);
  color: var(--c-text-3);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-dropdown__divider {
  height: 1px;
  background: var(--c-border);
  margin: 4px 0;
}
.user-dropdown__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--r-sm);
  color: var(--c-text-2);
  font-size: var(--fs-sm);
  text-decoration: none;
  transition: all var(--t-fast);
  width: 100%;
  cursor: pointer;
  border: none;
  background: none;
  font-family: inherit;
}
.user-dropdown__item:hover {
  background: var(--c-bg-hover);
  color: var(--c-text-1);
}
.user-dropdown__item--danger:hover {
  background: rgba(239, 68, 68, 0.08);
  color: var(--c-danger);
}

/* Dropdown transition */
.dropdown-enter-active { animation: dropIn 0.2s ease; }
.dropdown-leave-active { animation: dropIn 0.15s ease reverse; }
@keyframes dropIn {
  from { opacity: 0; transform: translateY(-6px) scale(0.96); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.topbar__hamburger {
  display: none;
  width: 40px; height: 40px;
  align-items: center; justify-content: center;
  color: var(--c-text-2);
  margin-left: auto;
}

/* ========== Sub Navigation (toggle) ========== */
.subnav {
  background: var(--c-surface-glass);
  border-bottom: 1px solid var(--c-border);
  backdrop-filter: blur(18px);
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.subnav::-webkit-scrollbar { display: none; }
.subnav__inner {
  max-width: 1320px;
  margin: 0 auto;
  padding: 16px 28px;
  display: flex;
  justify-content: center;
  gap: 8px;
  width: 100%;
}
.subnav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 24px;
  font-size: var(--fs-sm);
  color: var(--c-text-2);
  text-decoration: none;
  transition: all var(--t-fast);
  white-space: nowrap;
  border-radius: var(--r-sm);
}
.subnav__item:hover {
  color: var(--c-primary);
  background: var(--c-bg-hover);
}
.subnav__item--active {
  color: var(--c-primary);
  background: var(--c-primary-light);
  box-shadow: inset 0 0 0 1px var(--c-primary-light);
}

/* Subnav slide transition */
.subnav-slide-enter-active {
  animation: slideDown 0.25s ease;
}
.subnav-slide-leave-active {
  animation: slideDown 0.2s ease reverse;
}
@keyframes slideDown {
  from { opacity: 0; max-height: 0; }
  to { opacity: 1; max-height: 120px; }
}

/* ========== Content ========== */
.content {
  flex: 1;
  padding: 28px;
  position: relative;
  z-index: 1;
}
.content__inner {
  max-width: 1320px;
  margin: 0 auto;
}

/* ========== Mobile Menu ========== */
.mobile-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  z-index: 90;
  backdrop-filter: blur(2px);
}
.mobile-menu {
  display: none;
  position: fixed;
  top: 68px; right: 0;
  width: 280px;
  max-height: calc(100vh - 68px);
  background: var(--gradient-panel);
  border-left: 1px solid var(--c-border);
  box-shadow: var(--shadow-lg);
  padding: 10px;
  z-index: 95;
  overflow-y: auto;
  transform: translateX(100%);
  transition: transform var(--t-base);
}
.mobile-menu--open { transform: translateX(0); }
.mobile-menu__item {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 18px;
  border-radius: var(--r-sm);
  color: var(--c-text-2);
  font-size: var(--fs-base);
  text-decoration: none;
  transition: all var(--t-fast);
  width: 100%;
  cursor: pointer;
  border: none;
  background: none;
  font-family: inherit;
}
.mobile-menu__item:hover { background: var(--c-bg-hover); color: var(--c-text-1); }
.mobile-menu__item--active { color: var(--c-primary); background: var(--c-primary-light); }
.mobile-menu__language {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 18px;
  border-radius: var(--r-sm);
  color: var(--c-text-2);
}
.mobile-menu__language select {
  flex: 1;
  min-width: 0;
  height: 36px;
  border: 1px solid var(--c-border);
  border-radius: var(--r-sm);
  padding: 0 10px;
  color: var(--c-text-1);
  background: var(--c-bg-elevated);
  font-family: inherit;
  font-size: var(--fs-sm);
}
.mobile-menu__divider {
  height: 1px;
  background: var(--c-border);
  margin: 8px 0;
}

/* ========== Mobile Bottom Nav ========== */
.mobile-nav {
  display: none;
  position: fixed;
  bottom: 0; left: 0; right: 0;
  height: var(--navbar-h);
  background: var(--c-surface-glass);
  border-top: 1px solid var(--c-border);
  backdrop-filter: blur(18px);
  z-index: 80;
}
.mobile-nav__item {
  flex: 1;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 3px;
  font-size: 11px;
  color: var(--c-text-3);
  text-decoration: none;
  transition: color var(--t-fast);
}
.mobile-nav__item--active { color: var(--c-primary); }

/* ========== Responsive ========== */
@media (max-width: 900px) {
  .topbar__nav { display: none; }
  .topbar__actions { display: none; }
  .topbar__hamburger { display: flex; }
  .subnav { display: none !important; }
  .mobile-overlay { display: block; }
  .mobile-menu { display: flex; flex-direction: column; }
  .mobile-nav { display: flex; }
  .content {
    padding: 16px;
    padding-bottom: calc(var(--navbar-h) + 16px);
  }
}
</style>

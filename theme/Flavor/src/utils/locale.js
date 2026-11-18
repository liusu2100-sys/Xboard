export const DEFAULT_LOCALE = 'zh-CN'
export const LOCALE_STORAGE_KEY = 'flavor_locale'

export const LOCALE_NAMES = {
  'zh-CN': '简体中文',
  'en-US': 'English',
  'ja-JP': '日本語',
  'vi-VN': 'Tiếng Việt',
  'ko-KR': '한국어',
  'zh-TW': '繁體中文',
  'fa-IR': 'فارسی'
}

export function getAvailableLocales() {
  const configured = window.settings?.i18n
  if (Array.isArray(configured) && configured.length) {
    return configured.filter(Boolean)
  }
  return [DEFAULT_LOCALE]
}

export function getCurrentLocale() {
  const locales = getAvailableLocales()
  const saved = localStorage.getItem(LOCALE_STORAGE_KEY)
  if (saved && locales.includes(saved)) return saved
  return locales.includes(DEFAULT_LOCALE) ? DEFAULT_LOCALE : locales[0]
}

export function setCurrentLocale(locale) {
  const locales = getAvailableLocales()
  const next = locales.includes(locale) ? locale : getCurrentLocale()
  localStorage.setItem(LOCALE_STORAGE_KEY, next)
  document.documentElement.setAttribute('lang', next)
  window.dispatchEvent(new CustomEvent('flavor-locale-changed', { detail: { locale: next } }))
  return next
}

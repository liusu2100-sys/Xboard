<template>
  <div class="page knowledge-page">
    <div class="page-header mb-4">
      <h2 class="page-title">{{ i18n.t('knowledge') }}</h2>
      <p class="text-2 mt-2">{{ i18n.t('knowledgeDesc') }}</p>
    </div>

    <!-- Active Article Modal/Override View -->
    <div v-if="activeArticle" class="article-view anim-fade-up">
      <div class="article-header mb-4">
        <button class="btn btn--secondary btn--sm mb-3" @click="activeArticle = null">
          <Icon name="chevron-left" :size="16" /> {{ i18n.t('backToKnowledge') }}
        </button>
        <h3 class="article-title">{{ activeArticle.title }}</h3>
        <div class="text-3 mt-1 fs-sm">{{ i18n.t('updatedAt') }}: {{ formatDate(activeArticle.updated_at) }}</div>
      </div>
      <div class="article-body card p-6" v-html="activeArticle.body"></div>
    </div>

    <!-- Categories & Articles Grid -->
    <div v-else>
      <div v-if="loading" class="dash-grid">
        <div class="skeleton" style="height: 150px" v-for="i in 4" :key="i"></div>
      </div>
      
      <div v-else-if="error" class="card text-center text-danger p-6">
        <Icon name="alert" :size="32" class="mb-2 mx-auto" />
        <p>{{ error }}</p>
      </div>

      <div v-else-if="Object.keys(knowledgeMap).length === 0" class="card text-center p-6">
        <Icon name="book" :size="48" class="text-3 mb-3 mx-auto" />
        <p class="text-2">{{ i18n.t('emptyKnowledge') }}</p>
      </div>

      <div v-else class="knowledge-categories">
        <div class="category-block mb-5" v-for="(articles, category) in knowledgeMap" :key="category">
          <h3 class="category-title mb-3">
            <Icon name="folder" :size="20" class="text-primary mr-2" />
            {{ category || i18n.t('commonKnowledge') }}
          </h3>
          <div class="dash-grid">
            <div class="card article-card" v-for="article in articles" :key="article.id" @click="fetchArticleBody(article)">
              <h4 class="article-card__title">{{ article.title }}</h4>
              <p class="article-card__time mt-auto">
                <Icon name="clock" :size="12" /> {{ formatDate(article.updated_at) }}
              </p>
              <div class="article-card__arrow">
                <Icon name="arrow-right" :size="16" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { getKnowledge } from '@/api/user'
import { objectFromPayload } from '@/api/normalizers'
import Icon from '@/components/ui/Icon.vue'
import { useAppStore } from '@/stores/app'
import { useI18nStore } from '@/stores/i18n'

const app = useAppStore()
const i18n = useI18nStore()
const loading = ref(true)
const error = ref('')
const knowledgeMap = ref({})
const activeArticle = ref(null)

function formatDate(ts) {
  if (!ts) return '-'
  return new Date(ts * 1000).toLocaleDateString(i18n.locale)
}

// Fetch list of all knowledges
async function fetchList() {
  loading.value = true
  error.value = ''
  try {
    const res = await getKnowledge()
    knowledgeMap.value = objectFromPayload(res)
  } catch (err) {
    error.value = err.message || i18n.t('knowledgeListLoadFailed')
  } finally {
    loading.value = false
  }
}

// Fetch single article body
async function fetchArticleBody(article) {
  if (article.body) {
    activeArticle.value = article
    return
  }
  
  // If no body provided in list, fetch it via ID
  const loaderId = app.showToast(i18n.t('loadingKnowledge'), 'info', 100000)
  try {
    const res = await getKnowledge({ id: article.id })
    const fullArticle = objectFromPayload(res, article)
    activeArticle.value = fullArticle
    app.toasts = app.toasts.filter(t => t.id !== loaderId) // hacky toast dismiss
  } catch (err) {
    app.toasts = app.toasts.filter(t => t.id !== loaderId)
    app.showToast(err.message || i18n.t('knowledgeBodyLoadFailed'), 'error')
  }
}

watch(() => i18n.locale, () => {
  activeArticle.value = null
  fetchList()
})

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.page-title {
  font-size: var(--fs-xl);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
}

.category-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
}
.dash-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 16px;
}

.article-card {
  position: relative;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-height: 120px;
  transition: all var(--t-fast);
  border: 1px solid var(--c-border);
}
.article-card:hover {
  border-color: var(--c-primary);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}
.article-card__title {
  font-size: var(--fs-base);
  font-weight: var(--fw-semibold);
  color: var(--c-text-1);
  line-height: 1.4;
  padding-right: 20px;
}
.article-card__time {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: var(--fs-xs);
  color: var(--c-text-3);
}
.article-card__arrow {
  position: absolute;
  top: 50%;
  right: 16px;
  transform: translateY(-50%) translateX(-4px);
  color: var(--c-border);
  opacity: 0;
  transition: all var(--t-fast);
}
.article-card:hover .article-card__arrow {
  opacity: 1;
  color: var(--c-primary);
  transform: translateY(-50%) translateX(0);
}

.article-view {
  animation: fadeIn 0.3s ease;
}
.article-title {
  font-size: 24px;
  font-weight: var(--fw-bold);
  color: var(--c-text-1);
}
.article-body {
  font-size: var(--fs-base);
  color: var(--c-text-2);
  line-height: 1.7;
}

/* Base styles for knowledge markdown HTML */
:deep(.article-body h1), :deep(.article-body h2), :deep(.article-body h3) {
  color: var(--c-text-1);
  margin-top: 1.5em;
  margin-bottom: 0.5em;
}
:deep(.article-body p) {
  margin-bottom: 1em;
}
:deep(.article-body img) {
  max-width: 100%;
  border-radius: var(--r-md);
  margin: 1em 0;
  box-shadow: var(--shadow-sm);
}
:deep(.article-body a) {
  color: var(--c-primary);
  text-decoration: none;
}
:deep(.article-body a:hover) {
  text-decoration: underline;
}
:deep(.article-body code) {
  background: var(--c-bg-elevated);
  padding: 0.2em 0.4em;
  border-radius: var(--r-sm);
  font-family: monospace;
  font-size: 0.9em;
}
:deep(.article-body pre) {
  background: var(--c-bg-elevated);
  padding: 1em;
  border-radius: var(--r-md);
  overflow-x: auto;
  margin: 1em 0;
}
:deep(.article-body pre code) {
  background: none;
  padding: 0;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

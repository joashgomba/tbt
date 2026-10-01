<template>
  <div>
    <h4 class="mb-1">{{ t('developerDocsTitle') }}</h4>
    <p class="text-muted mb-4">{{ t('developerDocsLead') }}</p>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header"><strong>{{ t('developerDocsCatalog') }}</strong></div>
          <ul class="list-group list-group-flush">
            <li
              v-for="doc in documents"
              :key="doc.slug"
              class="list-group-item list-group-item-action"
              :class="{ active: activeSlug === doc.slug }"
              role="button"
              @click="loadDoc(doc.slug)"
            >
              <div class="fw-semibold">{{ doc.title }}</div>
              <div class="small opacity-75">{{ doc.summary }}</div>
            </li>
          </ul>
        </div>
        <div v-if="baseUrls" class="card mt-3">
          <div class="card-header"><strong>{{ t('developerDocsBaseUrls') }}</strong></div>
          <div class="card-body small">
            <div v-for="(url, key) in baseUrls" :key="key" class="mb-2">
              <span class="text-muted text-uppercase">{{ key }}</span>
              <code class="d-block text-break">{{ url }}</code>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>{{ activeTitle || t('developerDocsSelect') }}</strong>
            <span v-if="updatedAt" class="small text-muted">{{ t('updated') }}: {{ formatDate(updatedAt) }}</span>
          </div>
          <div class="card-body developer-doc-body">
            <div v-if="loading" class="text-muted">{{ t('loading') }}</div>
            <pre v-else-if="content" class="developer-doc-pre">{{ content }}</pre>
            <p v-else class="text-muted mb-0">{{ t('developerDocsSelect') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const { t, locale } = useI18n()
const documents = ref([])
const baseUrls = ref(null)
const activeSlug = ref('')
const activeTitle = ref('')
const content = ref('')
const updatedAt = ref('')
const loading = ref(false)
const error = ref('')

onMounted(loadIndex)

async function loadIndex() {
  error.value = ''
  try {
    const { data } = await http.get('/developer-docs')
    documents.value = data.documents || []
    baseUrls.value = data.base_urls || null
    if (documents.value.length) {
      await loadDoc(documents.value[0].slug)
    }
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('developerDocsLoadError')
  }
}

async function loadDoc(slug) {
  activeSlug.value = slug
  const meta = documents.value.find((d) => d.slug === slug)
  activeTitle.value = meta?.title || slug
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get(`/developer-docs/${encodeURIComponent(slug)}`)
    content.value = data.content || ''
    updatedAt.value = data.updated_at || ''
  } catch (e) {
    content.value = ''
    error.value = e?.response?.data?.message || e?.message || t('developerDocsLoadError')
  } finally {
    loading.value = false
  }
}

function formatDate(v) {
  try {
    return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return v
  }
}
</script>

<style scoped>
.developer-doc-pre {
  white-space: pre-wrap;
  word-break: break-word;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.85rem;
  line-height: 1.55;
  margin: 0;
  background: #f8fafc;
  border-radius: 0.5rem;
  padding: 1rem 1.25rem;
}
.list-group-item.active .small {
  color: rgba(255, 255, 255, 0.85);
}
</style>

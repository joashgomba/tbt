<template>
  <div class="portal-wizard">
    <div class="portal-wizard__header">
      <h1 class="portal-wizard__title">{{ t('trackCase') }}</h1>
      <p class="portal-wizard__intro">{{ t('trackIntro') }}</p>
    </div>

    <div class="portal-wizard__body">
      <form class="portal-track-search" role="search" :aria-label="t('trackCase')" @submit.prevent="load">
        <label for="case-code" class="visually-hidden">{{ t('caseCode') }}</label>
        <input
          id="case-code"
          v-model="code"
          type="search"
          :placeholder="t('trackPlaceholder')"
          autocomplete="off"
          spellcheck="false"
          :aria-describedby="error ? 'track-error' : undefined"
        />
        <button type="submit" class="portal-btn portal-btn--accent" :disabled="loading" :aria-busy="loading">
          <i class="ti ti-search" aria-hidden="true"></i>
          {{ loading ? t('loading') : t('trackButton') }}
        </button>
      </form>

      <div v-if="error" id="track-error" class="portal-alert portal-alert--error mt-3" role="alert">
        {{ error }}
      </div>

      <div v-if="!complaint && !loading && !error && searched" class="portal-empty">
        <i class="ti ti-file-off" aria-hidden="true"></i>
        <p>{{ t('trackNotFound') }}</p>
      </div>

      <div v-if="!complaint && !loading && !error && !searched" class="portal-empty">
        <i class="ti ti-id" aria-hidden="true"></i>
        <p>{{ t('trackEmpty') }}</p>
      </div>

      <article v-if="complaint" class="portal-case-card" :aria-label="`${t('caseDetails')}: ${complaint.complaint_code}`">
        <header class="portal-case-card__header">
          <div>
            <p class="portal-case-code">{{ complaint.complaint_code }}</p>
            <h2 class="h5 mb-1 mt-1">{{ complaint.title }}</h2>
            <p class="text-muted small mb-0">
              {{ t('submittedOn') }}: {{ formatDate(complaint.created_at) }}
            </p>
          </div>
          <span :class="statusClass(complaint)" role="status">
            <i class="ti ti-point-filled" aria-hidden="true"></i>
            {{ statusLabel(complaint.status) }}
          </span>
        </header>

        <div class="portal-case-card__body">
          <p class="mb-0" style="line-height: 1.65;">{{ complaint.description }}</p>

          <div class="portal-meta-grid">
            <div class="portal-meta-item">
              <p class="portal-meta-item__label">{{ t('category') }}</p>
              <p class="portal-meta-item__value">{{ localizedName(complaint.category) }}</p>
            </div>
            <div class="portal-meta-item">
              <p class="portal-meta-item__label">{{ t('corridor') }}</p>
              <p class="portal-meta-item__value">{{ localizedName(complaint.corridor) }}</p>
            </div>
            <div class="portal-meta-item">
              <p class="portal-meta-item__label">{{ t('institution') }}</p>
              <p class="portal-meta-item__value">{{ complaint.assigned_institution?.institution_name || '—' }}</p>
            </div>
          </div>

          <div v-if="complaint.sla_deadline" class="portal-meta-item mt-3" style="max-width: 20rem;">
            <p class="portal-meta-item__label">{{ t('slaDeadline') }}</p>
            <p class="portal-meta-item__value">{{ formatDate(complaint.sla_deadline) }}</p>
          </div>

          <section class="portal-timeline" :aria-label="t('timeline')">
            <h3 class="portal-timeline__title">{{ t('timeline') }}</h3>
            <ul v-if="complaint.histories?.length" class="portal-timeline__list">
              <li v-for="h in complaint.histories" :key="h.id" class="portal-timeline__item">
                <span class="portal-timeline__dot" aria-hidden="true"></span>
                <div class="portal-timeline__content">
                  <p class="portal-timeline__action">{{ formatAction(h.action_type) }}</p>
                  <p class="portal-timeline__date">{{ formatDate(h.action_date) }}</p>
                  <p v-if="h.comment" class="portal-timeline__comment">{{ h.comment }}</p>
                </div>
              </li>
            </ul>
            <p v-else class="text-muted mb-0">{{ t('noTimeline') }}</p>
          </section>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const route = useRoute()
const { t, localizedName, locale } = useI18n()
const code = ref(String(route.query.code || ''))
const complaint = ref(null)
const loading = ref(false)
const error = ref('')
const searched = ref(false)

onMounted(() => {
  if (code.value.trim()) load()
})

async function load() {
  const trimmed = code.value.trim()
  if (!trimmed) return

  loading.value = true
  error.value = ''
  complaint.value = null
  searched.value = true

  try {
    const { data } = await http.get(`/public/complaints/${encodeURIComponent(trimmed)}`)
    complaint.value = data.complaint
  } catch {
    error.value = t('trackNotFound')
  } finally {
    loading.value = false
  }
}

function statusLabel(status) {
  return localizedName(status)
}

function statusClass(c) {
  const code = c.status?.code || c.status?.status_code || ''
  const base = 'portal-status-badge '
  if (/closed|resolved|rejected/i.test(code)) return base + 'portal-status-badge--closed'
  if (/process|assign|valid/i.test(code)) return base + 'portal-status-badge--progress'
  return base + 'portal-status-badge--open'
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

function formatAction(action) {
  if (!action) return ''
  return action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}
</script>

<style scoped>
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>

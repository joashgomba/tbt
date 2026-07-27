<template>
  <div class="aud-mgmt-enterprise cm-enterprise">
    <section class="aud-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-shield-check"></i></div>
              <div>
                <div class="hero-kicker">{{ t('audHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('audHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('audHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="aud-stat-grid">
              <div class="aud-stat"><div class="label">{{ t('audTotal') }}</div><div class="value">{{ summary.total ?? 0 }}</div></div>
              <div class="aud-stat"><div class="label">{{ t('audLast24h') }}</div><div class="value">{{ summary.last_24h ?? 0 }}</div></div>
              <div class="aud-stat"><div class="label">{{ t('audLast7d') }}</div><div class="value">{{ summary.last_7d ?? 0 }}</div></div>
              <div class="aud-stat"><div class="label">{{ t('audUniqueUsers') }}</div><div class="value">{{ summary.unique_users ?? 0 }}</div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="card aud-card mb-3">
      <div class="card-body py-3">
        <ul class="nav aud-tabs flex-wrap gap-2">
          <li v-for="tab in tabs" :key="tab.key">
            <button type="button" class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
              <i :class="tab.icon" class="me-1"></i>{{ tab.label }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div v-if="message" class="alert alert-success">{{ message }}</div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-if="loading && !bootstrapped" class="card aud-card"><div class="card-body text-center p-5 text-muted">{{ t('loading') }}</div></div>

    <template v-else>
      <!-- Overview -->
      <div v-show="activeTab === 'overview'">
        <div class="row g-4 mb-4">
          <div v-for="tile in overviewTiles" :key="tile.label" class="col-md-3">
            <div class="card aud-card h-100"><div class="card-body">
              <div class="small text-muted">{{ tile.label }}</div>
              <div class="fs-4 fw-bold">{{ tile.value }}</div>
            </div></div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-lg-8">
            <div class="card aud-card">
              <div class="card-header bg-white border-0">
                <h6 class="mb-0">{{ t('audActivityTimeline') }}</h6>
                <p class="small text-muted mb-0">{{ t('audActivityTimelineSub') }}</p>
              </div>
              <div class="card-body">
                <div v-if="!timeline.length" class="text-muted small">{{ t('uamNoData') }}</div>
                <div v-for="row in timeline" :key="row.day" class="mb-2">
                  <div class="d-flex justify-content-between small mb-1">
                    <span>{{ formatDay(row.day) }}</span><strong>{{ row.count }}</strong>
                  </div>
                  <div class="aud-timeline-bar"><span :style="{ width: timelineWidth(row.count) }"></span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card aud-card h-100">
              <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('audByModule') }}</h6></div>
              <div class="card-body">
                <ul class="list-unstyled small mb-0">
                  <li v-for="row in byModule" :key="row.module" class="d-flex justify-content-between py-1 border-bottom">
                    <span class="badge bg-label-secondary aud-module-badge">{{ row.module }}</span>
                    <strong>{{ row.count }}</strong>
                  </li>
                  <li v-if="!byModule.length" class="text-muted">{{ t('uamNoData') }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card aud-card">
              <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('audTopActions') }}</h6></div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <tbody>
                    <tr v-for="row in topActions" :key="row.action" class="cursor-pointer" @click="filterByAction(row.action)">
                      <td><span class="aud-action-badge">{{ row.action }}</span></td>
                      <td class="text-end">{{ row.count }}</td>
                    </tr>
                    <tr v-if="!topActions.length"><td colspan="2" class="text-muted p-3">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card aud-card">
              <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('audTopUsers') }}</h6></div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <tbody>
                    <tr v-for="row in topUsers" :key="row.id" class="cursor-pointer" @click="filterByUser(row.id)">
                      <td>{{ row.name }}<div class="small text-muted">{{ row.email }}</div></td>
                      <td class="text-end">{{ row.count }}</td>
                    </tr>
                    <tr v-if="!topUsers.length"><td colspan="2" class="text-muted p-3">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Explorer -->
      <div v-show="activeTab === 'explorer'">
        <div class="card aud-card mb-3"><div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">{{ t('search') }}</label>
              <input v-model="filters.search" type="search" class="form-control" placeholder="action, IP, values…" @keyup.enter="loadLogs(1)" /></div>
            <div class="col-md-2"><label class="form-label">{{ t('audModule') }}</label>
              <select v-model="filters.module" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="m in lookups.modules" :key="m" :value="m">{{ m }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('instAction') }}</label>
              <select v-model="filters.action" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="a in lookups.actions" :key="a" :value="a">{{ a }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('user') }}</label>
              <select v-model="filters.user_id" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="u in lookups.users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
              </select></div>
            <div class="col-md-1"><label class="form-label">{{ t('audFrom') }}</label>
              <input v-model="filters.date_from" type="date" class="form-control" /></div>
            <div class="col-md-1"><label class="form-label">{{ t('audTo') }}</label>
              <input v-model="filters.date_to" type="date" class="form-control" /></div>
            <div class="col-md-1"><button type="button" class="btn btn-primary w-100" @click="loadLogs(1)">{{ t('applyFilters') }}</button></div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-3"><label class="form-label">{{ t('audEntityType') }}</label>
              <select v-model="filters.entity_type" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="et in lookups.entity_types" :key="et.class" :value="et.class">{{ et.label }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('audEntityId') }}</label>
              <input v-model="filters.entity_id" type="number" min="1" class="form-control" /></div>
            <div class="col-md-2"><label class="form-label">{{ t('audIpAddress') }}</label>
              <input v-model="filters.ip_address" class="form-control" /></div>
            <div class="col-md-2"><button type="button" class="btn btn-outline-secondary" @click="clearFilters">{{ t('audClearFilters') }}</button></div>
          </div>
        </div></div>

        <div class="row g-4">
          <div :class="selected ? 'col-lg-7' : 'col-12'">
            <div class="card aud-card">
              <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="small text-muted">{{ t('audShowing') }} {{ logs.length }} / {{ meta.total ?? 0 }}</span>
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-secondary" :disabled="meta.current_page <= 1" @click="loadLogs(meta.current_page - 1)">{{ t('audPrev') }}</button>
                  <button type="button" class="btn btn-outline-secondary disabled">{{ meta.current_page ?? 1 }} / {{ meta.last_page ?? 1 }}</button>
                  <button type="button" class="btn btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="loadLogs(meta.current_page + 1)">{{ t('audNext') }}</button>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                  <thead>
                    <tr>
                      <th>{{ t('date') }}</th>
                      <th>{{ t('instAction') }}</th>
                      <th>{{ t('user') }}</th>
                      <th>{{ t('audEntity') }}</th>
                      <th>{{ t('audIpAddress') }}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="log in logs" :key="log.id" :class="{ 'table-active': selected?.id === log.id }">
                      <td class="small text-nowrap">{{ formatDate(log.created_at) }}</td>
                      <td>
                        <span class="badge bg-label-dark aud-module-badge me-1">{{ log.module }}</span>
                        <span class="aud-action-badge">{{ log.action }}</span>
                      </td>
                      <td class="small">{{ log.user?.name || t('audSystem') }}</td>
                      <td class="small">
                        <span v-if="log.entity_short_type">{{ log.entity_short_type }}</span>
                        <span v-if="log.entity_label" class="text-muted"> · {{ log.entity_label }}</span>
                        <span v-else-if="!log.entity_short_type">—</span>
                      </td>
                      <td class="small"><code>{{ log.ip_address || '—' }}</code></td>
                      <td><button type="button" class="btn btn-sm btn-link" @click="selectLog(log)">{{ t('view') }}</button></td>
                    </tr>
                    <tr v-if="!logs.length"><td colspan="6" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-if="selected" class="col-lg-5">
            <div class="card aud-card aud-detail-drawer">
              <div class="card-header bg-white d-flex justify-content-between">
                <div>
                  <h6 class="mb-1">{{ t('audEventDetail') }}</h6>
                  <span class="aud-action-badge">{{ detail?.log?.action || selected.action }}</span>
                </div>
                <button type="button" class="btn-close" @click="closeDetail"></button>
              </div>
              <div class="card-body pt-2">
                <ul class="nav nav-pills flex-wrap gap-1 mb-3">
                  <li v-for="dt in detailTabs" :key="dt.key">
                    <button type="button" class="nav-link py-1 px-2 small" :class="{ active: detailTab === dt.key }" @click="detailTab = dt.key">{{ dt.label }}</button>
                  </li>
                </ul>

                <div v-show="detailTab === 'summary'">
                  <div class="small mb-3">
                    <p class="mb-1"><strong>{{ t('date') }}:</strong> {{ formatDate(detail?.log?.created_at || selected.created_at) }}</p>
                    <p class="mb-1"><strong>{{ t('user') }}:</strong> {{ detail?.log?.user?.name || selected.user?.name || t('audSystem') }}
                      <span v-if="detail?.log?.user?.email" class="text-muted">({{ detail.log.user.email }})</span></p>
                    <p class="mb-1"><strong>{{ t('audModule') }}:</strong> {{ detail?.log?.module || selected.module }}</p>
                    <p class="mb-1"><strong>{{ t('audEntity') }}:</strong>
                      {{ detail?.log?.entity_short_type || selected.entity_short_type || '—' }}
                      <span v-if="detail?.log?.entity_label"> — {{ detail.log.entity_label }} (#{{ detail.log.entity_id }})</span></p>
                    <p class="mb-1"><strong>{{ t('audIpAddress') }}:</strong> <code>{{ detail?.log?.ip_address || selected.ip_address || '—' }}</code></p>
                    <p class="mb-0"><strong>{{ t('audUserAgent') }}:</strong>
                      <span class="text-muted">{{ truncate(detail?.log?.user_agent || selected.user_agent, 120) }}</span></p>
                  </div>
                  <button v-if="detail?.log?.entity_type && detail?.log?.entity_id" type="button" class="btn btn-sm btn-outline-primary" @click="loadEntityTrail">
                    {{ t('audViewEntityTrail') }}
                  </button>
                </div>

                <div v-show="detailTab === 'changes'">
                  <div v-if="!(detail?.log?.diff?.length)" class="text-muted small">{{ t('audNoChanges') }}</div>
                  <div v-else class="table-responsive aud-diff-table">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('audField') }}</th><th>{{ t('audOldValue') }}</th><th>{{ t('audNewValue') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="row in detail.log.diff" :key="row.field">
                          <td><code>{{ row.field }}</code></td>
                          <td><code>{{ formatValue(row.old) }}</code></td>
                          <td><code>{{ formatValue(row.new) }}</code></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div v-show="detailTab === 'payload'">
                  <h6 class="small fw-semibold">{{ t('audOldValues') }}</h6>
                  <pre class="small bg-light p-2 rounded mb-3">{{ prettyJson(detail?.log?.old_values) }}</pre>
                  <h6 class="small fw-semibold">{{ t('audNewValues') }}</h6>
                  <pre class="small bg-light p-2 rounded mb-0">{{ prettyJson(detail?.log?.new_values) }}</pre>
                </div>

                <div v-show="detailTab === 'related'">
                  <div class="table-responsive" style="max-height:360px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('date') }}</th><th>{{ t('instAction') }}</th><th>{{ t('user') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="rel in relatedLogs" :key="rel.id" class="cursor-pointer" @click="selectLog(rel)">
                          <td class="small">{{ formatDate(rel.created_at) }}</td>
                          <td class="small"><code>{{ rel.action }}</code></td>
                          <td class="small">{{ rel.user?.name || '—' }}</td>
                        </tr>
                        <tr v-if="!relatedLogs.length"><td colspan="3" class="text-muted">{{ t('audNoRelated') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const { t, locale } = useI18n()

const loading = ref(true)
const bootstrapped = ref(false)
const error = ref('')
const message = ref('')
const activeTab = ref('overview')
const detailTab = ref('summary')
const stats = ref({ summary: {}, by_module: [], top_actions: [], top_users: [], timeline: [] })
const lookups = ref({ modules: [], actions: [], entity_types: [], users: [] })
const logs = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const selected = ref(null)
const detail = ref(null)
const relatedLogs = ref([])
const filters = ref(emptyFilters())
const maxTimeline = ref(1)

const summary = computed(() => stats.value.summary || {})
const byModule = computed(() => stats.value.by_module || [])
const topActions = computed(() => stats.value.top_actions || [])
const topUsers = computed(() => stats.value.top_users || [])
const timeline = computed(() => stats.value.timeline || [])

const tabs = computed(() => [
  { key: 'overview', label: t('audTabOverview'), icon: 'ti ti-layout-dashboard' },
  { key: 'explorer', label: t('audTabExplorer'), icon: 'ti ti-list-search' },
])

const detailTabs = computed(() => [
  { key: 'summary', label: t('audTabSummary') },
  { key: 'changes', label: t('audTabChanges') },
  { key: 'payload', label: t('audTabPayload') },
  { key: 'related', label: t('audTabRelated') },
])

const overviewTiles = computed(() => [
  { label: t('audLast30d'), value: summary.value.last_30d ?? 0 },
  { label: t('audUniqueActions'), value: summary.value.unique_actions ?? 0 },
  { label: t('audUniqueUsers'), value: summary.value.unique_users ?? 0 },
  { label: t('audTotal'), value: summary.value.total ?? 0 },
])

function emptyFilters() {
  return { search: '', module: '', action: '', user_id: '', entity_type: '', entity_id: '', ip_address: '', date_from: '', date_to: '' }
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatDay(day) {
  if (!day) return '—'
  return new Date(day).toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { month: 'short', day: 'numeric' })
}

function timelineWidth(count) {
  const max = maxTimeline.value || 1
  return `${Math.max(4, (count / max) * 100)}%`
}

function truncate(s, n) {
  if (!s) return '—'
  return s.length > n ? s.slice(0, n) + '…' : s
}

function formatValue(v) {
  if (v === null || v === undefined) return '—'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function prettyJson(v) {
  if (!v) return '—'
  try { return JSON.stringify(v, null, 2) } catch { return String(v) }
}

function buildFilterParams(page = 1) {
  const p = { page, per_page: 50 }
  Object.entries(filters.value).forEach(([k, v]) => { if (v !== '') p[k] = v })
  return p
}

async function refresh() {
  error.value = ''
  message.value = ''
  await loadAll()
}

async function loadAll(initial = false) {
  if (initial) loading.value = true
  try {
    const [statsRes, lookupRes] = await Promise.all([
      http.get('/audit-mgmt/stats'),
      http.get('/audit-mgmt/lookups'),
    ])
    stats.value = statsRes.data
    lookups.value = lookupRes.data
    maxTimeline.value = Math.max(...(stats.value.timeline || []).map(r => r.count), 1)
    await loadLogs(1)
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('audLoadError')
  } finally {
    loading.value = false
  }
}

async function loadLogs(page = 1) {
  try {
    const { data } = await http.get('/audit-mgmt', { params: buildFilterParams(page) })
    logs.value = data.data || []
    meta.value = data.meta || { current_page: page, last_page: 1, total: logs.value.length }
    activeTab.value = 'explorer'
  } catch (e) {
    error.value = e?.response?.data?.message || t('audLoadError')
  }
}

async function selectLog(log) {
  selected.value = log
  detailTab.value = 'summary'
  activeTab.value = 'explorer'
  try {
    const { data } = await http.get(`/audit-mgmt/${log.id}`)
    detail.value = data
    relatedLogs.value = data.related || []
  } catch (e) {
    error.value = e?.response?.data?.message || t('audLoadError')
  }
}

function closeDetail() {
  selected.value = null
  detail.value = null
  relatedLogs.value = []
}

function clearFilters() {
  filters.value = emptyFilters()
  loadLogs(1)
}

function filterByAction(action) {
  filters.value = { ...emptyFilters(), action }
  loadLogs(1)
}

function filterByUser(userId) {
  filters.value = { ...emptyFilters(), user_id: String(userId) }
  loadLogs(1)
}

async function loadEntityTrail() {
  const log = detail.value?.log
  if (!log?.entity_type || !log?.entity_id) return
  try {
    const { data } = await http.get('/audit-mgmt/entity-trail', {
      params: { entity_type: log.entity_type, entity_id: log.entity_id },
    })
    relatedLogs.value = data.logs || []
    detailTab.value = 'related'
    message.value = t('audViewEntityTrail') + ': ' + (data.entity_label || log.entity_id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('audLoadError')
  }
}

onMounted(() => loadAll(true))
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>

<template>
  <div class="cm-enterprise">
    <!-- Hero -->
    <section class="cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-flag"></i></div>
              <div>
                <div class="hero-kicker">{{ t('cmHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('cmHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('cmHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <RouterLink class="btn btn-outline-light btn-sm" :to="{ name: 'public-report' }">
                <i class="ti ti-file-plus me-1"></i>{{ t('cmNewReport') }}
              </RouterLink>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="cm-stat-grid">
              <div class="cm-stat">
                <div class="label">{{ t('activePipeline') }}</div>
                <div class="value">{{ stats.summary.open }}</div>
              </div>
              <div class="cm-stat">
                <div class="label">{{ t('cmValidationQueue') }}</div>
                <div class="value text-warning">{{ stats.summary.validation_queue }}</div>
              </div>
              <div class="cm-stat">
                <div class="label">{{ t('closedCases') }}</div>
                <div class="value">{{ stats.summary.closed }}</div>
              </div>
              <div class="cm-stat">
                <div class="label">{{ t('slaBreached') }}</div>
                <div class="value" :class="{ 'text-warning': stats.summary.sla_breached > 0 }">{{ stats.summary.sla_breached }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Tabs -->
    <div class="card cm-card mb-3">
      <div class="card-body py-3">
        <ul class="nav cm-tabs flex-wrap gap-2">
          <li v-for="tab in tabs" :key="tab.key">
            <button type="button" class="nav-link" :class="{ active: activeTab === tab.key }" @click="setTab(tab.key)">
              <i :class="tab.icon" class="me-1"></i>{{ tab.label }}
              <span v-if="tab.badge" class="badge bg-danger ms-1">{{ tab.badge }}</span>
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Filters -->
    <div class="card cm-card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">{{ t('search') }}</label>
            <input v-model="filters.search" type="search" class="form-control" @keyup.enter="load" />
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('category') }}</label>
            <select v-model="filters.category_id" class="form-select">
              <option value="">{{ t('allCategories') }}</option>
              <option v-for="c in lookups.categories" :key="c.id" :value="String(c.id)">{{ localizedName(c) }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('institution') }}</label>
            <select v-model="filters.institution_id" class="form-select">
              <option value="">{{ t('allInstitutions') }}</option>
              <option v-for="i in lookups.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('priority') }}</label>
            <select v-model="filters.priority" class="form-select">
              <option value="">{{ t('allPriorities') }}</option>
              <option value="HIGH">HIGH</option>
              <option value="MED">MED</option>
              <option value="LOW">LOW</option>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="button" class="btn btn-primary" :disabled="loading" @click="load">{{ t('applyFilters') }}</button>
            <button type="button" class="btn btn-outline-secondary" @click="resetFilters">{{ t('resetFilters') }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Leaderboard -->
    <div class="row g-3 mb-4">
      <div class="col-12">
        <div class="card cm-card admin-lb-card">
          <div class="card-header d-flex justify-content-between align-items-center bg-transparent">
            <div>
              <h5 class="mb-0"><i class="ti ti-trophy me-2"></i>{{ t('institutionSlaPerformance') }}</h5>
              <div class="text-muted small">{{ t('institutionLeaderboardSub') }}</div>
            </div>
            <span class="badge bg-label-secondary">{{ t('filteredPeriod') }}</span>
          </div>
          <div class="table-responsive">
            <table class="table admin-lb-table mb-0">
              <thead>
                <tr>
                  <th>{{ t('institution') }}</th>
                  <th class="text-end">{{ t('casesAssigned') }}</th>
                  <th class="text-end">{{ t('slaBreached') }}</th>
                  <th class="text-end">{{ t('slaCompliancePct') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!stats.leaderboard.length">
                  <td colspan="4" class="text-muted p-4">{{ t('noLeaderboardData') }}</td>
                </tr>
                <tr v-for="(row, idx) in stats.leaderboard" :key="row.institution + idx">
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span class="admin-leaderboard-rank" :class="rankClass(idx + 1)">{{ idx + 1 }}</span>
                      <div>
                        <div class="fw-semibold">{{ row.institution }}</div>
                        <div v-if="row.institution_type" class="text-muted small text-uppercase">{{ row.institution_type }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="text-end">{{ row.cases_assigned }}</td>
                  <td class="text-end">{{ row.sla_breached }}</td>
                  <td class="text-end"><span class="admin-lb-pct" :class="pctClass(row.sla_pct)">{{ pct(row.sla_pct) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Cases table -->
    <div class="card cm-card">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ t('cmCaseRegister') }}</h5>
        <span class="text-muted small">{{ pagination.total }} {{ t('totalCases').toLowerCase() }}</span>
      </div>
      <div class="table-responsive">
        <table class="table cm-table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>{{ t('caseCode') }}</th>
              <th>{{ t('title') }}</th>
              <th>{{ t('category') }}</th>
              <th>{{ t('status') }}</th>
              <th>{{ t('priority') }}</th>
              <th>{{ t('institution') }}</th>
              <th>{{ t('submittedOn') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="8" class="text-center py-5 text-muted">{{ t('loading') }}</td>
            </tr>
            <tr v-else-if="!rows.length">
              <td colspan="8" class="text-center py-5 text-muted">{{ t('cmNoCases') }}</td>
            </tr>
            <tr v-for="row in rows" :key="row.id">
              <td class="fw-semibold">{{ row.complaint_code }}</td>
              <td>{{ row.title }}</td>
              <td><span class="badge bg-label-primary">{{ row.category?.code }}</span></td>
              <td><span class="badge bg-label-secondary">{{ localizedName(row.status) }}</span></td>
              <td><span :class="'cm-priority-' + row.priority">{{ row.priority }}</span></td>
              <td class="small">{{ row.assigned_institution?.institution_name || '—' }}</td>
              <td class="small text-muted">{{ formatDate(row.created_at) }}</td>
              <td>
                <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'admin-complaint-detail', params: { id: row.id } }">
                  {{ t('view') }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="pagination.last_page > 1" class="card-footer bg-transparent d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">←</button>
        <span class="small text-muted">{{ t('page') }} {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">→</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const { t, localizedName, locale } = useI18n()

const loading = ref(false)
const activeTab = ref('active')
const rows = ref([])
const stats = ref({ summary: { open: 0, validation_queue: 0, closed: 0, archived: 0, sla_breached: 0 }, leaderboard: [] })
const lookups = ref({ categories: [], institutions: [] })
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })

const filters = ref({ search: '', category_id: '', institution_id: '', priority: '' })

const tabs = computed(() => [
  { key: 'active', label: t('cmTabActive'), icon: 'ti ti-list', badge: stats.value.summary.open || null },
  { key: 'validation', label: t('cmTabValidation'), icon: 'ti ti-checkbox', badge: stats.value.summary.validation_queue || null },
  { key: 'closed', label: t('cmTabClosed'), icon: 'ti ti-archive', badge: null },
  { key: 'sla_alerts', label: t('cmTabSlaAlerts'), icon: 'ti ti-alert-triangle', badge: stats.value.summary.sla_breached || null },
])

onMounted(async () => {
  await Promise.all([loadLookups(), loadStats(), load()])
})

async function loadLookups() {
  const { data } = await http.get('/complaints/lookups').catch(() => ({ data: {} }))
  lookups.value = {
    categories: data.categories || [],
    institutions: data.institutions || [],
  }
}

async function loadStats() {
  try {
    const { data } = await http.get('/complaints/stats')
    stats.value = data
  } catch {
    // keep defaults on error
  }
}

async function load(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      tab: activeTab.value,
      search: filters.value.search || undefined,
      category_id: filters.value.category_id || undefined,
      institution_id: filters.value.institution_id || undefined,
      priority: filters.value.priority || undefined,
    }
    const { data } = await http.get('/complaints', { params })
    rows.value = data.data || []
    pagination.value = {
      current_page: data.current_page || 1,
      last_page: data.last_page || 1,
      total: data.total || rows.value.length,
    }
  } catch {
    rows.value = []
  } finally {
    loading.value = false
  }
}

function setTab(key) {
  activeTab.value = key
  load(1)
}

function refresh() {
  loadStats()
  load(pagination.value.current_page)
}

function resetFilters() {
  filters.value = { search: '', category_id: '', institution_id: '', priority: '' }
  load(1)
}

function goPage(page) {
  load(page)
}

function rankClass(rank) {
  if (rank === 1) return 'admin-leaderboard-rank--1'
  if (rank === 2) return 'admin-leaderboard-rank--2'
  if (rank === 3) return 'admin-leaderboard-rank--3'
  return ''
}

function pctClass(v) {
  if (v >= 90) return 'admin-lb-pct--good'
  if (v >= 75) return 'admin-lb-pct--warn'
  return 'admin-lb-pct--bad'
}

function pct(n) {
  return `${Number(n || 0).toFixed(1)}%`
}

function formatDate(v) {
  return v ? new Date(v).toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-GB') : '—'
}
</script>

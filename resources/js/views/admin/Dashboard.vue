<template>
  <div class="admin-dash-enterprise">
    <!-- Hero board -->
    <section class="hero-board card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem" aria-hidden="true"><i class="ti ti-layout-dashboard"></i></div>
              <div>
                <div class="hero-kicker">{{ heroKicker }}</div>
                <h1 class="hero-title mb-2">{{ t('dashboardHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('dashboardHeroSubtitle') }}</p>
                <p v-if="me?.name" class="hero-user mb-0 mt-2">
                  {{ t('dashboardWelcome') }} <strong>{{ me.name }}</strong>
                  <span v-if="me.institution"> · {{ me.institution }}</span>
                </p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refreshNow">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <button type="button" class="btn btn-outline-light btn-sm" @click="exportCsv">
                <i class="ti ti-table me-1"></i>{{ t('exportExcel') }}
              </button>
              <RouterLink class="btn btn-outline-light btn-sm" :to="{ name: 'admin-complaints' }">
                <i class="ti ti-list me-1"></i>{{ t('viewAllCases') }}
              </RouterLink>
              <RouterLink class="btn btn-outline-light btn-sm" :to="{ name: 'public-home' }">
                <i class="ti ti-world me-1"></i>{{ t('publicPortal') }}
              </RouterLink>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="hero-stats-grid">
              <div class="hero-stat">
                <div class="label">{{ t('activePipeline') }}</div>
                <div class="value">{{ data.summary.open }}</div>
              </div>
              <div class="hero-stat">
                <div class="label">{{ t('slaBreached') }}</div>
                <div class="value" :class="{ 'text-warning': data.summary.sla_breached > 0 }">{{ data.summary.sla_breached }}</div>
              </div>
              <div class="hero-stat">
                <div class="label">{{ t('resolutionRate') }}</div>
                <div class="value">{{ data.summary.resolution_rate }}%</div>
              </div>
              <div class="hero-stat">
                <div class="label">{{ t('lastSync') }}</div>
                <div class="value small">{{ lastSyncLabel }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Institution scope -->
    <div v-if="me?.institution_id && !canViewAll" class="alert alert-info border-0 shadow-sm mb-4 d-flex align-items-center gap-3">
      <i class="ti ti-building fs-4" aria-hidden="true"></i>
      <div>
        <strong>{{ t('institutionScopedDashboard') }}</strong>
        <div class="small">{{ t('institutionScopedText') }} <strong>{{ me.institution }}</strong>.</div>
      </div>
    </div>

    <!-- SLA breach alert -->
    <div v-if="data.summary.sla_breached > 0" class="alert alert-danger d-flex align-items-start gap-3 border-0 shadow-sm mb-4 sla-alert-banner">
      <i class="ti ti-alert-triangle fs-3" aria-hidden="true"></i>
      <div class="flex-grow-1">
        <strong>{{ data.summary.sla_breached }} {{ t('slaBreachesActive') }}</strong>
        <div class="small mb-2">{{ t('slaBreachesHint') }}</div>
        <div class="sla-preview-list">
          <div
            v-for="row in topSlaInstitutions"
            :key="row.institution"
            class="sla-preview-item"
          >
            <span class="fw-semibold">{{ row.institution }}</span>
            <span class="mx-1 opacity-50">·</span>
            <span>{{ row.sla_breached }} {{ t('breaches') }}</span>
          </div>
        </div>
      </div>
      <RouterLink class="btn btn-sm btn-light" :to="{ name: 'admin-complaints' }">{{ t('reviewAll') }}</RouterLink>
    </div>

    <!-- Filters -->
    <div class="card admin-dash-card mb-4 filter-card">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0"><i class="ti ti-filter me-2"></i>{{ t('dashboardFilters') }}</h6>
        <button type="button" class="btn btn-sm btn-link" @click="filtersOpen = !filtersOpen">
          {{ filtersOpen ? t('collapseFilters') : t('expandFilters') }}
        </button>
      </div>
      <div v-show="filtersOpen" class="card-body pt-0">
        <div class="row g-3 align-items-end">
          <div class="col-xl-2 col-md-4">
            <label class="form-label">{{ t('filterYear') }}</label>
            <select v-model="filters.year" class="form-select">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div class="col-xl-2 col-md-4">
            <label class="form-label">{{ t('filterFromMonth') }}</label>
            <input v-model="filters.from_month" type="month" class="form-control" />
          </div>
          <div class="col-xl-2 col-md-4">
            <label class="form-label">{{ t('filterToMonth') }}</label>
            <input v-model="filters.to_month" type="month" class="form-control" />
          </div>
          <div class="col-xl-2 col-md-4">
            <label class="form-label">{{ t('category') }}</label>
            <select v-model="filters.category_id" class="form-select">
              <option value="">{{ t('allCategories') }}</option>
              <option v-for="c in options.categories" :key="c.id" :value="String(c.id)">{{ localizedName(c) }}</option>
            </select>
          </div>
          <div class="col-xl-2 col-md-4">
            <label class="form-label">{{ t('corridor') }}</label>
            <select v-model="filters.corridor_id" class="form-select">
              <option value="">{{ t('allCorridors') }}</option>
              <option v-for="c in options.corridors" :key="c.id" :value="String(c.id)">{{ localizedName(c) }}</option>
            </select>
          </div>
          <div class="col-xl-2 col-md-4">
            <label class="form-label">{{ t('institution') }}</label>
            <select v-model="filters.institution_id" class="form-select">
              <option value="">{{ t('allInstitutions') }}</option>
              <option v-for="i in options.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
            </select>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="button" class="btn btn-primary btn-sm" :disabled="loading" @click="applyFilters">
            {{ t('applyFilters') }}
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="resetFilters">
            {{ t('resetFilters') }}
          </button>
        </div>
        <p class="text-muted small mt-3 mb-0">{{ t('dashboardFilterTip') }}</p>
      </div>
    </div>

    <div v-if="loading && !bootstrapped" class="card admin-dash-card">
      <div class="card-body admin-dash-empty">{{ t('loading') }}</div>
    </div>

    <template v-else>
      <!-- KPI tiles -->
      <div class="row g-3 mb-3">
        <div v-for="tile in kpiTiles" :key="tile.label" class="col-xl-2 col-md-4 col-sm-6">
          <div class="card admin-kpi" :class="tile.class">
            <div class="card-body d-flex justify-content-between align-items-start">
              <div>
                <div class="kpi-label">{{ tile.label }}</div>
                <div class="kpi-value">{{ tile.value }}</div>
                <div class="kpi-sub">{{ tile.sub }}</div>
              </div>
              <div class="kpi-icon"><i :class="tile.icon"></i></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Institution SLA leaderboard (template-style, prominent at top) -->
      <div class="row g-3 mb-3">
        <div class="col-12">
          <div class="card admin-dash-card admin-lb-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div>
                <h5 class="mb-0"><i class="ti ti-trophy me-2"></i>{{ t('institutionSlaPerformance') }}</h5>
                <div class="text-muted small">{{ t('institutionLeaderboardSub') }}</div>
              </div>
              <span class="badge bg-label-secondary">{{ t('filteredPeriod') }}</span>
            </div>
            <div class="card-body">
              <div class="admin-lb-summary row g-3 mb-4">
                <div class="col-md-4">
                  <div class="admin-lb-summary__item">
                    <span class="admin-lb-summary__label">{{ t('overallSlaCompliance') }}</span>
                    <span class="admin-lb-summary__value">{{ pct(data.institution_performance.overall) }}</span>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="admin-lb-summary__item">
                    <span class="admin-lb-summary__label">{{ t('overallResolutionRate') }}</span>
                    <span class="admin-lb-summary__value">{{ pct(data.institution_performance.overall_resolution_rate) }}</span>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="admin-lb-summary__item">
                    <span class="admin-lb-summary__label">{{ t('institutionsRanked') }}</span>
                    <span class="admin-lb-summary__value">{{ data.institution_performance.top.length }}</span>
                  </div>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table admin-lb-table mb-0">
                  <thead>
                    <tr>
                      <th>{{ t('institution') }}</th>
                      <th class="text-end">{{ t('casesAssigned') }}</th>
                      <th class="text-end">{{ t('resolvedOnTime') }}</th>
                      <th class="text-end">{{ t('slaCompliancePct') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!data.institution_performance.top.length">
                      <td colspan="4" class="text-muted">{{ t('noLeaderboardData') }}</td>
                    </tr>
                    <tr v-for="(row, idx) in data.institution_performance.top" :key="row.institution + idx">
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
                      <td class="text-end">{{ row.resolved }}</td>
                      <td class="text-end">
                        <span class="admin-lb-pct" :class="pctClass(row.pct)">{{ pct(row.pct) }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <details v-if="data.leaderboard.length" class="admin-lb-details mt-4">
                <summary>{{ t('viewDetailedBreakdown') }}</summary>
                <div class="table-responsive mt-3">
                  <table class="table table-sm admin-leaderboard-table mb-0">
                    <thead>
                      <tr>
                        <th>{{ t('institution') }}</th>
                        <th class="text-end">{{ t('openCases') }}</th>
                        <th class="text-end">{{ t('resolutionRate') }}</th>
                        <th class="text-end">{{ t('avgResolutionDays') }}</th>
                        <th class="text-end">{{ t('slaBreached') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in data.leaderboard" :key="row.institution?.id">
                        <td>{{ row.institution?.institution_name || '—' }}</td>
                        <td class="text-end">{{ row.open_cases }}</td>
                        <td class="text-end">{{ pct(row.resolution_rate) }}</td>
                        <td class="text-end">{{ row.avg_resolution_days ?? '—' }}</td>
                        <td class="text-end">{{ row.sla_breached }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </details>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts row 1 -->
      <div class="row g-3">
        <div class="col-xl-8">
          <div class="card admin-dash-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0">{{ t('chartCasesByMonth') }}</h5>
              <span class="badge bg-label-primary">{{ t('chartArea') }}</span>
            </div>
            <div class="card-body">
              <apexchart
                v-if="monthSeries[0].data.length"
                type="area"
                height="280"
                :options="monthOptions"
                :series="monthSeries"
              />
              <div v-else class="admin-dash-empty">{{ t('noChartData') }}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="card admin-dash-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0">{{ t('chartByCategory') }}</h5>
              <span class="badge bg-label-success">{{ t('chartDonut') }}</span>
            </div>
            <div class="card-body">
              <apexchart
                v-if="categorySeries.length"
                type="donut"
                height="280"
                :options="categoryOptions"
                :series="categorySeries"
              />
              <div v-else class="admin-dash-empty">{{ t('noChartData') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts row 2 -->
      <div class="row g-3 mt-1">
        <div class="col-xl-6">
          <div class="card admin-dash-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0">{{ t('chartYearTrend') }} ({{ filters.year }})</h5>
              <span class="badge bg-label-warning">{{ t('chartLine') }}</span>
            </div>
            <div class="card-body">
              <apexchart
                v-if="trendSeries[0].data.some((v) => v > 0)"
                type="line"
                height="280"
                :options="trendOptions"
                :series="trendSeries"
              />
              <div v-else class="admin-dash-empty">{{ t('noChartData') }}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-6">
          <div class="card admin-dash-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0">{{ t('topInstitutions') }}</h5>
              <span class="badge bg-label-info">{{ t('chartBar') }}</span>
            </div>
            <div class="card-body">
              <apexchart
                v-if="institutionBarSeries[0].data.length"
                type="bar"
                height="280"
                :options="institutionBarOptions"
                :series="institutionBarSeries"
              />
              <div v-else class="admin-dash-empty">{{ t('noLeaderboardData') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Status breakdown -->
      <div class="row g-3 mt-1">
        <div class="col-xl-6">
          <div class="card admin-dash-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0">{{ t('chartByStatus') }}</h5>
              <span class="badge bg-label-info">{{ t('chartBar') }}</span>
            </div>
            <div class="card-body">
              <apexchart
                v-if="statusSeries[0].data.length"
                type="bar"
                height="260"
                :options="statusOptions"
                :series="statusSeries"
              />
              <div v-else class="admin-dash-empty">{{ t('noChartData') }}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-6">
          <div class="card admin-dash-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0">{{ t('chartByCorridor') }}</h5>
              <span class="badge bg-label-primary">{{ t('chartBar') }}</span>
            </div>
            <div class="card-body">
              <apexchart
                v-if="corridorSeries[0].data.length"
                type="bar"
                height="260"
                :options="corridorOptions"
                :series="corridorSeries"
              />
              <div v-else class="admin-dash-empty">{{ t('noChartData') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Map -->
      <div class="row g-3 mt-1">
        <div class="col-12">
          <div class="card admin-dash-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0">{{ t('chartCasesMap') }}</h5>
              <span class="badge bg-label-secondary">{{ t('chartMap') }}</span>
            </div>
            <div class="card-body">
              <PublicBarrierMap
                v-if="data.map_points.length"
                :points="data.map_points"
                :aria-label="t('chartCasesMap')"
              />
              <div v-else class="admin-dash-empty">{{ t('noMapData') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent cases -->
      <div class="row g-3 mt-1">
        <div class="col-12">
          <div class="card admin-dash-card">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h5 class="mb-0">{{ t('recentCases') }}</h5>
              <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'admin-complaints' }">
                {{ t('viewAll') }}
              </RouterLink>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>{{ t('caseCode') }}</th>
                    <th>{{ t('title') }}</th>
                    <th>{{ t('category') }}</th>
                    <th>{{ t('status') }}</th>
                    <th>{{ t('institution') }}</th>
                    <th>{{ t('submittedOn') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!data.recent.length">
                    <td colspan="6" class="text-muted p-4">{{ t('noRecentCases') }}</td>
                  </tr>
                  <tr v-for="row in data.recent" :key="row.id">
                    <td>
                      <RouterLink :to="{ name: 'admin-complaint-detail', params: { id: row.id } }">
                        {{ row.complaint_code }}
                      </RouterLink>
                    </td>
                    <td>{{ row.title }}</td>
                    <td>{{ localizedName(row.category) }}</td>
                    <td><span class="badge bg-label-primary">{{ localizedName(row.status) }}</span></td>
                    <td>{{ row.assigned_institution?.institution_name || '—' }}</td>
                    <td class="text-muted small">{{ formatDate(row.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'
import PublicBarrierMap from '@/components/public/PublicBarrierMap.vue'

const { t, localizedName, locale } = useI18n()

const loading = ref(true)
const bootstrapped = ref(false)
const error = ref('')
const me = ref(null)
const filtersOpen = ref(true)
const lastSync = ref(null)
let refreshTimer = null

const yearOptions = (() => {
  const y = new Date().getFullYear()
  return [y - 3, y - 2, y - 1, y, y + 1]
})()

const filters = ref({
  year: new Date().getFullYear(),
  from_month: '',
  to_month: '',
  category_id: '',
  corridor_id: '',
  institution_id: '',
})

const options = ref({ categories: [], corridors: [], institutions: [], statuses: [] })

const data = ref({
  summary: { total: 0, open: 0, closed: 0, resolution_rate: 0, sla_breached: 0, sla_compliance: 100, avg_resolution_days: null },
  by_month: [],
  trend_year: [],
  by_status: [],
  by_category: [],
  by_corridor: [],
  leaderboard: [],
  institution_performance: { overall: 0, overall_resolution_rate: 0, top: [] },
  map_points: [],
  recent: [],
})

const kpiTiles = computed(() => {
  const s = data.value.summary
  return [
    { label: t('totalCases'), value: s.total, sub: t('filteredPeriod'), icon: 'ti ti-files', class: 'admin-kpi-indigo' },
    { label: t('openCases'), value: s.open, sub: t('activePipeline'), icon: 'ti ti-clock', class: 'admin-kpi-amber' },
    { label: t('closedCases'), value: s.closed, sub: t('terminalStatuses'), icon: 'ti ti-circle-check', class: 'admin-kpi-emerald' },
    { label: t('resolutionRate'), value: `${s.resolution_rate}%`, sub: t('closedVsTotal'), icon: 'ti ti-chart-pie', class: 'admin-kpi-fuchsia' },
    { label: t('slaCompliance'), value: `${s.sla_compliance}%`, sub: t('withinSlaTarget'), icon: 'ti ti-shield-check', class: 'admin-kpi-cyan' },
    { label: t('avgResolutionDays'), value: s.avg_resolution_days ?? '—', sub: t('daysToClose'), icon: 'ti ti-calendar-stats', class: 'admin-kpi-slate' },
  ]
})

const canViewAll = computed(() => me.value?.permissions?.includes('complaints.view_all') ?? false)

const heroKicker = computed(() => {
  const roles = me.value?.roles || []
  if (roles.includes('system_administrator')) return t('heroKickerAdmin')
  if (roles.includes('national_focal_point')) return t('heroKickerFocal')
  if (roles.includes('institutional_officer') || roles.includes('institutional_administrator')) return t('heroKickerInstitution')
  return t('heroKickerDefault')
})

const lastSyncLabel = computed(() =>
  lastSync.value ? new Date(lastSync.value).toLocaleTimeString(locale.value === 'fr' ? 'fr-FR' : 'en-GB') : '—',
)

const topSlaInstitutions = computed(() =>
  [...(data.value.leaderboard || [])]
    .filter((r) => r.sla_breached > 0)
    .sort((a, b) => b.sla_breached - a.sla_breached)
    .slice(0, 4)
    .map((r) => ({
      institution: r.institution?.institution_name || '—',
      sla_breached: r.sla_breached,
    })),
)

function buildParams() {
  const p = {}
  if (filters.value.year) p.year = filters.value.year
  if (filters.value.from_month) p.from_month = filters.value.from_month
  if (filters.value.to_month) p.to_month = filters.value.to_month
  if (filters.value.category_id) p.category_id = filters.value.category_id
  if (filters.value.corridor_id) p.corridor_id = filters.value.corridor_id
  if (filters.value.institution_id) p.institution_id = filters.value.institution_id
  return p
}

async function load(manual = true) {
  if (manual) loading.value = true
  error.value = ''
  try {
    const [meRes, dashRes] = await Promise.all([
      http.get('/auth/me').catch(() => ({ data: {} })),
      http.get('/dashboard', { params: buildParams() }),
    ])
    me.value = meRes.data?.user || null
    data.value = {
      summary: dashRes.data.summary,
      by_month: dashRes.data.by_month || [],
      trend_year: dashRes.data.trend_year || [],
      by_status: dashRes.data.by_status || [],
      by_category: dashRes.data.by_category || [],
      by_corridor: dashRes.data.by_corridor || [],
      leaderboard: dashRes.data.leaderboard || [],
      institution_performance: dashRes.data.institution_performance || { overall: 0, overall_resolution_rate: 0, top: [] },
      map_points: dashRes.data.map_points || [],
      recent: dashRes.data.recent || [],
    }
    if (dashRes.data.filter_options) {
      options.value = dashRes.data.filter_options
    }
    lastSync.value = new Date().toISOString()
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('dashboardLoadError')
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  if (filters.value.from_month && !filters.value.to_month) filters.value.to_month = filters.value.from_month
  if (!filters.value.from_month && filters.value.to_month) filters.value.from_month = filters.value.to_month
  load()
}

function resetFilters() {
  filters.value = {
    year: new Date().getFullYear(),
    from_month: '',
    to_month: '',
    category_id: '',
    corridor_id: '',
    institution_id: '',
  }
  load()
}

function refreshNow() {
  load(true)
}

function exportCsv() {
  window.open('/api/v1/reports/export.csv', '_blank')
}

function rankClass(rank) {
  if (rank === 1) return 'admin-leaderboard-rank--1'
  if (rank === 2) return 'admin-leaderboard-rank--2'
  if (rank === 3) return 'admin-leaderboard-rank--3'
  return ''
}

function pct(n) {
  return `${Number(n || 0).toFixed(2)}%`
}

function pctClass(value) {
  if (value >= 90) return 'admin-lb-pct--good'
  if (value >= 70) return 'admin-lb-pct--ok'
  return 'admin-lb-pct--low'
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium' })
}

const monthSeries = computed(() => [{
  name: t('cases'),
  data: data.value.by_month.map((x) => Number(x.total || 0)),
}])

const monthOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.05 } },
  colors: ['#4f46e5'],
  xaxis: { categories: data.value.by_month.map((x) => x.month) },
}))

const trendSeries = computed(() => [{
  name: t('cases'),
  data: data.value.trend_year.map((x) => Number(x.total || 0)),
}])

const trendOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 3 },
  colors: ['#f59e0b'],
  xaxis: { categories: data.value.trend_year.map((x) => x.month) },
}))

const categorySeries = computed(() => data.value.by_category.map((x) => Number(x.count || 0)))
const categoryOptions = computed(() => ({
  labels: data.value.by_category.map((x) => x.category?.code || 'N/A'),
  legend: { position: 'bottom' },
  colors: ['#4f46e5', '#059669', '#f59e0b', '#c026d3', '#0b4f6c', '#ef4444'],
}))

const statusSeries = computed(() => [{
  name: t('cases'),
  data: data.value.by_status.map((x) => x.complaints_count || 0),
}])

const statusOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
  dataLabels: { enabled: false },
  colors: ['#0891b2'],
  xaxis: { categories: data.value.by_status.map((x) => localizedName(x)) },
}))

const corridorSeries = computed(() => [{
  name: t('cases'),
  data: data.value.by_corridor.map((x) => Number(x.count || 0)),
}])

const corridorOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
  dataLabels: { enabled: false },
  colors: ['#6366f1'],
  xaxis: { categories: data.value.by_corridor.map((x) => localizedName(x.corridor) || x.corridor?.corridor_code) },
}))

const institutionBarSeries = computed(() => [{
  name: t('casesAssigned'),
  data: data.value.institution_performance.top.slice(0, 8).map((x) => Number(x.cases_assigned || 0)),
}])

const institutionBarOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
  dataLabels: { enabled: false },
  colors: ['#0891b2'],
  xaxis: {
    categories: data.value.institution_performance.top.slice(0, 8).map((x) => x.institution),
  },
  tooltip: { y: { formatter: (v) => `${v} ${t('cases').toLowerCase()}` } },
}))

onMounted(() => {
  load()
  refreshTimer = setInterval(() => load(false), 60000)
})

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<style scoped>
:deep(.portal-map-wrap) {
  height: 280px;
  border-radius: 0.75rem;
  border: none;
}
</style>

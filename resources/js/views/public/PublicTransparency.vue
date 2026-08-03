<template>
  <div>
    <section class="portal-section" aria-labelledby="transparency-title">
      <div class="portal-section__header">
        <h1 id="transparency-title" class="portal-section__title">{{ t('transparencyTitle') }}</h1>
        <p class="portal-section__subtitle">{{ t('transparencySubtitle') }}</p>
      </div>

      <div v-if="statsLoading" class="portal-empty">{{ t('loading') }}</div>
      <div v-else class="portal-stats-grid mb-4" role="list">
        <div v-for="item in statCards" :key="item.key" class="portal-stat-card" role="listitem">
          <div class="portal-stat-card__value">{{ item.value }}</div>
          <div class="portal-stat-card__label">
            {{ item.label }}
            <span class="portal-stat-card__tooltip" tabindex="0" :aria-label="item.tooltip">
              <i class="ti ti-info-circle" aria-hidden="true"></i>
              <span class="portal-stat-card__tooltip-text" role="tooltip">{{ item.tooltip }}</span>
            </span>
          </div>
          <p v-if="filtersActive" class="portal-stat-card__filtered">{{ t('statsFilteredHint') }}</p>
        </div>
      </div>
    </section>

    <section class="portal-track-widget" aria-labelledby="track-widget-title">
      <h2 id="track-widget-title" class="portal-track-widget__title">{{ t('trackWidgetTitle') }}</h2>
      <p class="portal-track-widget__text">{{ t('trackWidgetText') }}</p>
      <form class="portal-track-search" @submit.prevent="goTrack">
        <label for="widget-code" class="visually-hidden">{{ t('caseCode') }}</label>
        <input id="widget-code" v-model="trackCode" type="search" :placeholder="t('trackPlaceholder')" />
        <button type="submit" class="portal-btn portal-btn--accent">{{ t('trackButton') }}</button>
      </form>
    </section>

    <section class="portal-section" aria-labelledby="browse-title">
      <div class="portal-section__header d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div>
          <h2 id="browse-title" class="portal-section__title">{{ t('browseReports') }}</h2>
          <p class="portal-section__subtitle mb-0">{{ t('browseReportsSubtitle') }}</p>
        </div>
        <div class="portal-view-toggle" role="group" :aria-label="t('viewMode')">
          <button type="button" :aria-pressed="viewMode === 'list'" @click="viewMode = 'list'">
            <i class="ti ti-list" aria-hidden="true"></i> {{ t('listView') }}
          </button>
          <button type="button" :aria-pressed="viewMode === 'map'" @click="viewMode = 'map'">
            <i class="ti ti-map" aria-hidden="true"></i> {{ t('mapView') }}
          </button>
        </div>
      </div>

      <div class="portal-filters" role="search">
        <select v-model="filters.status_group" :aria-label="t('filterStatus')" @change="applyFilters">
          <option value="">{{ t('allStatuses') }}</option>
          <option value="received">{{ t('statusReceived') }}</option>
          <option value="under_review">{{ t('statusUnderReview') }}</option>
          <option value="resolved">{{ t('statusResolved') }}</option>
          <option value="closed">{{ t('statusClosed') }}</option>
        </select>
        <select v-model="filters.category_id" :aria-label="t('filterCategory')" @change="applyFilters">
          <option value="">{{ t('allCategories') }}</option>
          <option v-for="c in lookups.categories" :key="c.id" :value="String(c.id)">{{ localizedName(c) }}</option>
        </select>
        <select v-model="filters.country" :aria-label="t('filterCountry')" @change="applyFilters">
          <option value="">{{ t('allCountries') }}</option>
          <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
        </select>
        <select v-model="filters.year" :aria-label="t('filterYear')" @change="applyFilters">
          <option value="">{{ t('allYears') }}</option>
          <option v-for="y in lookups.years" :key="y" :value="String(y)">{{ y }}</option>
        </select>
        <select v-model="filters.institution_id" :aria-label="t('filterInstitution')" @change="applyFilters">
          <option value="">{{ t('allInstitutions') }}</option>
          <option v-for="inst in lookups.institutions" :key="inst.id" :value="String(inst.id)">{{ localizedName(inst) }}</option>
        </select>
        <select v-model="filters.activity_sector_id" :aria-label="t('filterSector')" @change="applyFilters">
          <option value="">{{ t('allSectors') }}</option>
          <option v-for="sector in lookups.activity_sectors" :key="sector.id" :value="String(sector.id)">{{ localizedName(sector) }}</option>
        </select>
        <input
          v-model="filters.search"
          type="search"
          :placeholder="t('searchReports')"
          :aria-label="t('searchReports')"
          @input="debouncedApply"
        />
      </div>

      <PublicBarrierMap
        v-if="viewMode === 'map' && mapPoints.length"
        :points="mapPoints"
        :aria-label="t('mapAriaLabel')"
      />
      <div v-else-if="viewMode === 'map'" class="portal-empty portal-map-wrap d-flex align-items-center justify-content-center">
        <p class="mb-0">{{ t('mapEmpty') }}</p>
      </div>

      <div v-if="viewMode === 'list'">
        <div v-if="reportsLoading" class="portal-empty">{{ t('loading') }}</div>
        <div v-else-if="!reports.length" class="portal-empty">
          <i class="ti ti-inbox" aria-hidden="true"></i>
          <p>{{ t('noReportsFound') }}</p>
        </div>
        <div v-else class="portal-report-grid" role="list">
          <article
            v-for="report in reports"
            :key="report.complaint_code"
            class="portal-report-card"
            role="listitem"
          >
            <span :class="statusChipClass(report.status_group)">{{ statusGroupLabel(report.status_group) }}</span>
            <p class="portal-report-card__code">{{ report.complaint_code }}</p>
            <h3 class="portal-report-card__title">{{ report.title }}</h3>
            <p class="portal-report-card__meta">
              {{ localizedName(report.category) }} · {{ report.country_origin }} → {{ report.country_destination }}
            </p>
            <p class="portal-report-card__meta">{{ formatDate(report.created_at) }}</p>
            <RouterLink
              class="portal-btn portal-btn--outline mt-auto align-self-start"
              style="width: fit-content"
              :to="{ name: 'public-track', query: { code: report.complaint_code } }"
            >
              {{ t('viewStatus') }}
            </RouterLink>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'
import PublicBarrierMap from '@/components/public/PublicBarrierMap.vue'

const router = useRouter()
const route = useRoute()
const { t, localizedName, locale } = useI18n()

const stats = ref(null)
const statsLoading = ref(true)
const reports = ref([])
const mapPoints = ref([])
const reportsLoading = ref(true)
const viewMode = ref('list')
const trackCode = ref('')
const lookups = reactive({
  categories: [],
  years: [],
  institutions: [],
  activity_sectors: [],
})
const filters = reactive({
  status_group: '',
  category_id: '',
  country: '',
  year: '',
  institution_id: '',
  activity_sector_id: '',
  search: '',
})

const countries = ['Ethiopia', 'Djibouti']

const filtersActive = computed(() => stats.value?.filters_applied === true)

const statCards = computed(() => {
  const s = stats.value?.summary
  if (!s) return []
  return [
    {
      key: 'total',
      value: s.total_received ?? 0,
      label: t('statTotalReceived'),
      tooltip: t('statTotalReceivedHelp'),
    },
    {
      key: 'resolved',
      value: stats.value?.by_status_group?.resolved ?? 0,
      label: t('statResolved'),
      tooltip: t('statResolvedHelp'),
    },
    {
      key: 'rate',
      value: s.resolution_rate != null ? `${s.resolution_rate}%` : '—',
      label: t('statResolutionRate'),
      tooltip: t('statResolutionRateHelp'),
    },
    {
      key: 'avg',
      value: s.avg_resolution_days != null ? `${s.avg_resolution_days}d` : '—',
      label: t('statAvgResolution'),
      tooltip: t('statAvgResolutionHelp'),
    },
  ]
})

let debounceTimer = null

onMounted(async () => {
  if (route.query.category_id) {
    filters.category_id = String(route.query.category_id)
  }

  const [lookupsRes, transparencyLookupsRes] = await Promise.all([
    http.get('/public/lookups'),
    http.get('/public/transparency/lookups'),
  ])

  lookups.categories = lookupsRes.data.categories || []
  lookups.years = transparencyLookupsRes.data.years || []
  lookups.institutions = transparencyLookupsRes.data.institutions || []
  lookups.activity_sectors = transparencyLookupsRes.data.activity_sectors || []

  await refreshDashboard()
})

watch(() => route.query.category_id, (id) => {
  filters.category_id = id ? String(id) : ''
  applyFilters()
})

function filterParams() {
  const params = {}
  if (filters.status_group) params.status_group = filters.status_group
  if (filters.category_id) params.category_id = filters.category_id
  if (filters.country) params.country = filters.country
  if (filters.year) params.year = filters.year
  if (filters.institution_id) params.institution_id = filters.institution_id
  if (filters.activity_sector_id) params.activity_sector_id = filters.activity_sector_id
  if (filters.search.trim()) params.search = filters.search.trim()
  return params
}

async function refreshDashboard() {
  const params = filterParams()
  statsLoading.value = true
  reportsLoading.value = true

  try {
    const [statsRes, reportsRes, mapRes] = await Promise.all([
      http.get('/public/transparency/stats', { params }),
      http.get('/public/transparency/reports', { params }),
      http.get('/public/transparency/map', { params }),
    ])
    stats.value = statsRes.data
    reports.value = reportsRes.data.reports || []
    mapPoints.value = mapRes.data.points || []
  } finally {
    statsLoading.value = false
    reportsLoading.value = false
  }
}

function applyFilters() {
  refreshDashboard()
}

function debouncedApply() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 350)
}

function goTrack() {
  if (!trackCode.value.trim()) return
  router.push({ name: 'public-track', query: { code: trackCode.value.trim() } })
}

function statusGroupLabel(group) {
  const map = {
    received: t('statusReceived'),
    under_review: t('statusUnderReview'),
    resolved: t('statusResolved'),
    closed: t('statusClosed'),
  }
  return map[group] || group
}

function statusChipClass(group) {
  return `portal-status-chip portal-status-chip--${group || 'received'}`
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium' })
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

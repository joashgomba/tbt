<template>
  <div class="loc-mgmt-enterprise cm-enterprise">
    <section class="loc-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-map-pin"></i></div>
              <div>
                <div class="hero-kicker">{{ t('locHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('locHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('locHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <button type="button" class="btn btn-outline-light btn-sm" @click="openCreateModal">
                <i class="ti ti-plus me-1"></i>{{ t('locAddLocation') }}
              </button>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="loc-stat-grid">
              <div class="loc-stat"><div class="label">{{ t('locTotal') }}</div><div class="value">{{ summary.total ?? 0 }}</div></div>
              <div class="loc-stat"><div class="label">{{ t('locActive') }}</div><div class="value">{{ summary.active ?? 0 }}</div></div>
              <div class="loc-stat"><div class="label">{{ t('corBorderPosts') }}</div><div class="value">{{ summary.border_posts ?? 0 }}</div></div>
              <div class="loc-stat"><div class="label">{{ t('openCases') }}</div><div class="value">{{ summary.open_cases ?? 0 }}</div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="card loc-card mb-3">
      <div class="card-body py-3">
        <ul class="nav loc-tabs flex-wrap gap-2">
          <li v-for="tab in tabs" :key="tab.key">
            <button type="button" class="nav-link" :class="{ active: activeTab === tab.key }" @click="switchTab(tab.key)">
              <i :class="tab.icon" class="me-1"></i>{{ tab.label }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div v-if="message" class="alert alert-success">{{ message }}</div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-if="loading && !bootstrapped" class="card loc-card"><div class="card-body text-center p-5 text-muted">{{ t('loading') }}</div></div>

    <template v-else>
      <!-- Overview -->
      <div v-show="activeTab === 'overview'">
        <div class="row g-4 mb-4">
          <div v-for="tile in overviewTiles" :key="tile.label" class="col-md-3">
            <div class="card loc-card h-100"><div class="card-body">
              <div class="small text-muted">{{ tile.label }}</div>
              <div class="fs-4 fw-bold">{{ tile.value }}</div>
            </div></div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-lg-8">
            <div class="card loc-card">
              <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-0">{{ t('locMapTitle') }}</h6>
                  <p class="small text-muted mb-0">{{ t('locMapSub') }}</p>
                </div>
                <span class="badge bg-label-info">{{ mapPoints.length }} {{ t('locGeocoded') }}</span>
              </div>
              <div class="card-body pt-0">
                <AdminLocationsMap
                  ref="mapRef"
                  :points="mapPoints"
                  :selected-id="selected?.id"
                  :aria-label="t('locMapTitle')"
                  @select="onMapSelect"
                />
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card loc-card h-100">
              <div class="card-header bg-white border-0">
                <h6 class="mb-0">{{ t('locByType') }}</h6>
              </div>
              <div class="card-body">
                <ul class="list-unstyled small mb-0">
                  <li v-for="row in byType" :key="row.location_type" class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-capitalize">{{ formatType(row.location_type) }}</span>
                    <strong>{{ row.count }}</strong>
                  </li>
                  <li v-if="!byType.length" class="text-muted">{{ t('uamNoData') }}</li>
                </ul>
                <h6 class="small fw-semibold mt-4">{{ t('locByCountry') }}</h6>
                <ul class="list-unstyled small mb-0">
                  <li v-for="row in byCountry" :key="row.country" class="d-flex justify-content-between py-1 border-bottom">
                    <span>{{ row.country }}</span><strong>{{ row.count }}</strong>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="card loc-card">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0">{{ t('locLeaderboard') }}</h6>
            <p class="small text-muted mb-0">{{ t('locLeaderboardSub') }}</p>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ t('location') }}</th>
                  <th>{{ t('corridor') }}</th>
                  <th>{{ t('openCases') }}</th>
                  <th>{{ t('catTotalCases') }}</th>
                  <th>{{ t('instResolutionRate') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in leaderboard" :key="row.location?.id || i" class="cursor-pointer" @click="selectFromLeaderboard(row.location)">
                  <td>{{ i + 1 }}</td>
                  <td>
                    <span v-if="row.location?.location_code" class="loc-code-badge me-2">{{ row.location.location_code }}</span>
                    <span class="fw-semibold">{{ localizedName(row.location) }}</span>
                  </td>
                  <td class="small">{{ row.location?.corridor?.corridor_code || '—' }}</td>
                  <td>{{ row.open_cases }}</td>
                  <td>{{ row.total_cases }}</td>
                  <td>{{ row.resolution_rate }}%</td>
                </tr>
                <tr v-if="!leaderboard.length"><td colspan="6" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Registry -->
      <div v-show="activeTab === 'registry'">
        <div class="card loc-card mb-3"><div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">{{ t('search') }}</label>
              <input v-model="filters.search" type="search" class="form-control" @keyup.enter="loadLocations" /></div>
            <div class="col-md-2"><label class="form-label">{{ t('status') }}</label>
              <select v-model="filters.is_active" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option value="true">{{ t('active') }}</option>
                <option value="false">{{ t('inactive') }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('corridor') }}</label>
              <select v-model="filters.corridor_id" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option value="none">{{ t('locNoCorridor') }}</option>
                <option v-for="c in lookups.corridors" :key="c.id" :value="String(c.id)">{{ c.corridor_code }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('locType') }}</label>
              <select v-model="filters.location_type" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="tp in lookups.location_types" :key="tp" :value="tp">{{ formatType(tp) }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('instCountry') }}</label>
              <select v-model="filters.country" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="c in lookups.countries" :key="c" :value="c">{{ c }}</option>
              </select></div>
            <div class="col-md-1"><button type="button" class="btn btn-primary w-100" @click="loadLocations">{{ t('applyFilters') }}</button></div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-3">
              <div class="form-check">
                <input id="f-border" v-model="filters.border_post" class="form-check-input" type="checkbox" true-value="true" false-value="" />
                <label class="form-check-label" for="f-border">{{ t('corBorderPost') }}</label>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-check">
                <input id="f-coords" v-model="filters.has_coordinates" class="form-check-input" type="checkbox" true-value="true" false-value="" />
                <label class="form-check-label" for="f-coords">{{ t('locHasCoordinates') }}</label>
              </div>
            </div>
          </div>
        </div></div>

        <div class="row g-4">
          <div :class="selected ? 'col-lg-7' : 'col-12'">
            <div class="card loc-card">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>{{ t('locCode') }}</th>
                      <th>{{ t('location') }}</th>
                      <th>{{ t('locType') }}</th>
                      <th>{{ t('corridor') }}</th>
                      <th>{{ t('instCountry') }}</th>
                      <th>{{ t('openCases') }}</th>
                      <th>{{ t('status') }}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="loc in locations" :key="loc.id" :class="{ 'table-active': selected?.id === loc.id }">
                      <td><span v-if="loc.location_code" class="loc-code-badge">{{ loc.location_code }}</span><span v-else>—</span></td>
                      <td>
                        <div class="fw-semibold">{{ localizedName(loc) }}</div>
                        <div v-if="loc.region" class="small text-muted">{{ loc.region }}</div>
                      </td>
                      <td><span class="badge bg-label-info loc-type-badge">{{ formatType(loc.location_type) }}</span></td>
                      <td class="small">{{ loc.corridor?.corridor_code || '—' }}</td>
                      <td>{{ loc.country }}</td>
                      <td>{{ loc.open_cases_count ?? 0 }}</td>
                      <td><span class="loc-status" :class="loc.is_active !== false ? 'loc-status--active' : 'loc-status--inactive'">{{ loc.is_active !== false ? t('active') : t('inactive') }}</span></td>
                      <td><button type="button" class="btn btn-sm btn-link" @click="selectLocation(loc)">{{ t('view') }}</button></td>
                    </tr>
                    <tr v-if="!locations.length"><td colspan="8" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-if="selected" class="col-lg-5">
            <div class="card loc-card loc-detail-drawer">
              <div class="card-header bg-white d-flex justify-content-between align-items-start">
                <div>
                  <h6 class="mb-1">{{ localizedName(detail?.location || selected) }}</h6>
                  <span v-if="detail?.location?.location_code || selected.location_code" class="loc-code-badge">{{ detail?.location?.location_code || selected.location_code }}</span>
                </div>
                <button type="button" class="btn-close" @click="closeDetail"></button>
              </div>
              <div class="card-body pt-2">
                <ul class="nav nav-pills flex-wrap gap-1 mb-3">
                  <li v-for="dt in detailTabs" :key="dt.key">
                    <button type="button" class="nav-link py-1 px-2 small" :class="{ active: detailTab === dt.key }" @click="detailTab = dt.key">{{ dt.label }}</button>
                  </li>
                </ul>

                <div v-show="detailTab === 'profile'">
                  <p class="small mb-2">{{ localizedDescription(detail?.location || selected) }}</p>
                  <div class="row g-2 mb-3 small">
                    <div class="col-6"><strong>{{ t('locType') }}:</strong> {{ formatType(detail?.location?.location_type) }}</div>
                    <div class="col-6"><strong>{{ t('instCountry') }}:</strong> {{ detail?.location?.country }}</div>
                    <div class="col-6"><strong>{{ t('corRegion') }}:</strong> {{ detail?.location?.region || '—' }}</div>
                    <div class="col-6"><strong>{{ t('corridor') }}:</strong> {{ detail?.location?.corridor?.corridor_code || '—' }}</div>
                    <div class="col-12"><strong>{{ t('corLatitude') }} / {{ t('corLongitude') }}:</strong>
                      {{ detail?.location?.latitude != null ? `${detail.location.latitude}, ${detail.location.longitude}` : t('locNotGeocoded') }}</div>
                  </div>
                  <div v-if="detail?.impact" class="row g-2 mb-3">
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('openCases') }}</div><strong>{{ detail.impact.open_cases }}</strong></div></div>
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('catTotalCases') }}</div><strong>{{ detail.impact.total_cases }}</strong></div></div>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <button v-if="detail?.location?.is_active === false" type="button" class="btn btn-sm btn-success" @click="lifecycleAction('activate')">{{ t('uamActivate') }}</button>
                    <button v-if="detail?.location?.is_active !== false" type="button" class="btn btn-sm btn-warning" @click="lifecycleModal = true">{{ t('uamDeactivate') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openEditModal">{{ t('edit') }}</button>
                  </div>
                </div>

                <div v-show="detailTab === 'cases'">
                  <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('caseCode') }}</th><th>{{ t('category') }}</th><th>{{ t('corridor') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="c in detail?.recent_cases || []" :key="c.id">
                          <td><code>{{ c.complaint_code }}</code></td>
                          <td>{{ c.category?.code || '—' }}</td>
                          <td class="small">{{ c.corridor?.corridor_code || '—' }}</td>
                        </tr>
                        <tr v-if="!(detail?.recent_cases?.length)"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div v-show="detailTab === 'analytics'">
                  <h6 class="small fw-semibold">{{ t('corCasesByCategory') }}</h6>
                  <ul class="list-unstyled small mb-3">
                    <li v-for="row in detail?.cases_by_category || []" :key="row.code" class="d-flex justify-content-between border-bottom py-1">
                      <span><code>{{ row.code }}</code></span><strong>{{ row.count }}</strong>
                    </li>
                    <li v-if="!(detail?.cases_by_category?.length)" class="text-muted">{{ t('uamNoData') }}</li>
                  </ul>
                  <h6 class="small fw-semibold">{{ t('corCaseTrend') }}</h6>
                  <ul class="list-unstyled small mb-0">
                    <li v-for="row in detail?.case_trend || []" :key="row.month" class="d-flex justify-content-between py-1">
                      <span>{{ row.month }}</span><strong>{{ row.count }}</strong>
                    </li>
                  </ul>
                </div>

                <div v-show="detailTab === 'map'">
                  <AdminLocationsMap
                    v-if="detail?.location?.latitude != null"
                    :points="[{ ...detail.location, label: localizedName(detail.location) }]"
                    :selected-id="detail.location.id"
                    :aria-label="localizedName(detail.location)"
                  />
                  <p v-else class="small text-muted">{{ t('locNotGeocoded') }}</p>
                </div>

                <div v-show="detailTab === 'audit'">
                  <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('date') }}</th><th>{{ t('instAction') }}</th><th>{{ t('user') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="log in auditLogs" :key="log.id">
                          <td class="small">{{ formatDate(log.created_at) }}</td>
                          <td class="small"><code>{{ log.action }}</code></td>
                          <td class="small">{{ log.user?.name || '—' }}</td>
                        </tr>
                        <tr v-if="!auditLogs.length"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
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

    <!-- Form modal -->
    <div v-if="showFormModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ editingId ? t('locEditLocation') : t('locAddLocation') }}</h5>
          <button type="button" class="btn-close" @click="closeFormModal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">{{ t('locCode') }}</label>
              <input v-model="form.location_code" class="form-control text-uppercase" maxlength="50" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('locSortOrder') }}</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('locType') }}</label>
              <select v-model="form.location_type" class="form-select">
                <option v-for="tp in lookups.location_types" :key="tp" :value="tp">{{ formatType(tp) }}</option>
              </select></div>
            <div class="col-md-6"><label class="form-label">{{ t('location') }} *</label>
              <input v-model="form.location_name" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('name') }} (EN)</label>
              <input v-model="form.name_en" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('name') }} (FR)</label>
              <input v-model="form.name_fr" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('instCountry') }} *</label>
              <select v-model="form.country" class="form-select">
                <option v-for="c in lookups.countries" :key="c" :value="c">{{ c }}</option>
              </select></div>
            <div class="col-md-4"><label class="form-label">{{ t('corRegion') }}</label>
              <input v-model="form.region" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('corridor') }}</label>
              <select v-model="form.corridor_id" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="c in lookups.corridors" :key="c.id" :value="String(c.id)">{{ c.corridor_code }} — {{ c.corridor_name }}</option>
              </select></div>
            <div class="col-md-4"><label class="form-label">{{ t('corLatitude') }}</label>
              <input v-model.number="form.latitude" type="number" step="any" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('corLongitude') }}</label>
              <input v-model.number="form.longitude" type="number" step="any" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('locIcon') }}</label>
              <select v-model="form.icon" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="ic in lookups.icons" :key="ic" :value="ic">{{ ic }}</option>
              </select></div>
            <div class="col-12">
              <LocationCoordinatePicker
                ref="coordPickerRef"
                v-model:latitude="form.latitude"
                v-model:longitude="form.longitude"
              />
            </div>
            <div class="col-12"><label class="form-label">{{ t('catDescriptionEn') }}</label>
              <textarea v-model="form.description_en" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">{{ t('catDescriptionFr') }}</label>
              <textarea v-model="form.description_fr" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6"><div class="form-check mt-2">
              <input id="loc-border" v-model="form.border_post" type="checkbox" class="form-check-input" />
              <label class="form-check-label" for="loc-border">{{ t('corBorderPost') }}</label>
            </div></div>
            <div class="col-md-6"><div class="form-check mt-2">
              <input id="loc-active" v-model="form.is_active" type="checkbox" class="form-check-input" />
              <label class="form-check-label" for="loc-active">{{ t('active') }}</label>
            </div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="closeFormModal">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveLocation">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <div v-if="lifecycleModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('uamDeactivate') }}</h5>
          <button type="button" class="btn-close" @click="lifecycleModal = false"></button></div>
        <div class="modal-body">
          <p class="small text-muted">{{ t('locDeactivateHint') }}</p>
          <textarea v-model="lifecycleReason" class="form-control" rows="3" :placeholder="t('notes')"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="lifecycleModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-danger" @click="confirmDeactivate">{{ t('confirm') }}</button>
        </div>
      </div></div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'
import AdminLocationsMap from '@/components/admin/AdminLocationsMap.vue'
import LocationCoordinatePicker from '@/components/admin/LocationCoordinatePicker.vue'

const { t, locale } = useI18n()

const loading = ref(true)
const bootstrapped = ref(false)
const error = ref('')
const message = ref('')
const activeTab = ref('overview')
const detailTab = ref('profile')
const stats = ref({ summary: {}, leaderboard: [], by_type: [], by_country: [] })
const lookups = ref({ corridors: [], countries: [], location_types: [], icons: [] })
const locations = ref([])
const mapPoints = ref([])
const selected = ref(null)
const detail = ref(null)
const auditLogs = ref([])
const mapRef = ref(null)
const coordPickerRef = ref(null)
const filters = ref({ search: '', is_active: '', corridor_id: '', country: '', location_type: '', border_post: '', has_coordinates: '' })
const showFormModal = ref(false)
const editingId = ref(null)
const form = ref(emptyForm())
const lifecycleModal = ref(false)
const lifecycleReason = ref('')

const summary = computed(() => stats.value.summary || {})
const leaderboard = computed(() => stats.value.leaderboard || [])
const byType = computed(() => stats.value.by_type || [])
const byCountry = computed(() => stats.value.by_country || [])

const tabs = computed(() => [
  { key: 'overview', label: t('locTabOverview'), icon: 'ti ti-layout-dashboard' },
  { key: 'registry', label: t('locTabRegistry'), icon: 'ti ti-list-details' },
])

const detailTabs = computed(() => [
  { key: 'profile', label: t('instTabProfile') },
  { key: 'cases', label: t('instTabCases') },
  { key: 'analytics', label: t('corTabAnalytics') },
  { key: 'map', label: t('locTabMap') },
  { key: 'audit', label: t('instTabAudit') },
])

const overviewTiles = computed(() => [
  { label: t('locActive'), value: summary.value.active ?? 0 },
  { label: t('locGeocoded'), value: summary.value.with_coordinates ?? 0 },
  { label: t('corBorderPosts'), value: summary.value.border_posts ?? 0 },
  { label: t('locUnassignedCorridor'), value: summary.value.unassigned_corridor ?? 0 },
])

function emptyForm() {
  return {
    location_code: '', location_name: '', name_en: '', name_fr: '',
    description_en: '', description_fr: '', location_type: 'other', icon: 'ti ti-map-pin',
    sort_order: 0, country: 'Djibouti', region: '', latitude: null, longitude: null,
    border_post: false, is_active: true, corridor_id: '',
  }
}

function formatType(type) {
  if (!type) return '—'
  return type.replace(/_/g, ' ')
}

function localizedName(item) {
  if (!item) return '—'
  return locale.value === 'fr' ? (item.name_fr || item.name_en || item.location_name) : (item.name_en || item.name_fr || item.location_name)
}

function localizedDescription(item) {
  if (!item) return ''
  return locale.value === 'fr' ? (item.description_fr || item.description_en || '') : (item.description_en || item.description_fr || '')
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' })
}

function buildFilterParams() {
  const p = {}
  Object.entries(filters.value).forEach(([k, v]) => { if (v !== '') p[k] = v })
  return p
}

async function switchTab(key) {
  activeTab.value = key
  if (key === 'overview') {
    await nextTick()
    mapRef.value?.refreshMapSize?.()
  }
}

async function refresh() {
  error.value = ''
  message.value = ''
  await loadAll()
}

async function loadMapPoints() {
  try {
    const { data } = await http.get('/location-mgmt/map', { params: { is_active: true } })
    mapPoints.value = (data.locations || []).map(l => ({
      ...l,
      label: localizedName(l),
      open_cases: locations.value.find(x => x.id === l.id)?.open_cases_count,
    }))
  } catch { mapPoints.value = [] }
}

async function loadAll(initial = false) {
  if (initial) loading.value = true
  try {
    const [statsRes, lookupRes] = await Promise.all([
      http.get('/location-mgmt/stats'),
      http.get('/location-mgmt/lookups'),
    ])
    stats.value = statsRes.data
    lookups.value = lookupRes.data
    await loadLocations()
    await loadMapPoints()
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('locLoadError')
  } finally {
    loading.value = false
  }
}

async function loadLocations() {
  const { data } = await http.get('/location-mgmt', { params: buildFilterParams() })
  locations.value = data.data || []
  await loadMapPoints()
}

async function selectLocation(loc) {
  selected.value = loc
  detailTab.value = 'profile'
  activeTab.value = 'registry'
  await loadDetail(loc.id)
}

async function selectFromLeaderboard(loc) {
  if (!loc?.id) return
  activeTab.value = 'registry'
  const found = locations.value.find(x => x.id === loc.id)
  await selectLocation(found || loc)
}

function onMapSelect(point) {
  const found = locations.value.find(l => l.id === point.id)
  if (found) selectLocation(found)
  else selectLocation(point)
}

async function loadDetail(id) {
  try {
    const [detailRes, auditRes] = await Promise.all([
      http.get(`/location-mgmt/${id}`),
      http.get(`/location-mgmt/${id}/audit-logs`),
    ])
    detail.value = detailRes.data
    auditLogs.value = auditRes.data.logs || []
  } catch (e) {
    error.value = e?.response?.data?.message || t('locLoadError')
  }
}

function closeDetail() {
  selected.value = null
  detail.value = null
}

function openCreateModal() {
  editingId.value = null
  form.value = emptyForm()
  showFormModal.value = true
  refreshCoordPicker()
}

function openEditModal() {
  const loc = detail.value?.location
  if (!loc) return
  editingId.value = loc.id
  form.value = {
    location_code: loc.location_code || '',
    location_name: loc.location_name || '',
    name_en: loc.name_en || '',
    name_fr: loc.name_fr || '',
    description_en: loc.description_en || '',
    description_fr: loc.description_fr || '',
    location_type: loc.location_type || 'other',
    icon: loc.icon || '',
    sort_order: loc.sort_order ?? 0,
    country: loc.country || 'Djibouti',
    region: loc.region || '',
    latitude: loc.latitude ?? null,
    longitude: loc.longitude ?? null,
    border_post: loc.border_post ?? false,
    is_active: loc.is_active !== false,
    corridor_id: loc.corridor_id ? String(loc.corridor_id) : '',
  }
  showFormModal.value = true
  refreshCoordPicker()
}

async function refreshCoordPicker() {
  await nextTick()
  coordPickerRef.value?.refreshMapSize?.()
}

function closeFormModal() {
  showFormModal.value = false
  editingId.value = null
}

function payloadFromForm() {
  const p = { ...form.value }
  p.corridor_id = p.corridor_id || null
  p.location_code = p.location_code || null
  if (!p.latitude && p.latitude !== 0) p.latitude = null
  if (!p.longitude && p.longitude !== 0) p.longitude = null
  return p
}

async function saveLocation() {
  const savedId = editingId.value
  try {
    if (savedId) {
      await http.put(`/location-mgmt/${savedId}`, payloadFromForm())
    } else {
      const { data } = await http.post('/location-mgmt', payloadFromForm())
      selected.value = data.location
    }
    message.value = t('cmActionSuccess')
    closeFormModal()
    await loadAll()
    const id = savedId || selected.value?.id
    if (id) await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function lifecycleAction(action) {
  const id = selected.value?.id
  if (!id) return
  try {
    if (action === 'activate') await http.post(`/location-mgmt/${id}/activate`)
    message.value = t('cmActionSuccess')
    await loadAll()
    await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function confirmDeactivate() {
  const id = selected.value?.id
  if (!id) return
  try {
    await http.post(`/location-mgmt/${id}/deactivate`, { reason: lifecycleReason.value || null })
    lifecycleModal.value = false
    lifecycleReason.value = ''
    message.value = t('cmActionSuccess')
    await loadAll()
    await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

onMounted(() => loadAll(true))
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>

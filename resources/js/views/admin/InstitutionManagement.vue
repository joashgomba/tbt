<template>
  <div class="inst-mgmt-enterprise cm-enterprise">
    <section class="inst-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-building-community"></i></div>
              <div>
                <div class="hero-kicker">{{ t('instHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('instHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('instHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <button v-if="canManage" type="button" class="btn btn-outline-light btn-sm" @click="openCreateModal">
                <i class="ti ti-building-plus me-1"></i>{{ t('instAddInstitution') }}
              </button>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="inst-stat-grid">
              <div class="inst-stat"><div class="label">{{ t('instTotal') }}</div><div class="value">{{ summary.total ?? 0 }}</div></div>
              <div class="inst-stat"><div class="label">{{ t('instActive') }}</div><div class="value">{{ summary.active ?? 0 }}</div></div>
              <div class="inst-stat"><div class="label">{{ t('instSuspended') }}</div><div class="value">{{ summary.suspended ?? 0 }}</div></div>
              <div class="inst-stat"><div class="label">{{ t('openCases') }}</div><div class="value">{{ summary.open_cases ?? 0 }}</div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="card inst-card mb-3">
      <div class="card-body py-3">
        <ul class="nav inst-tabs flex-wrap gap-2">
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

    <div v-if="loading && !bootstrapped" class="card inst-card"><div class="card-body text-center p-5 text-muted">{{ t('loading') }}</div></div>

    <template v-else>
      <!-- Overview -->
      <div v-show="activeTab === 'overview'">
        <div class="row g-4 mb-4">
          <div v-for="tile in overviewTiles" :key="tile.label" class="col-md-3">
            <div class="card inst-card h-100"><div class="card-body">
              <div class="small text-muted">{{ tile.label }}</div>
              <div class="fs-4 fw-bold">{{ tile.value }}</div>
            </div></div>
          </div>
        </div>
        <div class="card inst-card">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0">{{ t('instLeaderboard') }}</h6>
            <p class="small text-muted mb-0">{{ t('instLeaderboardSub') }}</p>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ t('institution') }}</th>
                  <th>{{ t('uamActiveUsers') }}</th>
                  <th>{{ t('uamFocalPoints') }}</th>
                  <th>{{ t('openCases') }}</th>
                  <th>{{ t('instSlaCompliance') }}</th>
                  <th>{{ t('instResolutionRate') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in leaderboard" :key="row.institution?.id || i" class="cursor-pointer" @click="selectFromLeaderboard(row.institution)">
                  <td>{{ i + 1 }}</td>
                  <td class="fw-semibold">{{ row.institution?.institution_name || '—' }}</td>
                  <td>{{ row.active_users }}</td>
                  <td>{{ row.focal_points }}</td>
                  <td>{{ row.open_cases }}</td>
                  <td>{{ row.sla_pct }}%</td>
                  <td>{{ row.resolution_rate }}%</td>
                </tr>
                <tr v-if="!leaderboard.length"><td colspan="7" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Directory -->
      <div v-show="activeTab === 'directory'">
        <div class="card inst-card mb-3"><div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">{{ t('search') }}</label>
              <input v-model="filters.search" type="search" class="form-control" @keyup.enter="loadInstitutions" /></div>
            <div class="col-md-2"><label class="form-label">{{ t('status') }}</label>
              <select v-model="filters.status" class="form-select"><option value="">{{ t('all') }}</option>
                <option v-for="s in lookups.statuses" :key="s" :value="s">{{ s }}</option></select></div>
            <div class="col-md-2"><label class="form-label">{{ t('instType') }}</label>
              <select v-model="filters.institution_type" class="form-select"><option value="">{{ t('all') }}</option>
                <option v-for="tp in lookups.types" :key="tp" :value="tp">{{ tp }}</option></select></div>
            <div class="col-md-2"><label class="form-label">{{ t('instCountry') }}</label>
              <select v-model="filters.country_code" class="form-select"><option value="">{{ t('all') }}</option>
                <option v-for="c in lookups.countries" :key="c" :value="c">{{ c }}</option></select></div>
            <div class="col-md-2"><label class="form-label">{{ t('corridor') }}</label>
              <select v-model="filters.corridor_id" class="form-select"><option value="">{{ t('all') }}</option>
                <option v-for="c in lookups.corridors" :key="c.id" :value="String(c.id)">{{ c.corridor_code }}</option></select></div>
            <div class="col-md-1"><button type="button" class="btn btn-primary w-100" @click="loadInstitutions">{{ t('applyFilters') }}</button></div>
          </div>
        </div></div>

        <div class="row g-4">
          <div :class="selected ? 'col-lg-7' : 'col-12'">
            <div class="card inst-card">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>{{ t('instCode') }}</th>
                      <th>{{ t('institution') }}</th>
                      <th>{{ t('instType') }}</th>
                      <th>{{ t('instCountry') }}</th>
                      <th>{{ t('status') }}</th>
                      <th>{{ t('uamUsers') }}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="inst in institutions" :key="inst.id" :class="{ 'table-active': selected?.id === inst.id }">
                      <td><code class="small">{{ inst.institution_code }}</code></td>
                      <td class="fw-semibold">{{ inst.institution_name }}</td>
                      <td><span class="badge bg-label-secondary inst-type-badge">{{ inst.institution_type }}</span></td>
                      <td>{{ inst.country_code }}</td>
                      <td><span class="inst-status" :class="statusClass(inst.status)">{{ inst.status }}</span></td>
                      <td>{{ inst.active_users_count ?? 0 }}</td>
                      <td><button type="button" class="btn btn-sm btn-link" @click="selectInstitution(inst)">{{ t('view') }}</button></td>
                    </tr>
                    <tr v-if="!institutions.length"><td colspan="7" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-if="selected" class="col-lg-5">
            <div class="card inst-card inst-detail-drawer">
              <div class="card-header bg-white d-flex justify-content-between align-items-start">
                <div>
                  <h6 class="mb-1">{{ detail?.institution?.institution_name || selected.institution_name }}</h6>
                  <code class="small">{{ detail?.institution?.institution_code || selected.institution_code }}</code>
                </div>
                <button type="button" class="btn-close" @click="closeDetail"></button>
              </div>
              <div class="card-body pt-2">
                <ul class="nav nav-pills flex-wrap gap-1 mb-3">
                  <li v-for="dt in detailTabs" :key="dt.key">
                    <button type="button" class="nav-link py-1 px-2 small" :class="{ active: detailTab === dt.key }" @click="detailTab = dt.key">{{ dt.label }}</button>
                  </li>
                </ul>

                <!-- Profile -->
                <div v-show="detailTab === 'profile'">
                  <p class="small mb-1"><strong>{{ t('status') }}:</strong> <span class="inst-status" :class="statusClass(detail?.institution?.status)">{{ detail?.institution?.status }}</span></p>
                  <p class="small mb-1"><strong>{{ t('instType') }}:</strong> {{ detail?.institution?.institution_type }}</p>
                  <p class="small mb-1"><strong>{{ t('instCountry') }}:</strong> {{ detail?.institution?.country_code }}</p>
                  <p v-if="detail?.institution?.contact_email" class="small mb-1"><strong>{{ t('email') }}:</strong> {{ detail.institution.contact_email }}</p>
                  <p v-if="detail?.institution?.contact_phone" class="small mb-1"><strong>{{ t('phone') }}:</strong> {{ detail.institution.contact_phone }}</p>
                  <p v-if="detail?.institution?.mandate" class="small mb-2"><strong>{{ t('instMandate') }}:</strong> {{ detail.institution.mandate }}</p>
                  <div v-if="detail?.impact" class="row g-2 mb-3">
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('openCases') }}</div><strong>{{ detail.impact.open_cases }}</strong></div></div>
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('uamActiveUsers') }}</div><strong>{{ detail.impact.active_users }}</strong></div></div>
                  </div>
                  <div v-if="canManage" class="d-flex flex-wrap gap-2">
                    <button v-if="detail?.institution?.status !== 'active'" type="button" class="btn btn-sm btn-success" @click="lifecycleAction('activate')">{{ t('uamActivate') }}</button>
                    <button v-if="detail?.institution?.status === 'active'" type="button" class="btn btn-sm btn-warning" @click="promptLifecycle('suspend')">{{ t('uamSuspend') }}</button>
                    <button v-if="detail?.institution?.status !== 'inactive'" type="button" class="btn btn-sm btn-outline-danger" @click="promptLifecycle('deactivate')">{{ t('uamDeactivate') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openEditModal">{{ t('edit') }}</button>
                  </div>
                  <div v-else-if="canModify" class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openEditModal">{{ t('edit') }}</button>
                  </div>
                </div>

                <!-- Users -->
                <div v-show="detailTab === 'users'">
                  <div v-if="detail?.administrators?.length" class="mb-3">
                    <h6 class="small fw-semibold">{{ t('instAdministrators') }}</h6>
                    <ul class="list-unstyled small mb-0">
                      <li v-for="u in detail.administrators" :key="u.id">{{ u.full_name || u.name }} · {{ u.email }}</li>
                    </ul>
                  </div>
                  <div v-if="detail?.focal_points?.length" class="mb-3">
                    <h6 class="small fw-semibold">{{ t('uamFocalPoints') }}</h6>
                    <ul class="list-unstyled small mb-0">
                      <li v-for="u in detail.focal_points" :key="u.id">{{ u.full_name || u.name }} · {{ u.email }}</li>
                    </ul>
                  </div>
                  <div class="table-responsive" style="max-height:280px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('name') }}</th><th>{{ t('uamRole') }}</th><th>{{ t('status') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="u in institutionUsers" :key="u.id">
                          <td>{{ u.full_name || u.name }}</td>
                          <td><span class="badge bg-label-primary">{{ u.roles?.[0]?.name || '—' }}</span></td>
                          <td>{{ u.status }}</td>
                        </tr>
                        <tr v-if="!institutionUsers.length"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Units -->
                <div v-show="detailTab === 'units'">
                  <div class="d-flex justify-content-between mb-2">
                    <h6 class="small fw-semibold mb-0">{{ t('instUnits') }}</h6>
                    <button v-if="canModify || canManage" type="button" class="btn btn-sm btn-link" @click="showUnitModal = true">{{ t('instAddUnit') }}</button>
                  </div>
                  <div class="table-responsive" style="max-height:300px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('instCode') }}</th><th>{{ t('name') }}</th><th>{{ t('status') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="u in institutionUnits" :key="u.id">
                          <td><code>{{ u.unit_code }}</code></td>
                          <td>{{ locale === 'fr' ? u.name_fr : u.name_en }}</td>
                          <td>{{ u.is_active ? t('active') : t('inactive') }}</td>
                        </tr>
                        <tr v-if="!institutionUnits.length"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Cases -->
                <div v-show="detailTab === 'cases'">
                  <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('caseCode') }}</th><th>{{ t('title') }}</th><th>{{ t('status') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="c in detail?.open_cases || []" :key="c.id">
                          <td><code>{{ c.complaint_code }}</code></td>
                          <td>{{ c.title }}</td>
                          <td>{{ c.status?.name_en || c.status?.code || '—' }}</td>
                        </tr>
                        <tr v-if="!(detail?.open_cases?.length)"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Configuration -->
                <div v-show="detailTab === 'config'">
                  <p class="small mb-2"><strong>{{ t('instCorridors') }}:</strong>
                    {{ (detail?.institution?.corridors || []).map(c => c.corridor_code).join(', ') || '—' }}</p>
                  <p class="small mb-2"><strong>{{ t('instCategories') }}:</strong>
                    {{ (detail?.institution?.ntb_categories || detail?.institution?.ntbCategories || []).map(c => c.code).join(', ') || '—' }}</p>
                  <p v-if="detail?.institution?.integration_code" class="small mb-2"><strong>{{ t('instIntegrationCode') }}:</strong> {{ detail.institution.integration_code }}</p>
                  <p v-if="detail?.institution?.parent" class="small mb-0"><strong>{{ t('instParent') }}:</strong> {{ detail.institution.parent.institution_name }}</p>
                </div>

                <!-- Audit -->
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

    <!-- Create / Edit institution -->
    <div v-if="showFormModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ editingId ? t('instEditInstitution') : t('instAddInstitution') }}</h5>
          <button type="button" class="btn-close" @click="closeFormModal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">{{ t('institution') }} *</label>
              <input v-model="form.institution_name" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('instCode') }} *</label>
              <input v-model="form.institution_code" class="form-control" :disabled="!!editingId && !canManage" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('instType') }} *</label>
              <select v-model="form.institution_type" class="form-select">
                <option v-for="tp in lookups.types" :key="tp" :value="tp">{{ tp }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t('instCountry') }} *</label>
              <select v-model="form.country_code" class="form-select">
                <option v-for="c in lookups.countries" :key="c" :value="c">{{ c }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t('instParent') }}</label>
              <select v-model="form.parent_id" class="form-select"><option value="">{{ t('none') }}</option>
                <option v-for="p in lookups.parents" :key="p.id" :value="String(p.id)">{{ p.institution_name }}</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('email') }}</label>
              <input v-model="form.contact_email" type="email" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('phone') }}</label>
              <input v-model="form.contact_phone" class="form-control" /></div>
            <div class="col-12"><label class="form-label">{{ t('address') }}</label>
              <textarea v-model="form.address" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">{{ t('instMandate') }}</label>
              <textarea v-model="form.mandate" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label">{{ t('instCorridors') }}</label>
              <select v-model="form.corridor_ids" class="form-select" multiple size="4">
                <option v-for="c in lookups.corridors" :key="c.id" :value="String(c.id)">{{ c.corridor_code }} — {{ c.name_en }}</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('instCategories') }}</label>
              <select v-model="form.category_ids" class="form-select" multiple size="4">
                <option v-for="c in lookups.categories" :key="c.id" :value="String(c.id)">{{ c.code }} — {{ c.name_en }}</option></select></div>
            <div v-if="canManage" class="col-md-6"><label class="form-label">{{ t('instIntegrationCode') }}</label>
              <input v-model="form.integration_code" class="form-control" /></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="closeFormModal">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveInstitution">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Add unit -->
    <div v-if="showUnitModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('instAddUnit') }}</h5>
          <button type="button" class="btn-close" @click="showUnitModal = false"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">{{ t('instCode') }}</label><input v-model="unitForm.unit_code" class="form-control" /></div>
          <div class="mb-3"><label class="form-label">{{ t('name') }} (EN)</label><input v-model="unitForm.name_en" class="form-control" /></div>
          <div class="mb-3"><label class="form-label">{{ t('name') }} (FR)</label><input v-model="unitForm.name_fr" class="form-control" /></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showUnitModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="createUnit">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Lifecycle reason -->
    <div v-if="lifecycleModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ lifecycleModal === 'suspend' ? t('uamSuspend') : t('uamDeactivate') }}</h5>
          <button type="button" class="btn-close" @click="lifecycleModal = null"></button></div>
        <div class="modal-body">
          <label class="form-label">{{ t('uamReasonRequired') }}</label>
          <textarea v-model="lifecycleReason" class="form-control" rows="3"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="lifecycleModal = null">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-danger" @click="confirmLifecycle">{{ t('confirm') }}</button>
        </div>
      </div></div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const { t, locale } = useI18n()

const loading = ref(true)
const bootstrapped = ref(false)
const error = ref('')
const message = ref('')
const activeTab = ref('overview')
const detailTab = ref('profile')
const stats = ref({ summary: {}, leaderboard: [] })
const lookups = ref({ types: [], statuses: [], countries: [], corridors: [], categories: [], parents: [] })
const institutions = ref([])
const selected = ref(null)
const detail = ref(null)
const institutionUsers = ref([])
const institutionUnits = ref([])
const auditLogs = ref([])
const me = ref(null)

const filters = ref({ search: '', status: '', institution_type: '', country_code: '', corridor_id: '' })
const showFormModal = ref(false)
const editingId = ref(null)
const form = ref(emptyForm())
const showUnitModal = ref(false)
const unitForm = ref({ unit_code: '', name_en: '', name_fr: '' })
const lifecycleModal = ref(null)
const lifecycleReason = ref('')

const canManage = computed(() => me.value?.permissions?.includes('admin.institutions') ?? false)
const canModify = computed(() => {
  if (canManage.value) return true
  if (!me.value?.institution_id || !selected.value) return false
  return me.value.institution_id === selected.value.id && detail.value?.institution?.status === 'active'
})
const summary = computed(() => stats.value.summary || {})
const leaderboard = computed(() => stats.value.leaderboard || [])

const tabs = computed(() => [
  { key: 'overview', label: t('instTabOverview'), icon: 'ti ti-layout-dashboard' },
  { key: 'directory', label: t('instTabDirectory'), icon: 'ti ti-list-details' },
])

const detailTabs = computed(() => [
  { key: 'profile', label: t('instTabProfile') },
  { key: 'users', label: t('uamTabUsers') },
  { key: 'units', label: t('instUnits') },
  { key: 'cases', label: t('instTabCases') },
  { key: 'config', label: t('instTabConfig') },
  { key: 'audit', label: t('instTabAudit') },
])

const overviewTiles = computed(() => [
  { label: t('instActive'), value: summary.value.active ?? 0 },
  { label: t('instSuspended'), value: summary.value.suspended ?? 0 },
  { label: t('instInactive'), value: summary.value.inactive ?? 0 },
  { label: t('uamTotalUsers'), value: summary.value.total_users ?? 0 },
])

function emptyForm() {
  return {
    institution_name: '', institution_code: '', institution_type: 'lead_ministry', country_code: 'DJ',
    parent_id: '', contact_email: '', contact_phone: '', address: '', mandate: '',
    integration_code: '', corridor_ids: [], category_ids: [],
  }
}

function statusClass(status) {
  if (status === 'active') return 'inst-status--active'
  if (status === 'suspended') return 'inst-status--suspended'
  return 'inst-status--inactive'
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' })
}

function buildFilterParams() {
  const p = {}
  Object.entries(filters.value).forEach(([k, v]) => { if (v) p[k] = v })
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
    const meRes = await http.get('/auth/me')
    me.value = meRes.data.user || meRes.data
    const [statsRes, lookupRes] = await Promise.all([
      http.get('/institution-mgmt/stats'),
      http.get('/institution-mgmt/lookups'),
    ])
    stats.value = statsRes.data
    lookups.value = lookupRes.data
    await loadInstitutions()
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('instLoadError')
  } finally {
    loading.value = false
  }
}

async function loadInstitutions() {
  const { data } = await http.get('/institution-mgmt', { params: buildFilterParams() })
  institutions.value = data.data || []
}

async function selectInstitution(inst) {
  selected.value = inst
  detailTab.value = 'profile'
  await loadDetail(inst.id)
}

async function selectFromLeaderboard(inst) {
  if (!inst?.id) return
  activeTab.value = 'directory'
  const found = institutions.value.find(i => i.id === inst.id)
  await selectInstitution(found || inst)
}

async function loadDetail(id) {
  try {
    const [detailRes, usersRes, unitsRes, auditRes] = await Promise.all([
      http.get(`/institution-mgmt/${id}`),
      http.get(`/institution-mgmt/${id}/users`),
      http.get(`/institution-mgmt/${id}/units`),
      http.get(`/institution-mgmt/${id}/audit-logs`),
    ])
    detail.value = detailRes.data
    institutionUsers.value = usersRes.data.users || []
    institutionUnits.value = unitsRes.data.units || []
    auditLogs.value = auditRes.data.logs || []
  } catch (e) {
    error.value = e?.response?.data?.message || t('instLoadError')
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
}

function openEditModal() {
  const inst = detail.value?.institution
  if (!inst) return
  editingId.value = inst.id
  form.value = {
    institution_name: inst.institution_name || '',
    institution_code: inst.institution_code || '',
    institution_type: inst.institution_type || 'lead_ministry',
    country_code: inst.country_code || 'DJ',
    parent_id: inst.parent_id ? String(inst.parent_id) : '',
    contact_email: inst.contact_email || '',
    contact_phone: inst.contact_phone || '',
    address: inst.address || '',
    mandate: inst.mandate || '',
    integration_code: inst.integration_code || '',
    corridor_ids: (inst.corridors || []).map(c => String(c.id)),
    category_ids: (inst.ntb_categories || inst.ntbCategories || []).map(c => String(c.id)),
  }
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  editingId.value = null
}

function payloadFromForm() {
  const p = { ...form.value }
  p.parent_id = p.parent_id || null
  p.corridor_ids = (p.corridor_ids || []).map(Number)
  p.category_ids = (p.category_ids || []).map(Number)
  return p
}

async function saveInstitution() {
  try {
    if (editingId.value) {
      await http.put(`/institution-mgmt/${editingId.value}`, payloadFromForm())
    } else {
      await http.post('/institution-mgmt', payloadFromForm())
    }
    message.value = t('cmActionSuccess')
    closeFormModal()
    await loadAll()
    if (editingId.value || selected.value) {
      const id = editingId.value || selected.value?.id
      if (id) await loadDetail(id)
    }
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

function promptLifecycle(action) {
  lifecycleModal.value = action
  lifecycleReason.value = ''
}

async function confirmLifecycle() {
  if (!lifecycleReason.value.trim()) {
    error.value = t('uamReasonRequired')
    return
  }
  await lifecycleAction(lifecycleModal.value, lifecycleReason.value)
  lifecycleModal.value = null
}

async function lifecycleAction(action, reason = null) {
  const id = selected.value?.id
  if (!id) return
  try {
    if (action === 'activate') {
      await http.post(`/institution-mgmt/${id}/activate`, { reason })
    } else if (action === 'suspend') {
      await http.post(`/institution-mgmt/${id}/suspend`, { reason })
    } else if (action === 'deactivate') {
      await http.post(`/institution-mgmt/${id}/deactivate`, { reason })
    }
    message.value = t('cmActionSuccess')
    await loadAll()
    await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || e?.response?.data?.error || t('cmActionFailed')
  }
}

async function createUnit() {
  const id = selected.value?.id
  if (!id) return
  try {
    await http.post(`/institution-mgmt/${id}/units`, unitForm.value)
    showUnitModal.value = false
    unitForm.value = { unit_code: '', name_en: '', name_fr: '' }
    message.value = t('cmActionSuccess')
    await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

watch(detailTab, async (tab) => {
  if (!selected.value) return
  if (tab === 'users' && !institutionUsers.value.length) {
    const { data } = await http.get(`/institution-mgmt/${selected.value.id}/users`)
    institutionUsers.value = data.users || []
  }
})

onMounted(() => loadAll(true))
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>

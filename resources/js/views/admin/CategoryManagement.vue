<template>
  <div class="cat-mgmt-enterprise cm-enterprise">
    <section class="cat-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-category"></i></div>
              <div>
                <div class="hero-kicker">{{ t('catHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('catHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('catHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <button type="button" class="btn btn-outline-light btn-sm" @click="openCreateModal">
                <i class="ti ti-plus me-1"></i>{{ t('catAddCategory') }}
              </button>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="cat-stat-grid">
              <div class="cat-stat"><div class="label">{{ t('catTotal') }}</div><div class="value">{{ summary.total ?? 0 }}</div></div>
              <div class="cat-stat"><div class="label">{{ t('catActive') }}</div><div class="value">{{ summary.active ?? 0 }}</div></div>
              <div class="cat-stat"><div class="label">{{ t('catInactive') }}</div><div class="value">{{ summary.inactive ?? 0 }}</div></div>
              <div class="cat-stat"><div class="label">{{ t('openCases') }}</div><div class="value">{{ summary.open_cases ?? 0 }}</div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="card cat-card mb-3">
      <div class="card-body py-3">
        <ul class="nav cat-tabs flex-wrap gap-2">
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

    <div v-if="loading && !bootstrapped" class="card cat-card"><div class="card-body text-center p-5 text-muted">{{ t('loading') }}</div></div>

    <template v-else>
      <!-- Overview -->
      <div v-show="activeTab === 'overview'">
        <div class="row g-4 mb-4">
          <div v-for="tile in overviewTiles" :key="tile.label" class="col-md-3">
            <div class="card cat-card h-100"><div class="card-body">
              <div class="small text-muted">{{ tile.label }}</div>
              <div class="fs-4 fw-bold">{{ tile.value }}</div>
            </div></div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-7">
            <div class="card cat-card">
              <div class="card-header bg-white border-0">
                <h6 class="mb-0">{{ t('catLeaderboard') }}</h6>
                <p class="small text-muted mb-0">{{ t('catLeaderboardSub') }}</p>
              </div>
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>{{ t('category') }}</th>
                      <th>{{ t('openCases') }}</th>
                      <th>{{ t('catTotalCases') }}</th>
                      <th>{{ t('instResolutionRate') }}</th>
                      <th>{{ t('catDefaultSla') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, i) in leaderboard" :key="row.category?.id || i" class="cursor-pointer" @click="selectFromLeaderboard(row.category)">
                      <td>{{ i + 1 }}</td>
                      <td>
                        <span class="cat-code-badge me-2">{{ row.category?.code }}</span>
                        <span class="fw-semibold">{{ localizedName(row.category) }}</span>
                      </td>
                      <td>{{ row.open_cases }}</td>
                      <td>{{ row.total_cases }}</td>
                      <td>{{ row.resolution_rate }}%</td>
                      <td>{{ row.avg_sla_days }}d</td>
                    </tr>
                    <tr v-if="!leaderboard.length"><td colspan="6" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card cat-card h-100">
              <div class="card-header bg-white border-0">
                <h6 class="mb-0">{{ t('catHierarchy') }}</h6>
                <p class="small text-muted mb-0">{{ t('catHierarchySub') }}</p>
              </div>
              <div class="card-body">
                <div v-for="root in hierarchyRoots" :key="root.id" class="mb-3">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="cat-icon-preview"><i :class="root.icon || 'ti ti-category'"></i></span>
                    <span class="cat-code-badge">{{ root.code }}</span>
                    <button type="button" class="btn btn-link btn-sm p-0" @click="selectCategory(root)">{{ localizedName(root) }}</button>
                  </div>
                  <div v-for="child in childrenOf(root.id)" :key="child.id" class="cat-hierarchy-item small text-muted">
                    <span class="cat-code-badge me-1">{{ child.code }}</span>
                    <button type="button" class="btn btn-link btn-sm p-0" @click="selectCategory(child)">{{ localizedName(child) }}</button>
                  </div>
                </div>
                <p v-if="!hierarchyRoots.length" class="text-muted small mb-0">{{ t('uamNoData') }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Catalogue -->
      <div v-show="activeTab === 'catalogue'">
        <div class="card cat-card mb-3"><div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">{{ t('search') }}</label>
              <input v-model="filters.search" type="search" class="form-control" @keyup.enter="loadCategories" /></div>
            <div class="col-md-2"><label class="form-label">{{ t('status') }}</label>
              <select v-model="filters.is_active" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option value="true">{{ t('active') }}</option>
                <option value="false">{{ t('inactive') }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('catParent') }}</label>
              <select v-model="filters.parent_id" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option value="root">{{ t('catRootOnly') }}</option>
                <option v-for="p in lookups.parents" :key="p.id" :value="String(p.id)">{{ p.code }} — {{ localizedName(p) }}</option>
              </select></div>
            <div class="col-md-3"><label class="form-label">{{ t('catResponsibleInstitution') }}</label>
              <select v-model="filters.institution_id" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="i in lookups.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
              </select></div>
            <div class="col-md-2"><button type="button" class="btn btn-primary w-100" @click="loadCategories">{{ t('applyFilters') }}</button></div>
          </div>
        </div></div>

        <div class="row g-4">
          <div :class="selected ? 'col-lg-7' : 'col-12'">
            <div class="card cat-card">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th></th>
                      <th>{{ t('catCode') }}</th>
                      <th>{{ t('category') }}</th>
                      <th>{{ t('catDefaultSla') }}</th>
                      <th>{{ t('catResponsibleInstitution') }}</th>
                      <th>{{ t('openCases') }}</th>
                      <th>{{ t('status') }}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="cat in categories" :key="cat.id" :class="{ 'table-active': selected?.id === cat.id }">
                      <td><span class="cat-icon-preview"><i :class="cat.icon || 'ti ti-category'"></i></span></td>
                      <td><span class="cat-code-badge">{{ cat.code }}</span></td>
                      <td>
                        <div class="fw-semibold">{{ localizedName(cat) }}</div>
                        <div v-if="cat.parent" class="small text-muted">{{ t('catParent') }}: {{ cat.parent.code }}</div>
                      </td>
                      <td>{{ cat.default_sla_days }}d</td>
                      <td class="small">{{ cat.responsible_institution?.institution_name || '—' }}</td>
                      <td>{{ cat.open_cases_count ?? 0 }}</td>
                      <td><span class="cat-status" :class="cat.is_active ? 'cat-status--active' : 'cat-status--inactive'">{{ cat.is_active ? t('active') : t('inactive') }}</span></td>
                      <td><button type="button" class="btn btn-sm btn-link" @click="selectCategory(cat)">{{ t('view') }}</button></td>
                    </tr>
                    <tr v-if="!categories.length"><td colspan="8" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-if="selected" class="col-lg-5">
            <div class="card cat-card cat-detail-drawer">
              <div class="card-header bg-white d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-start gap-2">
                  <span class="cat-icon-preview"><i :class="detail?.category?.icon || selected.icon || 'ti ti-category'"></i></span>
                  <div>
                    <h6 class="mb-1">{{ localizedName(detail?.category || selected) }}</h6>
                    <span class="cat-code-badge">{{ detail?.category?.code || selected.code }}</span>
                  </div>
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
                  <p class="small mb-2">{{ localizedDescription(detail?.category || selected) }}</p>
                  <p class="small mb-1"><strong>{{ t('status') }}:</strong>
                    <span class="cat-status" :class="(detail?.category?.is_active ?? selected.is_active) ? 'cat-status--active' : 'cat-status--inactive'">
                      {{ (detail?.category?.is_active ?? selected.is_active) ? t('active') : t('inactive') }}
                    </span></p>
                  <p class="small mb-1"><strong>{{ t('catDefaultSla') }}:</strong> {{ detail?.category?.default_sla_days ?? selected.default_sla_days }} {{ t('catDays') }}</p>
                  <p class="small mb-1"><strong>{{ t('catResponsibleInstitution') }}:</strong> {{ detail?.category?.responsible_institution?.institution_name || '—' }}</p>
                  <p v-if="detail?.category?.name_ar" class="small mb-2"><strong>{{ t('catNameAr') }}:</strong> {{ detail.category.name_ar }}</p>
                  <div v-if="detail?.impact" class="row g-2 mb-3">
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('openCases') }}</div><strong>{{ detail.impact.open_cases }}</strong></div></div>
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('catTotalCases') }}</div><strong>{{ detail.impact.total_cases }}</strong></div></div>
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('catLinkedInstitutions') }}</div><strong>{{ detail.impact.linked_institutions }}</strong></div></div>
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('catSlaRules') }}</div><strong>{{ detail.impact.sla_configurations }}</strong></div></div>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <button v-if="!(detail?.category?.is_active ?? selected.is_active)" type="button" class="btn btn-sm btn-success" @click="lifecycleAction('activate')">{{ t('uamActivate') }}</button>
                    <button v-if="detail?.category?.is_active ?? selected.is_active" type="button" class="btn btn-sm btn-warning" @click="lifecycleModal = 'deactivate'">{{ t('uamDeactivate') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openEditModal">{{ t('edit') }}</button>
                  </div>
                </div>

                <!-- Cases -->
                <div v-show="detailTab === 'cases'">
                  <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('caseCode') }}</th><th>{{ t('title') }}</th><th>{{ t('institution') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="c in detail?.recent_cases || []" :key="c.id">
                          <td><code>{{ c.complaint_code }}</code></td>
                          <td>{{ c.title }}</td>
                          <td class="small">{{ c.assigned_institution?.institution_name || '—' }}</td>
                        </tr>
                        <tr v-if="!(detail?.recent_cases?.length)"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Institutions -->
                <div v-show="detailTab === 'institutions'">
                  <ul class="list-unstyled small mb-0">
                    <li v-for="inst in detail?.category?.institutions || []" :key="inst.id" class="mb-2">
                      <code>{{ inst.institution_code }}</code> — {{ inst.institution_name }}
                    </li>
                    <li v-if="!(detail?.category?.institutions?.length)" class="text-muted">{{ t('uamNoData') }}</li>
                  </ul>
                  <p v-if="detail?.category?.responsible_institution" class="small mt-3 mb-0 text-muted">
                    {{ t('catPrimaryResponsible') }}: {{ detail.category.responsible_institution.institution_name }}
                  </p>
                </div>

                <!-- SLA -->
                <div v-show="detailTab === 'sla'">
                  <div class="table-responsive" style="max-height:280px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('wfSlaStages') }}</th><th>{{ t('catTargetHours') }}</th><th>{{ t('status') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="s in detail?.sla_configurations || []" :key="s.id">
                          <td>{{ s.stage }}</td>
                          <td>{{ s.target_hours }}h</td>
                          <td>{{ s.is_active ? t('active') : t('inactive') }}</td>
                        </tr>
                        <tr v-if="!(detail?.sla_configurations?.length)"><td colspan="3" class="text-muted">{{ t('catNoSlaOverrides') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                  <p class="small text-muted mt-2 mb-0">{{ t('catDefaultSla') }}: {{ detail?.category?.default_sla_days }} {{ t('catDays') }}</p>
                </div>

                <!-- Hierarchy -->
                <div v-show="detailTab === 'hierarchy'">
                  <p v-if="detail?.category?.parent" class="small"><strong>{{ t('catParent') }}:</strong> {{ detail.category.parent.code }} — {{ localizedName(detail.category.parent) }}</p>
                  <p v-else class="small text-muted">{{ t('catRootCategory') }}</p>
                  <div v-if="detail?.category?.children?.length" class="mt-3">
                    <h6 class="small fw-semibold">{{ t('catChildCategories') }}</h6>
                    <ul class="list-unstyled small">
                      <li v-for="ch in detail.category.children" :key="ch.id">
                        <span class="cat-code-badge me-1">{{ ch.code }}</span>{{ localizedName(ch) }}
                      </li>
                    </ul>
                  </div>
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

    <!-- Create / Edit -->
    <div v-if="showFormModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ editingId ? t('catEditCategory') : t('catAddCategory') }}</h5>
          <button type="button" class="btn-close" @click="closeFormModal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-3"><label class="form-label">{{ t('catCode') }} *</label>
              <input v-model="form.code" class="form-control text-uppercase" maxlength="20" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('catSortOrder') }}</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('catDefaultSla') }} *</label>
              <select v-model.number="form.default_sla_days" class="form-select">
                <option v-for="d in lookups.sla_day_options" :key="d" :value="d">{{ d }} {{ t('catDays') }}</option>
              </select></div>
            <div class="col-md-3"><label class="form-label">{{ t('catIcon') }}</label>
              <select v-model="form.icon" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="ic in lookups.icons" :key="ic" :value="ic">{{ ic }}</option>
              </select></div>
            <div class="col-md-6"><label class="form-label">{{ t('name') }} (EN) *</label>
              <input v-model="form.name_en" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('name') }} (FR) *</label>
              <input v-model="form.name_fr" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('catNameAr') }}</label>
              <input v-model="form.name_ar" class="form-control" dir="rtl" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('catParent') }}</label>
              <select v-model="form.parent_id" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="p in parentOptions" :key="p.id" :value="String(p.id)">{{ p.code }} — {{ localizedName(p) }}</option>
              </select></div>
            <div class="col-12"><label class="form-label">{{ t('catDescriptionEn') }}</label>
              <textarea v-model="form.description_en" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">{{ t('catDescriptionFr') }}</label>
              <textarea v-model="form.description_fr" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label">{{ t('catResponsibleInstitution') }}</label>
              <select v-model="form.responsible_institution_id" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="i in lookups.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
              </select></div>
            <div class="col-md-6"><label class="form-label">{{ t('catLinkedInstitutions') }}</label>
              <select v-model="form.institution_ids" class="form-select" multiple size="4">
                <option v-for="i in lookups.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
              </select></div>
            <div class="col-md-6"><div class="form-check mt-2">
              <input id="cat-active" v-model="form.is_active" type="checkbox" class="form-check-input" />
              <label class="form-check-label" for="cat-active">{{ t('active') }}</label>
            </div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="closeFormModal">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveCategory">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Deactivate -->
    <div v-if="lifecycleModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('uamDeactivate') }}</h5>
          <button type="button" class="btn-close" @click="lifecycleModal = null"></button></div>
        <div class="modal-body">
          <p class="small text-muted">{{ t('catDeactivateHint') }}</p>
          <label class="form-label">{{ t('notes') }}</label>
          <textarea v-model="lifecycleReason" class="form-control" rows="3"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="lifecycleModal = null">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-danger" @click="confirmDeactivate">{{ t('confirm') }}</button>
        </div>
      </div></div>
    </div>
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
const detailTab = ref('profile')
const stats = ref({ summary: {}, leaderboard: [] })
const lookups = ref({ institutions: [], parents: [], icons: [], sla_day_options: [] })
const categories = ref([])
const selected = ref(null)
const detail = ref(null)
const auditLogs = ref([])
const filters = ref({ search: '', is_active: '', parent_id: '', institution_id: '' })
const showFormModal = ref(false)
const editingId = ref(null)
const form = ref(emptyForm())
const lifecycleModal = ref(null)
const lifecycleReason = ref('')

const summary = computed(() => stats.value.summary || {})
const leaderboard = computed(() => stats.value.leaderboard || [])

const tabs = computed(() => [
  { key: 'overview', label: t('catTabOverview'), icon: 'ti ti-layout-dashboard' },
  { key: 'catalogue', label: t('catTabCatalogue'), icon: 'ti ti-list-details' },
])

const detailTabs = computed(() => [
  { key: 'profile', label: t('instTabProfile') },
  { key: 'cases', label: t('instTabCases') },
  { key: 'institutions', label: t('uamTabInstitutions') },
  { key: 'sla', label: t('catTabSla') },
  { key: 'hierarchy', label: t('catHierarchy') },
  { key: 'audit', label: t('instTabAudit') },
])

const overviewTiles = computed(() => [
  { label: t('catActive'), value: summary.value.active ?? 0 },
  { label: t('catInactive'), value: summary.value.inactive ?? 0 },
  { label: t('catWithParent'), value: summary.value.with_parent ?? 0 },
  { label: t('openCases'), value: summary.value.open_cases ?? 0 },
])

const hierarchyRoots = computed(() => categories.value.filter(c => !c.parent_id))
const parentOptions = computed(() => lookups.value.parents.filter(p => p.id !== editingId.value))

function emptyForm() {
  return {
    code: '', name_en: '', name_fr: '', name_ar: '', description_en: '', description_fr: '',
    icon: 'ti ti-category', sort_order: 0, parent_id: '', default_sla_days: 2,
    responsible_institution_id: '', institution_ids: [], is_active: true,
  }
}

function localizedName(item) {
  if (!item) return '—'
  return locale.value === 'fr' ? (item.name_fr || item.name_en) : (item.name_en || item.name_fr)
}

function localizedDescription(item) {
  if (!item) return ''
  return locale.value === 'fr' ? (item.description_fr || item.description_en || '') : (item.description_en || item.description_fr || '')
}

function childrenOf(parentId) {
  return categories.value.filter(c => c.parent_id === parentId)
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

async function refresh() {
  error.value = ''
  message.value = ''
  await loadAll()
}

async function loadAll(initial = false) {
  if (initial) loading.value = true
  try {
    const [statsRes, lookupRes] = await Promise.all([
      http.get('/category-mgmt/stats'),
      http.get('/category-mgmt/lookups'),
    ])
    stats.value = statsRes.data
    lookups.value = lookupRes.data
    await loadCategories()
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('catLoadError')
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  const { data } = await http.get('/category-mgmt', { params: buildFilterParams() })
  categories.value = data.data || []
}

async function selectCategory(cat) {
  selected.value = cat
  detailTab.value = 'profile'
  activeTab.value = 'catalogue'
  await loadDetail(cat.id)
}

async function selectFromLeaderboard(cat) {
  if (!cat?.id) return
  activeTab.value = 'catalogue'
  const found = categories.value.find(c => c.id === cat.id)
  await selectCategory(found || cat)
}

async function loadDetail(id) {
  try {
    const [detailRes, auditRes] = await Promise.all([
      http.get(`/category-mgmt/${id}`),
      http.get(`/category-mgmt/${id}/audit-logs`),
    ])
    detail.value = detailRes.data
    auditLogs.value = auditRes.data.logs || []
  } catch (e) {
    error.value = e?.response?.data?.message || t('catLoadError')
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
  const cat = detail.value?.category
  if (!cat) return
  editingId.value = cat.id
  form.value = {
    code: cat.code || '',
    name_en: cat.name_en || '',
    name_fr: cat.name_fr || '',
    name_ar: cat.name_ar || '',
    description_en: cat.description_en || '',
    description_fr: cat.description_fr || '',
    icon: cat.icon || '',
    sort_order: cat.sort_order ?? 0,
    parent_id: cat.parent_id ? String(cat.parent_id) : '',
    default_sla_days: cat.default_sla_days ?? 2,
    responsible_institution_id: cat.responsible_institution_id ? String(cat.responsible_institution_id) : '',
    institution_ids: (cat.institutions || []).map(i => String(i.id)),
    is_active: cat.is_active ?? true,
  }
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  editingId.value = null
}

function payloadFromForm() {
  const p = { ...form.value }
  p.code = (p.code || '').toUpperCase()
  p.parent_id = p.parent_id || null
  p.responsible_institution_id = p.responsible_institution_id || null
  p.institution_ids = (p.institution_ids || []).map(Number)
  return p
}

async function saveCategory() {
  try {
    if (editingId.value) {
      await http.put(`/category-mgmt/${editingId.value}`, payloadFromForm())
    } else {
      await http.post('/category-mgmt', payloadFromForm())
    }
    message.value = t('cmActionSuccess')
    closeFormModal()
    await loadAll()
    const id = editingId.value || selected.value?.id
    if (id) await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function lifecycleAction(action) {
  const id = selected.value?.id
  if (!id) return
  try {
    if (action === 'activate') await http.post(`/category-mgmt/${id}/activate`)
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
    await http.post(`/category-mgmt/${id}/deactivate`, { reason: lifecycleReason.value || null })
    lifecycleModal.value = null
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

<template>
  <div class="cm-enterprise wf-config">
    <section class="cm-hero wf-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-git-branch"></i></div>
              <div>
                <div class="hero-kicker">{{ t('wfHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('workflowSettings') }}</h1>
                <p class="hero-sub mb-0">{{ t('workflowSettingsSub') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="loadAll">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <RouterLink class="btn btn-outline-light btn-sm" :to="{ name: 'admin-complaints' }">
                <i class="ti ti-flag me-1"></i>{{ t('complaints') }}
              </RouterLink>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="cm-stat-grid">
              <div class="cm-stat"><div class="label">{{ t('activePipeline') }}</div><div class="value">{{ stats.summary.open_cases }}</div></div>
              <div class="cm-stat"><div class="label">{{ t('wfActiveTransitions') }}</div><div class="value">{{ stats.summary.active_transitions }}</div></div>
              <div class="cm-stat"><div class="label">{{ t('wfSlaRules') }}</div><div class="value">{{ stats.summary.sla_rules }}</div></div>
              <div class="cm-stat"><div class="label">{{ t('slaBreached') }}</div><div class="value" :class="{ 'text-warning': stats.summary.sla_breached > 0 }">{{ stats.summary.sla_breached }}</div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="card cm-card admin-lb-card mb-4">
      <div class="card-header bg-transparent">
        <h5 class="mb-0"><i class="ti ti-trophy me-2"></i>{{ t('wfSlaLeaderboard') }}</h5>
        <div class="text-muted small">{{ t('wfSlaLeaderboardSub') }}</div>
      </div>
      <div class="table-responsive">
        <table class="table admin-lb-table mb-0">
          <thead>
            <tr>
              <th>{{ t('institution') }}</th>
              <th class="text-end">{{ t('wfSlaStages') }}</th>
              <th class="text-end">{{ t('slaBreached') }}</th>
              <th class="text-end">{{ t('slaCompliancePct') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!stats.leaderboard.length"><td colspan="4" class="text-muted p-4">{{ t('noLeaderboardData') }}</td></tr>
            <tr v-for="(row, idx) in stats.leaderboard" :key="row.institution + idx">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="admin-leaderboard-rank" :class="rankClass(idx + 1)">{{ idx + 1 }}</span>
                  <div><div class="fw-semibold">{{ row.institution }}</div><div v-if="row.institution_type" class="text-muted small">{{ row.institution_type }}</div></div>
                </div>
              </td>
              <td class="text-end">{{ row.sla_stages }}</td>
              <td class="text-end">{{ row.sla_breached }}</td>
              <td class="text-end"><span class="admin-lb-pct" :class="pctClass(row.sla_pct)">{{ pct(row.sla_pct) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card cm-card mb-3">
      <div class="card-body py-3">
        <ul class="nav cm-tabs flex-wrap gap-2">
          <li v-for="tab in tabItems" :key="tab.key">
            <button type="button" class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
              <i :class="tab.icon" class="me-1"></i>{{ tab.label }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div v-if="message" class="alert alert-success">{{ message }}</div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-show="activeTab === 'overview'">
      <div class="row g-3 mb-4">
        <div v-for="stage in stats.stage_breaches" :key="stage.stage" class="col-md-4">
          <div class="card cm-card h-100"><div class="card-body">
            <div class="small text-muted text-uppercase">{{ stage.stage }}</div>
            <div class="h4 mb-1">{{ pct(stage.compliance_pct) }}</div>
            <div class="small text-muted">{{ stage.breached }} / {{ stage.total }} {{ t('wfBreaches') }}</div>
          </div></div>
        </div>
      </div>
      <div class="card cm-card">
        <div class="card-header bg-transparent"><h6 class="mb-0">{{ t('wfWorkflowMap') }}</h6></div>
        <div class="card-body">
          <div v-for="group in workflowMap" :key="group.from_status" class="wf-map-group mb-3">
            <div class="wf-map-from"><code>{{ group.from_status }}</code></div>
            <div v-for="step in group.steps" :key="step.id" class="wf-map-step" :class="{ 'is-inactive': !step.is_active }">
              <i class="ti ti-arrow-right me-1"></i><code>{{ step.to_status }}</code>
              <span class="ms-2 small text-muted">{{ transitionLabel(step) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-show="activeTab === 'transitions'">
      <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary btn-sm" @click="openTransitionForm()"><i class="ti ti-plus me-1"></i>{{ t('wfAddTransition') }}</button>
      </div>
      <ConfigTable :rows="transitions" :columns="transitionColumns" @edit="openTransitionForm" @delete="removeTransition" />
    </div>

    <div v-show="activeTab === 'sla'">
      <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary btn-sm" @click="openSlaForm()"><i class="ti ti-plus me-1"></i>{{ t('wfAddSlaRule') }}</button>
      </div>
      <ConfigTable :rows="slaConfigs" :columns="slaColumns" @edit="openSlaForm" @delete="removeSla" />
    </div>

    <div v-show="activeTab === 'notifications'">
      <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary btn-sm" @click="openNotificationForm()"><i class="ti ti-plus me-1"></i>{{ t('wfAddNotification') }}</button>
      </div>
      <ConfigTable :rows="templates" :columns="notificationColumns" @edit="openNotificationForm" @delete="removeNotification" />
    </div>

    <div v-show="activeTab === 'settings'" class="card cm-card">
      <div class="card-body">
        <form @submit.prevent="saveSettings" class="row g-3">
          <div class="col-md-3"><label class="form-label">{{ t('wfMaxUpload') }}</label><input v-model.number="settingsForm.max_attachment_size_kb" type="number" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">{{ t('wfRetentionYears') }}</label><input v-model.number="settingsForm.archive_retention_years" type="number" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">{{ t('wfDuplicateDays') }}</label><input v-model.number="settingsForm.duplicate_detection_days" type="number" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">{{ t('wfSmsSecret') }}</label><input v-model="settingsForm.sms_webhook_secret" type="text" class="form-control" /></div>
          <div class="col-12"><button type="submit" class="btn btn-primary" :disabled="saving">{{ t('save') }}</button></div>
        </form>
      </div>
    </div>

    <ConfigModal v-if="transitionForm.open" :title="transitionForm.id ? t('wfEditTransition') : t('wfAddTransition')" @close="transitionForm.open = false">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">{{ t('wfFrom') }}</label><select v-model="transitionForm.from_status" class="form-select"><option v-for="s in lookups.statuses" :key="s.code" :value="s.code">{{ statusLabel(s) }}</option></select></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfTo') }}</label><select v-model="transitionForm.to_status" class="form-select"><option v-for="s in lookups.statuses" :key="'t-'+s.code" :value="s.code">{{ statusLabel(s) }}</option></select></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfLabelEn') }}</label><input v-model="transitionForm.label_en" class="form-control" /></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfLabelFr') }}</label><input v-model="transitionForm.label_fr" class="form-control" /></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfPermission') }}</label><select v-model="transitionForm.required_permission" class="form-select"><option value="">{{ t('wfNone') }}</option><option v-for="p in lookups.permissions" :key="p" :value="p">{{ p }}</option></select></div>
        <div class="col-md-3"><label class="form-label">{{ t('wfOrder') }}</label><input v-model.number="transitionForm.sort_order" type="number" class="form-control" /></div>
        <div class="col-md-3 d-flex align-items-end gap-2"><div class="form-check"><input id="tc" v-model="transitionForm.requires_comment" type="checkbox" class="form-check-input" /><label for="tc" class="form-check-label">{{ t('wfComment') }}</label></div><div class="form-check"><input id="ta" v-model="transitionForm.is_active" type="checkbox" class="form-check-input" /><label for="ta" class="form-check-label">{{ t('wfActive') }}</label></div></div>
      </div>
      <template #actions><button type="button" class="btn btn-primary" :disabled="saving" @click="saveTransition">{{ t('save') }}</button></template>
    </ConfigModal>

    <ConfigModal v-if="slaForm.open" :title="slaForm.id ? t('wfEditSlaRule') : t('wfAddSlaRule')" @close="slaForm.open = false">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">{{ t('cmSlaStage') }}</label><select v-model="slaForm.stage" class="form-select"><option v-for="s in lookups.sla_stages" :key="s" :value="s">{{ s }}</option></select></div>
        <div class="col-md-6"><label class="form-label">{{ t('category') }}</label><select v-model="slaForm.ntb_category_id" class="form-select"><option value="">{{ t('wfGlobal') }}</option><option v-for="c in lookups.categories" :key="c.id" :value="String(c.id)">{{ localizedName(c) }}</option></select></div>
        <div class="col-md-4"><label class="form-label">{{ t('cmSlaTarget') }}</label><input v-model.number="slaForm.target_hours" type="number" class="form-control" /></div>
        <div class="col-md-4"><label class="form-label">{{ t('wfAlertThreshold') }}</label><input v-model.number="slaForm.alert_threshold_percent" type="number" class="form-control" /></div>
        <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input id="sa" v-model="slaForm.is_active" type="checkbox" class="form-check-input" /><label for="sa" class="form-check-label">{{ t('wfActive') }}</label></div></div>
      </div>
      <template #actions><button type="button" class="btn btn-primary" :disabled="saving" @click="saveSla">{{ t('save') }}</button></template>
    </ConfigModal>

    <ConfigModal v-if="notificationForm.open" wide :title="notificationForm.id ? t('wfEditNotification') : t('wfAddNotification')" @close="notificationForm.open = false">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">{{ t('wfEvent') }}</label><select v-model="notificationForm.event_type" class="form-select" :disabled="!!notificationForm.id"><option v-for="e in lookups.event_types" :key="e" :value="e">{{ e }}</option></select></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfChannels') }}</label><div class="d-flex gap-3 pt-2"><div v-for="ch in lookups.notification_channels" :key="ch" class="form-check"><input :id="'c-'+ch" v-model="notificationForm.channels" type="checkbox" class="form-check-input" :value="ch" /><label :for="'c-'+ch" class="form-check-label">{{ ch }}</label></div></div></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfSubjectEn') }}</label><input v-model="notificationForm.subject_en" class="form-control" /></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfSubjectFr') }}</label><input v-model="notificationForm.subject_fr" class="form-control" /></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfBodyEn') }}</label><textarea v-model="notificationForm.body_en" class="form-control" rows="3"></textarea></div>
        <div class="col-md-6"><label class="form-label">{{ t('wfBodyFr') }}</label><textarea v-model="notificationForm.body_fr" class="form-control" rows="3"></textarea></div>
        <div class="col-12"><p class="small text-muted mb-0">{{ t('wfPlaceholderHint') }}</p></div>
      </div>
      <template #actions><button type="button" class="btn btn-primary" :disabled="saving" @click="saveNotification">{{ t('save') }}</button></template>
    </ConfigModal>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const ConfigTable = defineComponent({
  name: 'ConfigTable',
  props: { rows: Array, columns: Array },
  emits: ['edit', 'delete'],
  setup(props, { emit }) {
    const { t } = useI18n()
    return () => h('div', { class: 'card cm-card' }, [
      h('div', { class: 'table-responsive' }, [
        h('table', { class: 'table cm-table mb-0' }, [
          h('thead', null, h('tr', null, [...props.columns.map((c) => h('th', null, c.label)), h('th')])),
          h('tbody', null, props.rows.map((row) => h('tr', { key: row.id }, [
            ...props.columns.map((c) => h('td', null, c.render(row))),
            h('td', { class: 'text-end' }, [
              h('button', { type: 'button', class: 'btn btn-sm btn-outline-primary me-1', onClick: () => emit('edit', row) }, t('edit')),
              h('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', onClick: () => emit('delete', row) }, t('delete')),
            ]),
          ]))),
        ]),
      ]),
    ])
  },
})

const ConfigModal = defineComponent({
  name: 'ConfigModal',
  props: { title: String, wide: Boolean },
  emits: ['close'],
  setup(props, { emit, slots }) {
    return () => h('div', { class: 'wf-modal-backdrop', onClick: (e) => e.target === e.currentTarget && emit('close') }, [
      h('div', { class: ['card cm-card wf-modal', props.wide ? 'wf-modal--wide' : ''] }, [
        h('div', { class: 'card-header bg-transparent d-flex justify-content-between' }, [
          h('h6', { class: 'mb-0' }, props.title),
          h('button', { type: 'button', class: 'btn-close', onClick: () => emit('close') }),
        ]),
        h('div', { class: 'card-body' }, slots.default?.()),
        h('div', { class: 'card-footer bg-transparent d-flex gap-2' }, slots.actions?.()),
      ]),
    ])
  },
})

const { t, locale, localizedName } = useI18n()
const loading = ref(false)
const saving = ref(false)
const activeTab = ref('overview')
const transitions = ref([])
const workflowMap = ref([])
const slaConfigs = ref([])
const templates = ref([])
const stats = ref({ summary: { open_cases: 0, sla_breached: 0, active_transitions: 0, sla_rules: 0 }, stage_breaches: [], leaderboard: [] })
const lookups = ref({ statuses: [], categories: [], permissions: [], sla_stages: [], notification_channels: [], event_types: [] })
const settingsForm = ref({ max_attachment_size_kb: 10240, archive_retention_years: 7, duplicate_detection_days: 14, sms_webhook_secret: '' })
const message = ref('')
const error = ref('')

const transitionForm = reactive({ open: false, id: null, from_status: '', to_status: '', label_en: '', label_fr: '', required_permission: '', requires_comment: false, sort_order: 0, is_active: true })
const slaForm = reactive({ open: false, id: null, stage: 'VALIDATION', ntb_category_id: '', target_hours: 8, alert_threshold_percent: 80, is_active: true })
const notificationForm = reactive({ open: false, id: null, event_type: 'complaint_created', subject_en: '', subject_fr: '', body_en: '', body_fr: '', channels: ['email'], is_active: true })

const tabItems = computed(() => [
  { key: 'overview', label: t('cmTabOverview'), icon: 'ti ti-layout-dashboard' },
  { key: 'transitions', label: t('wfTransitions'), icon: 'ti ti-git-branch' },
  { key: 'sla', label: t('wfSlaConfig'), icon: 'ti ti-clock' },
  { key: 'notifications', label: t('wfNotifications'), icon: 'ti ti-bell' },
  { key: 'settings', label: t('settings'), icon: 'ti ti-settings' },
])

const transitionColumns = computed(() => [
  { label: t('wfFrom'), render: (r) => r.from_status },
  { label: t('wfTo'), render: (r) => r.to_status },
  { label: t('wfLabel'), render: (r) => transitionLabel(r) },
  { label: t('wfOrder'), render: (r) => r.sort_order },
])

const slaColumns = computed(() => [
  { label: t('cmSlaStage'), render: (r) => r.stage },
  { label: t('category'), render: (r) => r.category?.code || t('wfGlobal') },
  { label: t('cmSlaTarget'), render: (r) => `${r.target_hours}h` },
])

const notificationColumns = computed(() => [
  { label: t('wfEvent'), render: (r) => r.event_type },
  { label: t('wfSubject'), render: (r) => r.subject_en || '—' },
  { label: t('wfChannels'), render: (r) => (r.channels || []).join(', ') },
])

onMounted(loadAll)

async function loadAll() {
  loading.value = true
  error.value = ''
  try {
    const [st, lu, tr, sla, tpl, set] = await Promise.all([
      http.get('/workflow/stats'), http.get('/workflow/lookups'), http.get('/workflow/transitions'),
      http.get('/workflow/sla-configurations'), http.get('/workflow/notification-templates'), http.get('/workflow/system-settings'),
    ])
    stats.value = st.data
    lookups.value = lu.data
    transitions.value = tr.data.transitions || []
    workflowMap.value = tr.data.workflow_map || []
    slaConfigs.value = sla.data.configurations || []
    templates.value = tpl.data.templates || []
    settingsForm.value = { ...settingsForm.value, ...set.data.settings }
  } catch (e) {
    error.value = e?.response?.data?.message || t('dashboardLoadError')
  } finally {
    loading.value = false
  }
}

function transitionLabel(row) {
  return locale.value === 'fr' ? (row.label_fr || row.label_en || row.to_status) : (row.label_en || row.to_status)
}
function statusLabel(s) { return locale.value === 'fr' ? (s.name_fr || s.code) : (s.name_en || s.code) }

function openTransitionForm(row = null) {
  Object.assign(transitionForm, { open: true, id: row?.id || null, from_status: row?.from_status || lookups.value.statuses[0]?.code || 'submitted', to_status: row?.to_status || 'under_validation', label_en: row?.label_en || '', label_fr: row?.label_fr || '', required_permission: row?.required_permission || '', requires_comment: row?.requires_comment ?? false, sort_order: row?.sort_order ?? 0, is_active: row?.is_active ?? true })
}
async function saveTransition() {
  saving.value = true
  try {
    const payload = { from_status: transitionForm.from_status, to_status: transitionForm.to_status, label_en: transitionForm.label_en, label_fr: transitionForm.label_fr, required_permission: transitionForm.required_permission || null, requires_comment: transitionForm.requires_comment, sort_order: transitionForm.sort_order, is_active: transitionForm.is_active }
    if (transitionForm.id) await http.put(`/workflow/transitions/${transitionForm.id}`, payload)
    else await http.post('/workflow/transitions', payload)
    transitionForm.open = false
    message.value = t('cmActionSuccess')
    await loadAll()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') } finally { saving.value = false }
}
async function removeTransition(row) {
  if (!confirm(t('wfConfirmDelete'))) return
  try {
    await http.delete(`/workflow/transitions/${row.id}`)
    message.value = t('cmActionSuccess')
    await loadAll()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

function openSlaForm(row = null) {
  Object.assign(slaForm, { open: true, id: row?.id || null, stage: row?.stage || 'VALIDATION', ntb_category_id: row?.ntb_category_id ? String(row.ntb_category_id) : '', target_hours: row?.target_hours ?? 8, alert_threshold_percent: row?.alert_threshold_percent ?? 80, is_active: row?.is_active ?? true })
}
async function saveSla() {
  saving.value = true
  try {
    const payload = { stage: slaForm.stage, ntb_category_id: slaForm.ntb_category_id || null, target_hours: slaForm.target_hours, alert_threshold_percent: slaForm.alert_threshold_percent, is_active: slaForm.is_active }
    if (slaForm.id) await http.put(`/workflow/sla-configurations/${slaForm.id}`, payload)
    else await http.post('/workflow/sla-configurations', payload)
    slaForm.open = false
    message.value = t('cmActionSuccess')
    await loadAll()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') } finally { saving.value = false }
}
async function removeSla(row) {
  if (!confirm(t('wfConfirmDelete'))) return
  try {
    await http.delete(`/workflow/sla-configurations/${row.id}`)
    message.value = t('cmActionSuccess')
    await loadAll()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

function openNotificationForm(row = null) {
  Object.assign(notificationForm, { open: true, id: row?.id || null, event_type: row?.event_type || 'complaint_created', subject_en: row?.subject_en || '', subject_fr: row?.subject_fr || '', body_en: row?.body_en || '', body_fr: row?.body_fr || '', channels: [...(row?.channels || ['email'])], is_active: row?.is_active ?? true })
}
async function saveNotification() {
  saving.value = true
  try {
    const payload = { event_type: notificationForm.event_type, subject_en: notificationForm.subject_en, subject_fr: notificationForm.subject_fr, body_en: notificationForm.body_en, body_fr: notificationForm.body_fr, channels: notificationForm.channels, is_active: notificationForm.is_active }
    if (notificationForm.id) await http.put(`/workflow/notification-templates/${notificationForm.id}`, payload)
    else await http.post('/workflow/notification-templates', payload)
    notificationForm.open = false
    message.value = t('cmActionSuccess')
    await loadAll()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') } finally { saving.value = false }
}
async function removeNotification(row) {
  if (!confirm(t('wfConfirmDelete'))) return
  try {
    await http.delete(`/workflow/notification-templates/${row.id}`)
    message.value = t('cmActionSuccess')
    await loadAll()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

async function saveSettings() {
  saving.value = true
  try { await http.put('/workflow/system-settings', settingsForm.value); message.value = t('cmSettingsSaved') }
  catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') } finally { saving.value = false }
}

function rankClass(rank) { return rank === 1 ? 'admin-leaderboard-rank--1' : rank === 2 ? 'admin-leaderboard-rank--2' : rank === 3 ? 'admin-leaderboard-rank--3' : '' }
function pctClass(v) { return v >= 90 ? 'admin-lb-pct--good' : v >= 75 ? 'admin-lb-pct--warn' : 'admin-lb-pct--bad' }
function pct(n) { return `${Number(n || 0).toFixed(1)}%` }
</script>

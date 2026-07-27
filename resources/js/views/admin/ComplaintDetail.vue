<template>
  <div v-if="complaint" class="cm-enterprise">
    <RouterLink class="text-muted small d-inline-block mb-3" :to="{ name: 'admin-complaints' }">← {{ t('complaints') }}</RouterLink>

    <div class="cm-detail-header mb-4">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <h3 class="mb-0">{{ complaint.complaint_code }}</h3>
            <span class="badge bg-primary">{{ localizedName(complaint.status) }}</span>
            <span v-if="slaBadge" class="badge" :class="slaBadge.class">{{ slaBadge.label }}</span>
          </div>
          <h5 class="text-muted fw-normal mb-2">{{ complaint.title }}</h5>
          <div class="small text-muted">
            {{ t('submittedOn') }} {{ formatDateTime(complaint.created_at) }}
            · {{ complaint.submission_channel }}
            <span v-if="complaint.gender"> · {{ t('gender') }}: {{ complaint.gender }}</span>
          </div>
        </div>
        <div class="text-end">
          <div class="small text-muted">{{ t('slaDeadline') }}</div>
          <div class="fw-semibold">{{ formatDateTime(complaint.sla_deadline) }}</div>
        </div>
      </div>
    </div>

    <div v-if="message" class="alert alert-success">{{ message }}</div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <ul class="nav cm-detail-tabs mb-4 border-bottom">
      <li v-for="tab in detailTabs" :key="tab.key">
        <button type="button" class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
          <i :class="tab.icon" class="me-1"></i>{{ tab.label }}
        </button>
      </li>
    </ul>

    <!-- Overview -->
    <div v-show="activeTab === 'overview'" class="row g-4">
      <div class="col-xl-8">
        <div class="card cm-card"><div class="card-body">
          <h6 class="mb-3">{{ t('description') }}</h6>
          <p class="mb-0">{{ complaint.description }}</p>
        </div></div>
        <div class="card cm-card mt-3"><div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><strong>{{ t('category') }}</strong><div>{{ localizedName(complaint.category) }}</div></div>
            <div class="col-md-6"><strong>{{ t('corridor') }}</strong><div>{{ localizedName(complaint.corridor) }}</div></div>
            <div class="col-md-6"><strong>{{ t('location') }}</strong><div>{{ localizedName(complaint.location) || '—' }}</div></div>
            <div class="col-md-6"><strong>{{ t('institution') }}</strong><div>{{ complaint.assigned_institution?.institution_name || '—' }}</div></div>
            <div class="col-md-6"><strong>{{ t('reporter') }}</strong><div>{{ complaint.reporter_name || t('anonymous') }}</div></div>
            <div class="col-md-6"><strong>{{ t('contact') }}</strong><div>{{ complaint.reporter_email || complaint.reporter_phone || '—' }}</div></div>
          </div>
        </div></div>
      </div>
      <div class="col-xl-4">
        <div class="cm-action-card">
          <h6 class="mb-3">{{ t('cmQuickActions') }}</h6>
          <div class="d-grid gap-2">
            <button v-if="canValidate" class="btn btn-primary btn-sm" :disabled="busy" @click="run('validate')">{{ t('validate') }}</button>
            <button v-if="canRequestInfo" class="btn btn-outline-primary btn-sm" :disabled="busy" @click="run('request-info')">{{ t('cmRequestInfo') }}</button>
            <button v-if="canAssign" class="btn btn-outline-primary btn-sm" :disabled="busy" @click="showAssign = true">{{ t('assign') }}</button>
            <button v-if="canProcess" class="btn btn-outline-primary btn-sm" :disabled="busy" @click="run('process')">{{ t('process') }}</button>
            <button v-if="canResolve" class="btn btn-outline-success btn-sm" :disabled="busy" @click="showResolve = true">{{ t('resolve') }}</button>
            <button v-if="canClose" class="btn btn-outline-secondary btn-sm" :disabled="busy" @click="showClose = true">{{ t('close') }}</button>
            <button v-if="canArchive" class="btn btn-outline-dark btn-sm" :disabled="busy" @click="run('archive')">{{ t('cmArchive') }}</button>
            <button v-if="canReopen" class="btn btn-outline-warning btn-sm" :disabled="busy" @click="showReopen = true">{{ t('cmReopen') }}</button>
            <button v-if="canEscalate" class="btn btn-outline-danger btn-sm" :disabled="busy" @click="showEscalate = true">{{ t('escalate') }}</button>
            <button v-if="canReject" class="btn btn-outline-danger btn-sm" :disabled="busy" @click="run('reject')">{{ t('reject') }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Workflow -->
    <div v-show="activeTab === 'workflow'" class="card cm-card">
      <div class="card-body">
        <h6>{{ t('cmAllowedTransitions') }}</h6>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <span v-for="tr in allowed" :key="tr" class="badge bg-label-primary">{{ tr }}</span>
          <span v-if="!allowed.length" class="text-muted">{{ t('cmNoTransitions') }}</span>
        </div>
        <div v-if="complaint.resolution_note" class="alert alert-info">
          <strong>{{ t('cmResolutionNote') }}</strong>
          <div class="small mt-1">{{ complaint.resolution_note }}</div>
        </div>
        <div v-if="complaint.closure_summary" class="alert alert-secondary">
          <strong>{{ t('cmClosureSummary') }}</strong>
          <div class="small mt-1">{{ complaint.closure_summary }}</div>
        </div>
        <div v-if="complaint.escalations?.length">
          <h6 class="mt-3">{{ t('escalate') }}</h6>
          <ul class="list-group list-group-flush">
            <li v-for="e in complaint.escalations" :key="e.id" class="list-group-item px-0">
              <div class="fw-semibold">L{{ e.escalation_level }} · {{ e.escalation_type }}</div>
              <div class="small text-muted">{{ e.escalation_reason }}</div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div v-show="activeTab === 'timeline'" class="card cm-card">
      <div class="card-body">
        <div class="mb-4">
          <label class="form-label">{{ t('comment') }}</label>
          <textarea v-model="comment" class="form-control mb-2" rows="3"></textarea>
          <button type="button" class="btn btn-sm btn-primary" :disabled="busy || !comment.trim()" @click="addComment">{{ t('cmAddComment') }}</button>
        </div>
        <div v-if="!complaint.histories?.length" class="text-muted">{{ t('noTimeline') }}</div>
        <div v-for="h in complaint.histories" :key="h.id" class="cm-timeline-item">
          <div class="fw-semibold">{{ h.action_type }} <span v-if="h.to_status" class="text-muted fw-normal">→ {{ h.to_status }}</span></div>
          <div class="small text-muted">{{ formatDateTime(h.action_date) }} · {{ h.performer?.name || t('system') }}</div>
          <div v-if="h.comment" class="small mt-1">{{ h.comment }}</div>
        </div>
      </div>
    </div>

    <!-- Documents -->
    <div v-show="activeTab === 'documents'" class="card cm-card">
      <div class="card-body">
        <div v-if="!complaint.attachments?.length" class="text-muted">{{ t('cmNoAttachments') }}</div>
        <ul v-else class="list-group list-group-flush">
          <li v-for="a in complaint.attachments" :key="a.id" class="list-group-item d-flex justify-content-between align-items-center px-0">
            <div>
              <i class="ti ti-paperclip me-2"></i>{{ a.original_name }}
              <span class="text-muted small">({{ formatSize(a.file_size) }})</span>
            </div>
            <a class="btn btn-sm btn-outline-primary" :href="attachmentUrl(a.id)" target="_blank" rel="noopener">{{ t('download') }}</a>
          </li>
        </ul>
      </div>
    </div>

    <!-- SLA -->
    <div v-show="activeTab === 'sla'" class="card cm-card">
      <div class="card-body table-responsive">
        <table class="table cm-table mb-0">
          <thead>
            <tr>
              <th>{{ t('cmSlaStage') }}</th>
              <th>{{ t('cmSlaTarget') }}</th>
              <th>{{ t('cmSlaDeadline') }}</th>
              <th>{{ t('status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!complaint.sla_trackings?.length">
              <td colspan="4" class="text-muted">{{ t('cmNoSla') }}</td>
            </tr>
            <tr v-for="s in complaint.sla_trackings" :key="s.id">
              <td>{{ s.stage }}</td>
              <td>{{ s.sla_target_hours }}h</td>
              <td>{{ formatDateTime(s.deadline_at) }}</td>
              <td>
                <span class="badge" :class="s.is_breached ? 'cm-sla-breach' : (s.completed_at ? 'cm-sla-ok' : 'cm-sla-warn')">
                  {{ s.is_breached ? t('slaBreached') : (s.completed_at ? t('cmSlaComplete') : t('cmSlaActive')) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Closure -->
    <div v-show="activeTab === 'closure'" class="card cm-card">
      <div class="card-body">
        <p class="text-muted">{{ t('cmClosureHelp') }}</p>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <h6>{{ t('close') }}</h6>
              <p class="small text-muted">{{ t('cmCloseDesc') }}</p>
              <button v-if="canClose" class="btn btn-sm btn-primary" @click="showClose = true">{{ t('close') }}</button>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <h6>{{ t('cmArchive') }}</h6>
              <p class="small text-muted">{{ t('cmArchiveDesc') }}</p>
              <button v-if="canArchive" class="btn btn-sm btn-dark" @click="run('archive')">{{ t('cmArchive') }}</button>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <h6>{{ t('cmReopen') }}</h6>
              <p class="small text-muted">{{ t('cmReopenDesc') }}</p>
              <button v-if="canReopen" class="btn btn-sm btn-warning" @click="showReopen = true">{{ t('cmReopen') }}</button>
            </div>
          </div>
        </div>
        <div v-if="complaint.closed_at" class="mt-4 small text-muted">
          {{ t('cmClosedAt') }}: {{ formatDateTime(complaint.closed_at) }}
          <span v-if="complaint.archived_at"> · {{ t('cmArchivedAt') }}: {{ formatDateTime(complaint.archived_at) }}</span>
        </div>
      </div>
    </div>

    <!-- Audit -->
    <div v-show="activeTab === 'audit'" class="card cm-card">
      <div class="card-body table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>{{ t('cmAuditAction') }}</th><th>{{ t('submittedOn') }}</th><th>{{ t('cmAuditUser') }}</th></tr></thead>
          <tbody>
            <tr v-if="!auditLogs.length"><td colspan="3" class="text-muted">{{ t('cmNoAudit') }}</td></tr>
            <tr v-for="log in auditLogs" :key="log.id">
              <td>{{ log.action }}</td>
              <td class="small">{{ formatDateTime(log.created_at) }}</td>
              <td class="small">{{ log.user_id || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modals (simple inline panels) -->
    <div v-if="showAssign" class="card cm-card mt-3"><div class="card-body">
      <h6>{{ t('assign') }}</h6>
      <select v-model="assignForm.institution_id" class="form-select mb-2">
        <option value="">{{ t('selectInstitution') }}</option>
        <option v-for="i in institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
      </select>
      <select v-model="assignForm.handler_id" class="form-select mb-2">
        <option value="">{{ t('cmOptionalHandler') }}</option>
        <option v-for="u in handlers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
      </select>
      <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" :disabled="busy" @click="submitAssign">{{ t('assign') }}</button>
        <button class="btn btn-outline-secondary btn-sm" @click="showAssign = false">{{ t('cancel') }}</button>
      </div>
    </div></div>

    <div v-if="showResolve" class="card cm-card mt-3"><div class="card-body">
      <h6>{{ t('resolve') }}</h6>
      <textarea v-model="resolveNote" class="form-control mb-2" rows="3" :placeholder="t('cmResolutionNote')"></textarea>
      <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm" :disabled="busy || !resolveNote.trim()" @click="submitResolve">{{ t('resolve') }}</button>
        <button class="btn btn-outline-secondary btn-sm" @click="showResolve = false">{{ t('cancel') }}</button>
      </div>
    </div></div>

    <div v-if="showClose" class="card cm-card mt-3"><div class="card-body">
      <h6>{{ t('close') }}</h6>
      <textarea v-model="closeSummary" class="form-control mb-2" rows="3" :placeholder="t('cmClosureSummary')"></textarea>
      <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" :disabled="busy" @click="submitClose">{{ t('close') }}</button>
        <button class="btn btn-outline-secondary btn-sm" @click="showClose = false">{{ t('cancel') }}</button>
      </div>
    </div></div>

    <div v-if="showReopen" class="card cm-card mt-3"><div class="card-body">
      <h6>{{ t('cmReopen') }}</h6>
      <textarea v-model="reopenReason" class="form-control mb-2" rows="3" :placeholder="t('cmReopenReason')"></textarea>
      <div class="d-flex gap-2">
        <button class="btn btn-warning btn-sm" :disabled="busy || !reopenReason.trim()" @click="submitReopen">{{ t('cmReopen') }}</button>
        <button class="btn btn-outline-secondary btn-sm" @click="showReopen = false">{{ t('cancel') }}</button>
      </div>
    </div></div>

    <div v-if="showEscalate" class="card cm-card mt-3"><div class="card-body">
      <h6>{{ t('escalate') }}</h6>
      <textarea v-model="escalateReason" class="form-control mb-2" rows="3"></textarea>
      <div class="d-flex gap-2">
        <button class="btn btn-danger btn-sm" :disabled="busy || !escalateReason.trim()" @click="submitEscalate">{{ t('escalate') }}</button>
        <button class="btn btn-outline-secondary btn-sm" @click="showEscalate = false">{{ t('cancel') }}</button>
      </div>
    </div></div>
  </div>
  <div v-else class="text-center py-5 text-muted">{{ t('loading') }}</div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { http } from '@/api/http'
import { hasPermission } from '@/auth/session'
import { useI18n } from '@/composables/useI18n'

const route = useRoute()
const { t, localizedName, locale } = useI18n()

const complaint = ref(null)
const allowed = ref([])
const auditLogs = ref([])
const institutions = ref([])
const handlers = ref([])
const activeTab = ref('overview')
const comment = ref('')
const busy = ref(false)
const message = ref('')
const error = ref('')

const showAssign = ref(false)
const showResolve = ref(false)
const showClose = ref(false)
const showReopen = ref(false)
const showEscalate = ref(false)
const assignForm = ref({ institution_id: '', handler_id: '' })
const resolveNote = ref('')
const closeSummary = ref('')
const reopenReason = ref('')
const escalateReason = ref('')

const statusCode = computed(() => complaint.value?.status?.code || '')

const detailTabs = computed(() => [
  { key: 'overview', label: t('cmTabOverview'), icon: 'ti ti-layout-dashboard' },
  { key: 'workflow', label: t('cmTabWorkflow'), icon: 'ti ti-git-branch' },
  { key: 'timeline', label: t('timeline'), icon: 'ti ti-history' },
  { key: 'documents', label: t('cmTabDocuments'), icon: 'ti ti-paperclip' },
  { key: 'sla', label: t('cmTabSla'), icon: 'ti ti-clock' },
  { key: 'closure', label: t('cmTabClosure'), icon: 'ti ti-archive' },
  { key: 'audit', label: t('audit'), icon: 'ti ti-shield-check' },
])

const slaBadge = computed(() => {
  const breached = complaint.value?.sla_trackings?.some((s) => s.is_breached)
  if (breached) return { class: 'cm-sla-breach', label: t('slaBreached') }
  if (['closed', 'archived'].includes(statusCode.value)) return { class: 'cm-sla-ok', label: t('closedCases') }
  return null
})

const canValidate = computed(() => hasPermission('complaints.validate') && ['submitted', 'under_validation'].includes(statusCode.value))
const canRequestInfo = computed(() => hasPermission('complaints.validate') && allowed.value.includes('additional_info_requested'))
const canAssign = computed(() => hasPermission('complaints.assign') && allowed.value.includes('assigned'))
const canProcess = computed(() => hasPermission('complaints.process') && ['assigned', 'in_processing', 'reopened'].includes(statusCode.value))
const canResolve = computed(() => hasPermission('complaints.process') && allowed.value.includes('resolved'))
const canClose = computed(() => hasPermission('complaints.close') && allowed.value.includes('closed'))
const canArchive = computed(() => hasPermission('complaints.close') && allowed.value.includes('archived'))
const canReopen = computed(() => hasPermission('complaints.reopen') && allowed.value.includes('reopened'))
const canEscalate = computed(() => hasPermission('complaints.escalate') && allowed.value.includes('escalated'))
const canReject = computed(() => hasPermission('complaints.validate') && allowed.value.includes('rejected'))

onMounted(load)

async function load() {
  const [detailRes, lookupRes] = await Promise.all([
    http.get(`/complaints/${route.params.id}`),
    http.get('/complaints/lookups'),
  ])
  complaint.value = detailRes.data.complaint
  allowed.value = detailRes.data.allowed_transitions || []
  auditLogs.value = detailRes.data.audit_logs || []
  institutions.value = lookupRes.data.institutions || []
  handlers.value = lookupRes.data.handlers || []
  if (complaint.value?.category?.responsible_institution_id) {
    assignForm.value.institution_id = String(complaint.value.category.responsible_institution_id)
  }
}

async function run(type) {
  busy.value = true
  message.value = ''
  error.value = ''
  try {
    const body = { comment: comment.value || undefined }
    if (type === 'reject' || type === 'archive' || type === 'request-info') {
      if (!comment.value.trim() && (type === 'reject' || type === 'request-info')) {
        error.value = t('cmCommentRequired')
        return
      }
    }
    await http.post(`/complaints/${route.params.id}/${type}`, body)
    message.value = t('cmActionSuccess')
    comment.value = ''
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  } finally {
    busy.value = false
  }
}

async function addComment() {
  busy.value = true
  try {
    await http.post(`/complaints/${route.params.id}/comment`, { comment: comment.value })
    comment.value = ''
    message.value = t('cmCommentAdded')
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  } finally {
    busy.value = false
  }
}

async function submitAssign() {
  busy.value = true
  try {
    await http.post(`/complaints/${route.params.id}/assign`, {
      assigned_institution_id: assignForm.value.institution_id,
      current_handler_id: assignForm.value.handler_id || null,
      comment: comment.value || undefined,
    })
    showAssign.value = false
    message.value = t('cmActionSuccess')
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  } finally {
    busy.value = false
  }
}

async function submitResolve() {
  busy.value = true
  try {
    await http.post(`/complaints/${route.params.id}/resolve`, {
      resolution_note: resolveNote.value,
      comment: comment.value || undefined,
    })
    showResolve.value = false
    resolveNote.value = ''
    message.value = t('cmActionSuccess')
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  } finally {
    busy.value = false
  }
}

async function submitClose() {
  busy.value = true
  try {
    await http.post(`/complaints/${route.params.id}/close`, {
      closure_summary: closeSummary.value,
      comment: comment.value || undefined,
    })
    showClose.value = false
    closeSummary.value = ''
    message.value = t('cmActionSuccess')
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  } finally {
    busy.value = false
  }
}

async function submitReopen() {
  busy.value = true
  try {
    await http.post(`/complaints/${route.params.id}/reopen`, { comment: reopenReason.value })
    showReopen.value = false
    reopenReason.value = ''
    message.value = t('cmActionSuccess')
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  } finally {
    busy.value = false
  }
}

async function submitEscalate() {
  busy.value = true
  try {
    await http.post(`/complaints/${route.params.id}/escalate`, {
      escalation_level: 1,
      escalation_type: 'manual',
      escalation_reason: escalateReason.value,
    })
    showEscalate.value = false
    escalateReason.value = ''
    message.value = t('cmActionSuccess')
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  } finally {
    busy.value = false
  }
}

function attachmentUrl(id) {
  return `/api/v1/complaints/${route.params.id}/attachments/${id}`
}

function formatDateTime(v) {
  return v ? new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB') : '—'
}

function formatSize(bytes) {
  if (!bytes) return '0 B'
  const kb = bytes / 1024
  return kb > 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${kb.toFixed(0)} KB`
}
</script>

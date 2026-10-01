<template>
  <div class="ussd-sim cm-enterprise">
    <section class="card cm-card mb-4">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div>
            <h1 class="h4 mb-1">{{ t('ussdSimTitle') }}</h1>
            <p class="text-muted mb-0 small">{{ t('ussdSimLead') }}</p>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="loadSchema">
              <i class="ti ti-book me-1"></i>{{ t('ussdSimIntegration') }}
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" @click="resetSession">
              <i class="ti ti-refresh me-1"></i>{{ t('ussdSimNewSession') }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="ussd-phone mx-auto">
          <div class="ussd-phone__header">{{ shortCode }}</div>
          <div class="ussd-phone__screen">
            <pre class="ussd-phone__text">{{ screenText }}</pre>
            <div v-if="busy" class="ussd-phone__loading">{{ t('loading') }}…</div>
          </div>
          <div class="ussd-phone__input row g-2">
            <div class="col-8">
              <input v-model="pendingInput" type="text" class="form-control form-control-sm" :placeholder="t('ussdSimInputHint')" @keyup.enter="sendInput" />
            </div>
            <div class="col-4">
              <button type="button" class="btn btn-primary btn-sm w-100" :disabled="busy" @click="sendInput">{{ t('ussdSimSend') }}</button>
            </div>
          </div>
          <div class="ussd-phone__keypad">
            <button v-for="k in keys" :key="k" type="button" class="btn btn-outline-dark btn-sm" @click="appendKey(k)">{{ k }}</button>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card cm-card mb-3">
          <div class="card-body">
            <h6 class="mb-3">{{ t('ussdSimSettings') }}</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">{{ t('ussdSimMsisdn') }}</label>
                <input v-model="phoneNumber" type="text" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ t('ussdSimSession') }}</label>
                <input v-model="sessionId" type="text" class="form-control font-monospace small" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ t('ussdSimCumulative') }}</label>
                <input v-model="cumulativeText" type="text" class="form-control font-monospace small" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ t('status') }}</label>
                <input :value="lastState" type="text" class="form-control" readonly />
              </div>
            </div>
            <p class="small text-muted mt-3 mb-0">{{ t('ussdSimHelp') }}</p>
          </div>
        </div>

        <div class="card cm-card mb-3">
          <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('ussdSimHistory') }}</h6></div>
          <div class="list-group list-group-flush ussd-history">
            <div v-for="(step, idx) in history" :key="idx" class="list-group-item">
              <div class="small text-muted">{{ step.input || '—' }} → {{ step.state }}</div>
              <pre class="small mb-0 ussd-history__msg">{{ step.message }}</pre>
            </div>
            <div v-if="!history.length" class="list-group-item text-muted">{{ t('ussdSimNoHistory') }}</div>
          </div>
        </div>

        <div v-if="integration" class="card cm-card">
          <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('ussdSimIntegration') }}</h6></div>
          <div class="card-body small">
            <p><strong>{{ t('ussdSimCallback') }}</strong><br><code>{{ integration.production_callback }}</code></p>
            <p class="mb-0"><strong>{{ t('ussdSimShortCode') }}</strong> {{ integration.short_code }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()

const phoneNumber = ref('+25377000001')
const sessionId = ref('')
const cumulativeText = ref('')
const pendingInput = ref('')
const screenText = ref('')
const lastState = ref('—')
const busy = ref(false)
const error = ref('')
const history = ref([])
const integration = ref(null)
const sessionActive = ref(true)
const shortCode = ref('*826#')

const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#']

function newSessionId() {
  return `SIM-${crypto.randomUUID()}`
}

onMounted(async () => {
  sessionId.value = newSessionId()
  await loadSchema()
  await dialFresh()
})

async function loadSchema() {
  try {
    const { data } = await http.get('/channels/ussd/schema')
    shortCode.value = data.short_code || data.integration?.short_code || '*826#'
    integration.value = data.integration || data
  } catch {
    integration.value = null
  }
}

async function dialFresh() {
  cumulativeText.value = ''
  pendingInput.value = ''
  history.value = []
  sessionActive.value = true
  await invoke('', true)
}

async function resetSession() {
  sessionId.value = newSessionId()
  await dialFresh()
}

function appendKey(k) {
  pendingInput.value += k
}

async function sendInput() {
  if (!sessionActive.value) {
    await resetSession()
    if (pendingInput.value) {
      await invoke(pendingInput.value, false)
      pendingInput.value = ''
    }
    return
  }
  const part = pendingInput.value.trim()
  if (!part) return
  await invoke(part, false)
  pendingInput.value = ''
}

async function invoke(inputPart, newSession) {
  busy.value = true
  error.value = ''
  try {
    if (inputPart !== '') {
      cumulativeText.value = cumulativeText.value ? `${cumulativeText.value}*${inputPart}` : inputPart
    }
    const { data } = await http.post('/channels/ussd/simulate', {
      session_id: sessionId.value,
      phone_number: phoneNumber.value,
      text: newSession ? '' : cumulativeText.value,
      new_session: newSession,
      service_code: shortCode.value,
    })
    sessionId.value = data.session_id || sessionId.value
    screenText.value = data.plain_message || data.message || ''
    lastState.value = data.state || '—'
    sessionActive.value = data.session_active !== false
    history.value.push({
      input: inputPart || (newSession ? t('ussdSimDial') : ''),
      state: data.state,
      message: screenText.value,
    })
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('ussdSimError')
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.ussd-phone {
  max-width: 320px;
  border: 2px solid #1e293b;
  border-radius: 24px;
  padding: 12px;
  background: #0f172a;
  color: #e2e8f0;
  box-shadow: 0 12px 40px rgba(15, 23, 42, 0.25);
}

.ussd-phone__header {
  text-align: center;
  font-weight: 600;
  font-size: 0.85rem;
  padding-bottom: 8px;
  color: #94a3b8;
}

.ussd-phone__screen {
  min-height: 200px;
  background: #1e293b;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 12px;
  position: relative;
}

.ussd-phone__text {
  white-space: pre-wrap;
  font-family: ui-monospace, monospace;
  font-size: 0.78rem;
  margin: 0;
  color: #f8fafc;
}

.ussd-phone__keypad {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
  margin-top: 10px;
}

.ussd-history__msg {
  white-space: pre-wrap;
  font-family: ui-monospace, monospace;
}
</style>

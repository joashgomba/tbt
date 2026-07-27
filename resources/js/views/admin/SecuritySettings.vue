<template>
  <div class="uam-enterprise cm-enterprise">
    <section class="uam-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4">
        <h1 class="hero-title mb-2">{{ t('mfaEnrollTitle') }}</h1>
        <p class="hero-sub mb-0">{{ t('mfaEnrollSubtitle') }}</p>
      </div>
    </section>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-if="message" class="alert alert-success">{{ message }}</div>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card uam-card">
          <div class="card-body">
            <h6>{{ t('mfaStatusTitle') }}</h6>
            <p class="small text-muted">{{ status.required ? t('mfaRequiredNote') : t('mfaOptionalNote') }}</p>
            <div class="mb-3">
              <span class="badge" :class="status.enrolled ? 'bg-success' : 'bg-warning'">
                {{ status.enrolled ? t('mfaEnrolledBadge') : t('mfaNotEnrolledBadge') }}
              </span>
            </div>
            <ul v-if="status.methods?.length" class="list-group list-group-flush">
              <li v-for="m in status.methods" :key="m.id" class="list-group-item px-0 d-flex justify-content-between">
                <span><i :class="m.method === 'email' ? 'ti ti-mail' : 'ti ti-device-mobile'" class="me-1"></i>{{ m.method }}</span>
                <span class="small text-muted">{{ m.is_verified ? t('verified') : t('pending') }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card uam-card">
          <div class="card-body">
            <h6>{{ step === 'choose' ? t('mfaChooseMethod') : t('mfaEnterCode') }}</h6>

            <div v-if="step === 'choose'" class="row g-3">
              <div class="col-md-6">
                <button type="button" class="btn btn-outline-primary w-100 py-4" @click="startEnroll('email')">
                  <i class="ti ti-mail fs-3 d-block mb-2"></i>{{ t('mfaMethodEmail') }}
                </button>
              </div>
              <div class="col-md-6">
                <button type="button" class="btn btn-outline-primary w-100 py-4" :disabled="!hasPhone" @click="startEnroll('sms')">
                  <i class="ti ti-device-mobile fs-3 d-block mb-2"></i>{{ t('mfaMethodSms') }}
                </button>
              </div>
            </div>

            <div v-else>
              <p class="small text-muted">{{ t('mfaCodeSentHint') }}</p>
              <input v-model="code" type="text" maxlength="6" class="form-control form-control-lg text-center mb-3" inputmode="numeric" />
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" :disabled="loading" @click="verifyEnroll">{{ t('mfaVerifyBtn') }}</button>
                <button type="button" class="btn btn-outline-secondary" @click="step = 'choose'">{{ t('back') }}</button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="status.enrolled" class="mt-3">
          <RouterLink class="btn btn-success" :to="{ name: 'admin-dashboard' }">{{ t('mfaContinueToApp') }}</RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { http } from '@/api/http'
import { fetchAuthSession } from '@/auth/session'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()
const status = ref({ required: false, enrolled: false, methods: [] })
const step = ref('choose')
const challengeId = ref(null)
const code = ref('')
const loading = ref(false)
const error = ref('')
const message = ref('')
const me = ref(null)

const hasPhone = computed(() => !!me.value?.phone_number)

async function load() {
  const [mfaRes, meRes] = await Promise.all([
    http.get('/auth/mfa/status'),
    http.get('/auth/me'),
  ])
  status.value = mfaRes.data
  me.value = meRes.data.user
}

async function startEnroll(method) {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.post('/auth/mfa/enroll', { method })
    challengeId.value = data.challenge_id
    step.value = 'verify'
    message.value = t('mfaCodeSentHint')
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  } finally {
    loading.value = false
  }
}

async function verifyEnroll() {
  loading.value = true
  error.value = ''
  try {
    await http.post('/auth/mfa/enroll/verify', { challenge_id: challengeId.value, code: code.value })
    message.value = t('mfaEnrollSuccess')
    await fetchAuthSession()
    await load()
    step.value = 'choose'
  } catch (e) {
    error.value = e?.response?.data?.message || t('mfaInvalidCode')
  } finally {
    loading.value = false
  }
}

onMounted(() => load().catch(() => { error.value = t('uamLoadError') }))
</script>

<template>
  <div class="admin-login">
    <main class="admin-login__panel w-100">
      <div class="admin-login__panel-inner mx-auto" style="max-width: 420px">
        <div class="admin-login__card">
          <header class="admin-login__card-header">
            <h1 class="admin-login__card-title">{{ t('mfaVerifyTitle') }}</h1>
            <p class="admin-login__card-subtitle">{{ t('mfaVerifySubtitle') }}</p>
            <p v-if="channel" class="small text-muted mb-0">{{ t('mfaSentTo') }}: {{ channel }}</p>
          </header>

          <form @submit.prevent="submit">
            <div class="admin-login__field">
              <label for="mfa-code">{{ t('mfaCodeLabel') }}</label>
              <input
                id="mfa-code"
                v-model="code"
                type="text"
                inputmode="numeric"
                maxlength="6"
                class="form-control form-control-lg text-center letter-spacing-2"
                autocomplete="one-time-code"
                required
              />
            </div>

            <button type="submit" class="admin-login__submit w-100" :disabled="loading">
              {{ loading ? t('verifying') : t('mfaVerifyBtn') }}
            </button>

            <div v-if="error" class="admin-login__error mt-3" role="alert">{{ error }}</div>
          </form>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'
import { fetchAuthSession, resolveAfterAdminLogin } from '@/auth/session'
import { useI18n } from '@/composables/useI18n'

const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const loading = ref(false)
const error = ref('')
const code = ref('')
const challengeId = ref(null)
const channel = ref('')

onMounted(() => {
  challengeId.value = sessionStorage.getItem('mfa_challenge_id')
  channel.value = sessionStorage.getItem('mfa_channel') || 'email'
  if (!challengeId.value) {
    router.replace({ name: 'login' })
  }
})

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const authContext = sessionStorage.getItem('mfa_auth_context') || 'admin'
    await http.post('/auth/mfa/verify', {
      challenge_id: Number(challengeId.value),
      code: code.value,
    }, {
      headers: { 'X-Auth-Context': authContext },
    })
    sessionStorage.removeItem('mfa_challenge_id')
    sessionStorage.removeItem('mfa_channel')
    sessionStorage.removeItem('mfa_auth_context')
    const user = await fetchAuthSession()
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    router.push(resolveAfterAdminLogin(user, redirect))
  } catch (e) {
    error.value = e?.response?.data?.message || t('mfaInvalidCode')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.letter-spacing-2 { letter-spacing: 0.35em; }
</style>

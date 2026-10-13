<template>
  <div class="admin-login admin-login--single">
    <select v-model="currentLocale" class="admin-login__locale" :aria-label="t('language')" @change="onLocaleChange">
      <option value="en">English</option>
      <option value="fr">Français</option>
    </select>

    <main class="admin-login__panel admin-login__panel--center">
      <div class="admin-login__card">
        <header class="admin-login__card-header">
          <h1 class="admin-login__card-title">{{ t('forgotPasswordTitle') }}</h1>
          <p class="admin-login__card-subtitle">{{ t('forgotPasswordIntro') }}</p>
        </header>

        <form @submit.prevent="submit" novalidate>
          <div class="admin-login__field">
            <label for="reset-email">{{ t('emailOrUsername') }}</label>
            <input id="reset-email" v-model="email" type="text" class="form-control" required autocomplete="username" />
          </div>
          <p v-if="message" class="text-success small">{{ message }}</p>
          <p v-if="error" class="text-danger small">{{ error }}</p>
          <button type="submit" class="admin-login__submit w-100" :disabled="loading">
            {{ loading ? t('loading') : t('sendResetLink') }}
          </button>
        </form>

        <p class="mt-3 mb-0 text-center">
          <RouterLink :to="{ name: 'login' }">{{ t('backToLogin') }}</RouterLink>
          ·
          <RouterLink :to="{ name: 'public-reporter-auth' }">{{ t('reporterAccount') }}</RouterLink>
        </p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const { t, locale, setLocale } = useI18n()
const currentLocale = ref(locale.value)
const email = ref('')
const loading = ref(false)
const message = ref('')
const error = ref('')

function onLocaleChange() {
  setLocale(currentLocale.value)
}

async function submit() {
  loading.value = true
  message.value = ''
  error.value = ''
  try {
    const { data } = await http.post('/auth/forgot-password', { email: email.value.trim() })
    message.value = data.message || t('forgotPasswordSent')
  } catch (e) {
    error.value = e?.response?.data?.message || t('forgotPasswordFailed')
  } finally {
    loading.value = false
  }
}
</script>

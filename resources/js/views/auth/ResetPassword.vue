<template>
  <div class="admin-login admin-login--single">
    <main class="admin-login__panel admin-login__panel--center">
      <div class="admin-login__card">
        <header class="admin-login__card-header">
          <h1 class="admin-login__card-title">{{ t('resetPasswordTitle') }}</h1>
          <p class="admin-login__card-subtitle">{{ t('resetPasswordIntro') }}</p>
        </header>

        <form @submit.prevent="submit" novalidate>
          <div class="admin-login__field">
            <label for="email">{{ t('email') }}</label>
            <input id="email" v-model="form.email" type="email" class="form-control" required readonly />
          </div>
          <div class="admin-login__field">
            <label for="password">{{ t('newPassword') }}</label>
            <input id="password" v-model="form.password" type="password" class="form-control" required minlength="8" autocomplete="new-password" />
          </div>
          <div class="admin-login__field">
            <label for="password_confirmation">{{ t('confirmPassword') }}</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="form-control" required minlength="8" autocomplete="new-password" />
          </div>
          <p v-if="message" class="text-success small">{{ message }}</p>
          <p v-if="error" class="text-danger small">{{ error }}</p>
          <button type="submit" class="admin-login__submit w-100" :disabled="loading">
            {{ loading ? t('loading') : t('resetPasswordAction') }}
          </button>
        </form>

        <p class="mt-3 mb-0 text-center">
          <RouterLink :to="{ name: 'login' }">{{ t('backToLogin') }}</RouterLink>
        </p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const loading = ref(false)
const message = ref('')
const error = ref('')
const form = reactive({
  token: '',
  email: '',
  password: '',
  password_confirmation: '',
})

onMounted(() => {
  form.token = String(route.query.token || '')
  form.email = String(route.query.email || '')
})

async function submit() {
  loading.value = true
  message.value = ''
  error.value = ''
  try {
    const { data } = await http.post('/auth/reset-password', { ...form })
    message.value = data.message || t('resetPasswordSuccess')
    setTimeout(() => router.push({ name: 'login' }), 1500)
  } catch (e) {
    error.value = e?.response?.data?.message || e?.response?.data?.errors?.email?.[0] || t('resetPasswordFailed')
  } finally {
    loading.value = false
  }
}
</script>

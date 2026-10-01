<template>
  <div class="admin-login">
    <select
      v-model="currentLocale"
      class="admin-login__locale"
      :aria-label="t('language')"
      @change="onLocaleChange"
    >
      <option value="en">English</option>
      <option value="fr">Français</option>
    </select>

    <aside class="admin-login__brand" aria-hidden="true">
      <div
        class="admin-login__brand-bg"
        style="background-image: url('/assets/img/slides/slide2.png')"
      ></div>
      <div class="admin-login__brand-overlay"></div>

      <div class="admin-login__brand-content">
        <RouterLink class="admin-login__logo" :to="{ name: 'public-home' }" tabindex="-1">
          <span class="admin-login__logo-mark"><i class="ti ti-world"></i></span>
          <span>
            <p class="admin-login__logo-title">{{ t('appName') }}</p>
            <p class="admin-login__logo-sub">{{ t('appSubtitle') }}</p>
          </span>
        </RouterLink>

        <h2 class="admin-login__headline">{{ t('adminLoginHeadline') }}</h2>
        <p class="admin-login__lead">{{ t('adminLoginLead') }}</p>

        <ul class="admin-login__features">
          <li v-for="item in features" :key="item" class="admin-login__feature">
            <i class="ti ti-circle-check" aria-hidden="true"></i>
            <span>{{ item }}</span>
          </li>
        </ul>
      </div>

      <p class="admin-login__brand-footer">© {{ year }} {{ t('appSubtitle') }}</p>
    </aside>

    <main class="admin-login__panel">
      <div class="admin-login__panel-inner">
        <div class="admin-login__mobile-brand">
          <span class="admin-login__mobile-mark" aria-hidden="true"><i class="ti ti-world"></i></span>
          <span>
            <p class="admin-login__mobile-title">{{ t('appName') }}</p>
            <p class="admin-login__mobile-sub">{{ t('adminPortal') }}</p>
          </span>
        </div>

        <div class="admin-login__card">
          <header class="admin-login__card-header">
            <h1 class="admin-login__card-title">{{ t('welcomeBack') }}</h1>
            <p class="admin-login__card-subtitle">{{ t('signIn') }}</p>
          </header>

          <form @submit.prevent="submit" novalidate>
            <div class="admin-login__field">
              <label for="login-email">{{ t('emailOrUsername') }}</label>
              <div class="admin-login__input-wrap">
                <i class="ti ti-mail input-icon" aria-hidden="true"></i>
                <input
                  id="login-email"
                  v-model="form.email"
                  type="text"
                  autocomplete="username"
                  required
                  autofocus
                  :aria-invalid="!!error"
                  :disabled="loading"
                />
              </div>
            </div>

            <div class="admin-login__field">
              <label for="login-password">{{ t('password') }}</label>
              <div class="admin-login__input-wrap">
                <i class="ti ti-lock input-icon" aria-hidden="true"></i>
                <input
                  id="login-password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  :aria-invalid="!!error"
                  :disabled="loading"
                />
                <button
                  type="button"
                  class="admin-login__toggle-pw"
                  :aria-label="showPassword ? t('hidePassword') : t('showPassword')"
                  @click="showPassword = !showPassword"
                >
                  <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <button type="submit" class="admin-login__submit" :disabled="loading" :aria-busy="loading">
              <i v-if="loading" class="ti ti-loader-2" aria-hidden="true"></i>
              <i v-else class="ti ti-login" aria-hidden="true"></i>
              {{ loading ? t('signingIn') : t('login') }}
            </button>

            <div v-if="error" class="admin-login__error" role="alert">
              <i class="ti ti-alert-circle" aria-hidden="true"></i>
              <span>{{ error }}</span>
            </div>

            <p class="admin-login__secure">
              <i class="ti ti-shield-lock" aria-hidden="true"></i>
              {{ t('loginSecureNote') }}
            </p>
          </form>
        </div>

        <p class="admin-login__footer">
          <RouterLink :to="{ name: 'public-home' }">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            {{ t('backToPublicPortal') }}
          </RouterLink>
        </p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { login, resolveAfterAdminLogin } from '@/auth/session'
import { useI18n } from '@/composables/useI18n'

const router = useRouter()
const route = useRoute()
const { t, locale, setLocale } = useI18n()
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const form = reactive({ email: '', password: '' })
const currentLocale = ref(locale.value)
const year = new Date().getFullYear()

watch(locale, (v) => { currentLocale.value = v })

const features = computed(() => [
  t('adminLoginFeature1'),
  t('adminLoginFeature2'),
  t('adminLoginFeature3'),
])

function onLocaleChange() {
  setLocale(currentLocale.value)
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const result = await login(form, { context: 'admin' })
    if (result?.mfaRequired) {
      router.push({ name: 'admin-mfa-verify', query: { redirect: typeof route.query.redirect === 'string' ? route.query.redirect : '/admin' } })
      return
    }
    if (result?.mfa_enrollment_required) {
      router.push({ name: 'admin-security' })
      return
    }
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    router.push(resolveAfterAdminLogin(result, redirect))
  } catch (e) {
    error.value = e?.response?.data?.message
      || e?.response?.data?.errors?.email?.[0]
      || t('invalidCredentials')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.admin-login__submit .ti-loader-2 {
  animation: spin 0.8s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
  .admin-login__submit .ti-loader-2 {
    animation: none;
  }
}
</style>

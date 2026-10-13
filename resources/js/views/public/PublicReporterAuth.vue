<template>
  <div class="portal-wizard">
    <div class="portal-wizard__header">
      <h1 class="portal-wizard__title">{{ t('reporterAccountTitle') }}</h1>
      <p class="portal-wizard__intro">{{ t('reporterAccountIntro') }}</p>
    </div>

    <div class="portal-wizard__body">
      <div class="portal-alert portal-alert--info mb-4" role="note">
        <i class="ti ti-info-circle me-1" aria-hidden="true"></i>
        {{ t('reporterPrivacyNote') }}
      </div>

      <div class="portal-reporter-tabs" role="tablist" :aria-label="t('reporterAccountTitle')">
        <button
          type="button"
          role="tab"
          class="portal-reporter-tabs__btn"
          :class="{ 'portal-reporter-tabs__btn--active': mode === 'login' }"
          :aria-selected="mode === 'login'"
          @click="mode = 'login'"
        >
          {{ t('reporterSignIn') }}
        </button>
        <button
          type="button"
          role="tab"
          class="portal-reporter-tabs__btn"
          :class="{ 'portal-reporter-tabs__btn--active': mode === 'register' }"
          :aria-selected="mode === 'register'"
          @click="mode = 'register'"
        >
          {{ t('reporterCreateAccount') }}
        </button>
      </div>

      <div v-if="error" class="portal-alert portal-alert--error mt-3" role="alert">{{ error }}</div>

      <form v-if="mode === 'login'" class="portal-form-grid mt-4" @submit.prevent="submitLogin" novalidate>
        <div class="portal-field">
          <label for="login-email">{{ t('email') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="login-email" v-model="loginForm.email" type="email" autocomplete="username" required />
        </div>
        <div class="portal-field">
          <label for="login-password">{{ t('password') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="login-password" v-model="loginForm.password" type="password" autocomplete="current-password" required />
          <p class="portal-field__hint mt-2 mb-0">
            <RouterLink :to="{ name: 'forgot-password' }">{{ t('forgotPassword') }}</RouterLink>
          </p>
        </div>
        <div class="portal-field portal-field--full">
          <button type="submit" class="portal-btn portal-btn--accent" :disabled="loading" :aria-busy="loading">
            {{ loading ? t('loading') : t('reporterSignIn') }}
          </button>
        </div>
      </form>

      <form v-else class="portal-form-grid portal-form-grid--2 mt-4" @submit.prevent="submitRegister" novalidate>
        <div class="portal-field portal-field--full">
          <label for="reg-name">{{ t('fullName') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="reg-name" v-model="registerForm.full_name" type="text" autocomplete="name" required />
          <p v-if="fieldErrors.full_name" class="portal-field__error">{{ fieldErrors.full_name }}</p>
        </div>
        <div class="portal-field">
          <label for="reg-email">{{ t('email') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="reg-email" v-model="registerForm.email" type="email" autocomplete="email" required />
          <p v-if="fieldErrors.email" class="portal-field__error">{{ fieldErrors.email }}</p>
        </div>
        <div class="portal-field">
          <label for="reg-phone">{{ t('reporterPhone') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="reg-phone" v-model="registerForm.phone_number" type="tel" autocomplete="tel" required />
          <p v-if="fieldErrors.phone_number" class="portal-field__error">{{ fieldErrors.phone_number }}</p>
        </div>
        <div class="portal-field">
          <label for="reg-org">{{ t('organizationName') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="reg-org" v-model="registerForm.organization_name" type="text" autocomplete="organization" required />
          <p v-if="fieldErrors.organization_name" class="portal-field__error">{{ fieldErrors.organization_name }}</p>
        </div>
        <div class="portal-field">
          <label for="reg-country">{{ t('country') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="reg-country" v-model="registerForm.country" type="text" autocomplete="country-name" required />
          <p v-if="fieldErrors.country" class="portal-field__error">{{ fieldErrors.country }}</p>
        </div>
        <div class="portal-field">
          <label for="reg-gender">{{ t('gender') }}</label>
          <select id="reg-gender" v-model="registerForm.gender">
            <option value="">{{ t('selectOption') }}</option>
            <option value="M">{{ t('genderM') }}</option>
            <option value="F">{{ t('genderF') }}</option>
            <option value="NB">{{ t('genderNB') }}</option>
            <option value="NA">{{ t('genderNA') }}</option>
          </select>
        </div>
        <div class="portal-field">
          <label for="reg-lang">{{ t('language') }}</label>
          <select id="reg-lang" v-model="registerForm.preferred_language">
            <option value="en">English</option>
            <option value="fr">Français</option>
          </select>
        </div>
        <div class="portal-field">
          <label for="reg-password">{{ t('password') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="reg-password" v-model="registerForm.password" type="password" autocomplete="new-password" required minlength="8" />
          <p v-if="fieldErrors.password" class="portal-field__error">{{ fieldErrors.password }}</p>
        </div>
        <div class="portal-field">
          <label for="reg-password-confirm">{{ t('confirmPassword') }} <span class="required" aria-hidden="true">*</span></label>
          <input id="reg-password-confirm" v-model="registerForm.password_confirmation" type="password" autocomplete="new-password" required />
        </div>
        <div class="portal-field portal-field--full">
          <label class="portal-checkbox">
            <input v-model="registerForm.accept_terms" type="checkbox" required />
            <span>{{ t('reporterAcceptTerms') }}</span>
          </label>
          <p v-if="fieldErrors.accept_terms" class="portal-field__error">{{ fieldErrors.accept_terms }}</p>
        </div>
        <div class="portal-field portal-field--full">
          <button type="submit" class="portal-btn portal-btn--accent" :disabled="loading" :aria-busy="loading">
            {{ loading ? t('loading') : t('reporterCreateAccount') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fetchAuthSession, hasPermission, login, registerReporter } from '@/auth/session'
import { useI18n } from '@/composables/useI18n'

const router = useRouter()
const route = useRoute()
const { t, locale } = useI18n()

const mode = ref(route.query.mode === 'register' ? 'register' : 'login')
const loading = ref(false)
const error = ref('')
const fieldErrors = reactive({})

const loginForm = reactive({ email: '', password: '' })
const registerForm = reactive({
  full_name: '',
  email: '',
  phone_number: '',
  organization_name: '',
  country: '',
  gender: '',
  preferred_language: locale.value || 'en',
  password: '',
  password_confirmation: '',
  accept_terms: false,
})

watch(() => route.query.mode, (v) => {
  if (v === 'register') mode.value = 'register'
})

onMounted(async () => {
  if (route.query.signedOut === '1') {
    await fetchAuthSession()
    return
  }

  const user = await fetchAuthSession()
  if (user && hasPermission('complaints.submit')) {
    await router.replace(redirectTarget())
  }
})

function redirectTarget() {
  const redirect = route.query.redirect
  if (typeof redirect === 'string' && redirect.startsWith('/')) {
    return redirect
  }
  return { name: 'public-report' }
}

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
}

function applyValidationErrors(errors = {}) {
  clearFieldErrors()
  Object.entries(errors).forEach(([key, messages]) => {
    fieldErrors[key] = Array.isArray(messages) ? messages[0] : messages
  })
}

async function submitLogin() {
  loading.value = true
  error.value = ''
  clearFieldErrors()
  try {
    const result = await login(loginForm, { context: 'portal' })
    if (result?.mfaRequired) {
      error.value = t('reporterMfaNotSupported')
      return
    }
    if (!hasPermission('complaints.submit')) {
      error.value = t('reporterStaffLoginHint')
      return
    }
    await router.push(redirectTarget())
  } catch (e) {
    error.value = e?.response?.data?.message
      || e?.response?.data?.errors?.email?.[0]
      || t('invalidCredentials')
  } finally {
    loading.value = false
  }
}

async function submitRegister() {
  loading.value = true
  error.value = ''
  clearFieldErrors()
  try {
    await registerReporter({
      ...registerForm,
      accept_terms: registerForm.accept_terms ? 1 : 0,
    })
    await router.push(redirectTarget())
  } catch (e) {
    if (e?.response?.status === 422) {
      applyValidationErrors(e.response.data?.errors)
      error.value = e.response.data?.message || t('stepValidationError')
    } else {
      error.value = e?.response?.data?.message || t('registrationFailed')
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.portal-reporter-tabs {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.portal-reporter-tabs__btn {
  flex: 1;
  min-width: 10rem;
  padding: 0.65rem 1rem;
  border: 1px solid var(--portal-border, #d0d7de);
  border-radius: 0.5rem;
  background: #fff;
  font-weight: 600;
  cursor: pointer;
}

.portal-reporter-tabs__btn--active {
  background: var(--portal-accent, #0d6efd);
  border-color: var(--portal-accent, #0d6efd);
  color: #fff;
}

.portal-field--full {
  grid-column: 1 / -1;
}

.portal-checkbox {
  display: flex;
  gap: 0.5rem;
  align-items: flex-start;
  cursor: pointer;
}
</style>

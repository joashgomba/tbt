<template>
  <div class="portal-wizard">
    <div class="portal-wizard__header">
      <h1 class="portal-wizard__title">{{ t('reportBarrier') }}</h1>
      <p class="portal-wizard__intro">{{ t('reportIntro') }}</p>
      <PublicStepIndicator :steps="wizardSteps" :current="step" :aria-label="`${t('step')} ${step} ${t('of')} 4`" />
    </div>

    <form @submit.prevent="onSubmit" novalidate>
      <div class="portal-wizard__body">
        <div v-if="reporter" class="portal-reporter-account mb-4" role="status">
          <div>
            <p class="portal-reporter-account__label">{{ t('signedInAs') }}</p>
            <p class="portal-reporter-account__name">{{ reporter.name }}</p>
            <p class="portal-reporter-account__meta">
              <span v-if="reporter.organization_name">{{ reporter.organization_name }}</span>
              <span v-if="reporter.organization_name && reporter.email"> · </span>
              <span>{{ reporter.email }}</span>
            </p>
          </div>
          <button type="button" class="portal-btn portal-btn--outline portal-btn--sm" :disabled="signingOut" @click="signOut">
            {{ signingOut ? t('loading') : t('signOut') }}
          </button>
        </div>

        <div v-if="stepError" class="portal-alert portal-alert--error" role="alert">
          {{ stepError }}
        </div>

        <!-- Step 1: Describe -->
        <fieldset v-show="step === 1" class="border-0 p-0 m-0">
          <legend class="visually-hidden">{{ t('stepDescribe') }}</legend>
          <div class="portal-form-grid">
            <div class="portal-field">
              <label for="title">{{ t('title') }}</label>
              <p id="title-hint" class="portal-field__hint">{{ t('titleOptionalHint') }}</p>
              <input
                id="title"
                v-model="form.title"
                type="text"
                maxlength="255"
                :aria-invalid="!!errors.title"
                aria-describedby="title-hint title-error"
                autocomplete="off"
              />
              <p v-if="errors.title" id="title-error" class="portal-field__error">{{ errors.title }}</p>
            </div>
            <div class="portal-field">
              <label for="description">{{ t('description') }} <span class="required" aria-hidden="true">*</span></label>
              <p id="desc-hint" class="portal-field__hint">{{ t('descriptionHint') }}</p>
              <textarea
                id="description"
                v-model="form.description"
                rows="6"
                :aria-invalid="!!errors.description"
                aria-describedby="desc-hint desc-error"
              ></textarea>
              <p v-if="errors.description" id="desc-error" class="portal-field__error">{{ errors.description }}</p>
            </div>
            <div class="portal-field portal-form-grid--2-span">
              <label for="occurrence_date">{{ t('occurrenceDate') }} <span class="required" aria-hidden="true">*</span></label>
              <p id="occurrence-hint" class="portal-field__hint">{{ t('occurrenceDateHint') }}</p>
              <input
                id="occurrence_date"
                v-model="form.occurrence_date"
                type="date"
                :max="todayIso"
                :aria-invalid="!!errors.occurrence_date"
                aria-describedby="occurrence-hint occurrence-error"
              />
              <p v-if="errors.occurrence_date" id="occurrence-error" class="portal-field__error">{{ errors.occurrence_date }}</p>
            </div>
            <div class="portal-field portal-form-grid--2-span">
              <label for="country_affected">{{ t('countryAffected') }} <span class="required" aria-hidden="true">*</span></label>
              <p id="affected-hint" class="portal-field__hint">{{ t('countryAffectedHint') }}</p>
              <select
                id="country_affected"
                v-model="form.country_affected"
                :aria-invalid="!!errors.country_affected"
                aria-describedby="affected-hint affected-error"
              >
                <option value="">{{ t('selectOption') }}</option>
                <option v-for="c in lookups.countries" :key="c.code" :value="c.name_en">
                  {{ localizedCountry(c) }}
                </option>
              </select>
              <p v-if="errors.country_affected" id="affected-error" class="portal-field__error">{{ errors.country_affected }}</p>
            </div>
            <div class="portal-field">
              <label for="category">{{ t('category') }} <span class="required" aria-hidden="true">*</span></label>
              <p id="cat-hint" class="portal-field__hint">{{ t('categoryHint') }}</p>
              <select
                id="category"
                v-model="form.ntb_category_id"
                :aria-invalid="!!errors.ntb_category_id"
                aria-describedby="cat-hint cat-error"
              >
                <option value="">{{ t('selectOption') }}</option>
                <option v-for="c in lookups.categories" :key="c.id" :value="c.id">
                  {{ localizedName(c) }} ({{ c.code }})
                </option>
              </select>
              <p v-if="errors.ntb_category_id" id="cat-error" class="portal-field__error">{{ errors.ntb_category_id }}</p>
            </div>
          </div>
        </fieldset>

        <!-- Step 2: Location -->
        <fieldset v-show="step === 2" class="border-0 p-0 m-0">
          <legend class="visually-hidden">{{ t('stepLocation') }}</legend>
          <div class="portal-form-grid portal-form-grid--2">
            <div class="portal-field">
              <label for="corridor">{{ t('corridor') }} <span class="required" aria-hidden="true">*</span></label>
              <p id="corridor-hint" class="portal-field__hint">{{ t('corridorHint') }}</p>
              <select
                id="corridor"
                v-model="form.corridor_id"
                :aria-invalid="!!errors.corridor_id"
                aria-describedby="corridor-hint corridor-error"
              >
                <option value="">{{ t('selectOption') }}</option>
                <option v-for="c in lookups.corridors" :key="c.id" :value="c.id">{{ localizedName(c) }}</option>
              </select>
              <p v-if="errors.corridor_id" id="corridor-error" class="portal-field__error">{{ errors.corridor_id }}</p>
            </div>
            <div class="portal-field">
              <label for="location">{{ t('location') }} <span class="required" aria-hidden="true">*</span></label>
              <p id="loc-hint" class="portal-field__hint">{{ t('locationHint') }}</p>
              <select
                id="location"
                v-model="form.location_id"
                :aria-invalid="!!errors.location_id"
                aria-describedby="loc-hint loc-error"
              >
                <option value="">{{ t('selectOption') }}</option>
                <option v-for="l in filteredLocations" :key="l.id" :value="l.id">{{ localizedName(l) }}</option>
              </select>
              <p v-if="errors.location_id" id="loc-error" class="portal-field__error">{{ errors.location_id }}</p>
              <p v-else-if="form.corridor_id && !filteredLocations.length" class="portal-field__error">{{ t('locationNoneForCorridor') }}</p>
            </div>
            <div class="portal-field">
              <label for="origin">{{ t('originCountry') }} <span class="required" aria-hidden="true">*</span></label>
              <select
                id="origin"
                v-model="form.country_origin"
                :aria-invalid="!!errors.country_origin"
                aria-describedby="origin-error"
              >
                <option value="">{{ t('selectOption') }}</option>
                <option v-for="c in lookups.countries" :key="`o-${c.code}`" :value="c.name_en">
                  {{ localizedCountry(c) }}
                </option>
              </select>
              <p v-if="errors.country_origin" id="origin-error" class="portal-field__error">{{ errors.country_origin }}</p>
            </div>
            <div class="portal-field">
              <label for="destination">{{ t('destinationCountry') }} <span class="required" aria-hidden="true">*</span></label>
              <select
                id="destination"
                v-model="form.country_destination"
                :aria-invalid="!!errors.country_destination"
                aria-describedby="dest-error"
              >
                <option value="">{{ t('selectOption') }}</option>
                <option v-for="c in lookups.countries" :key="`d-${c.code}`" :value="c.name_en">
                  {{ localizedCountry(c) }}
                </option>
              </select>
              <p v-if="errors.country_destination" id="dest-error" class="portal-field__error">{{ errors.country_destination }}</p>
            </div>
          </div>
        </fieldset>

        <!-- Step 3: Product -->
        <fieldset v-show="step === 3" class="border-0 p-0 m-0">
          <legend class="visually-hidden">{{ t('stepProduct') }}</legend>
          <div class="portal-form-grid">
            <div class="portal-field">
              <label for="product_description">{{ t('productDescription') }}</label>
              <p id="product-desc-hint" class="portal-field__hint">{{ t('productDescriptionHint') }}</p>
              <textarea
                id="product_description"
                v-model="form.product_description"
                rows="4"
                :aria-invalid="!!errors.product_description"
                aria-describedby="product-desc-hint"
              ></textarea>
            </div>
            <div class="portal-field">
              <label for="product_country_origin">{{ t('productCountryOrigin') }}</label>
              <select id="product_country_origin" v-model="form.product_country_origin">
                <option value="">{{ t('selectOption') }}</option>
                <option v-for="c in lookups.countries" :key="`p-${c.code}`" :value="c.name_en">
                  {{ localizedCountry(c) }}
                </option>
              </select>
            </div>
            <div class="portal-field">
              <label for="product_value_currency">{{ t('productValue') }}</label>
              <p id="value-hint" class="portal-field__hint">{{ t('productValueHint') }}</p>
              <div class="portal-value-row">
                <select
                  id="product_value_currency"
                  v-model="form.product_value_currency"
                  :aria-invalid="!!errors.product_value_currency"
                  aria-describedby="value-hint"
                >
                  <option value="">{{ t('currency') }}</option>
                  <option v-for="cur in lookups.currencies" :key="cur" :value="cur">{{ cur }}</option>
                </select>
                <input
                  id="product_value_amount"
                  v-model="form.product_value_amount"
                  type="number"
                  min="0"
                  step="0.01"
                  inputmode="decimal"
                  :placeholder="t('amount')"
                  :aria-invalid="!!errors.product_value_amount"
                />
              </div>
              <p v-if="errors.product_value_currency" class="portal-field__error">{{ errors.product_value_currency }}</p>
              <p v-if="errors.product_value_amount" class="portal-field__error">{{ errors.product_value_amount }}</p>
            </div>
          </div>
        </fieldset>

        <!-- Step 4: Documents -->
        <fieldset v-show="step === 4" class="border-0 p-0 m-0">
          <legend class="visually-hidden">{{ t('stepDocuments') }}</legend>
          <div class="portal-field">
            <label for="attachments">{{ t('attachments') }}</label>
            <p id="attach-hint" class="portal-field__hint">{{ t('attachmentsHint') }}</p>
            <p class="portal-field__hint mb-2"><em>{{ t('attachmentsOptional') }}</em></p>
            <input
              id="attachments"
              ref="fileInput"
              type="file"
              class="form-control"
              multiple
              accept=".pdf,.jpg,.jpeg,.png,.docx,application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
              :aria-describedby="attachDescribedBy"
              :disabled="files.length >= MAX_ATTACHMENTS"
              @change="onFiles"
            />
            <p v-if="errors.attachments" id="attach-error" class="portal-field__error">{{ errors.attachments }}</p>
            <p v-if="files.length" class="portal-field__hint mt-2">{{ attachmentsCountLabel }}</p>
            <ul v-if="files.length" class="portal-file-list" aria-live="polite">
              <li v-for="(f, index) in files" :key="`${f.name}-${f.size}-${index}`">
                <span>
                  <i class="ti ti-paperclip" aria-hidden="true"></i>
                  {{ f.name }} ({{ formatSize(f.size) }})
                </span>
                <button type="button" class="portal-file-list__remove" :aria-label="formatMessage('removeAttachment', { name: f.name })" @click="removeFile(index)">
                  <i class="ti ti-x" aria-hidden="true"></i>
                </button>
              </li>
            </ul>
          </div>
          <div class="portal-privacy-note">
            <i class="ti ti-shield-lock" aria-hidden="true"></i>
            <span>{{ t('footerPrivacyText') }}</span>
          </div>
        </fieldset>
      </div>

      <div class="portal-wizard__footer">
        <button
          v-if="step > 1"
          type="button"
          class="portal-btn portal-btn--outline"
          @click="goBack"
        >
          <i class="ti ti-arrow-left" aria-hidden="true"></i>
          {{ t('previous') }}
        </button>
        <span v-else></span>

        <button
          v-if="step < 4"
          type="button"
          class="portal-btn portal-btn--accent"
          @click="goNext"
        >
          {{ t('next') }}
          <i class="ti ti-arrow-right" aria-hidden="true"></i>
        </button>
        <button
          v-else
          type="submit"
          class="portal-btn portal-btn--accent"
          :disabled="loading"
          :aria-busy="loading"
        >
          <i class="ti ti-send" aria-hidden="true"></i>
          {{ loading ? t('loading') : t('submit') }}
        </button>
      </div>

      <div v-if="submitError" class="portal-alert portal-alert--error mx-4 mb-4" role="alert">
        {{ submitError }}
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { http } from '@/api/http'
import { fetchAuthSession, getAuthUser, logout } from '@/auth/session'
import { useI18n } from '@/composables/useI18n'
import PublicStepIndicator from '@/components/public/PublicStepIndicator.vue'

const router = useRouter()
const { t, localizedName, locale } = useI18n()
const authUser = getAuthUser()
const reporter = computed(() => authUser.value)

const MAX_ATTACHMENTS = 5

const step = ref(1)
const signingOut = ref(false)
const loading = ref(false)
const stepError = ref('')
const submitError = ref('')
const files = ref([])
const fileInput = ref(null)
const errors = reactive({})
const lookups = reactive({ categories: [], corridors: [], locations: [], countries: [], currencies: [] })

const todayIso = new Date().toISOString().slice(0, 10)

const form = reactive({
  title: '',
  description: '',
  occurrence_date: '',
  country_affected: 'Djibouti',
  ntb_category_id: '',
  corridor_id: '',
  location_id: '',
  country_origin: 'Ethiopia',
  country_destination: 'Djibouti',
  product_description: '',
  product_country_origin: '',
  product_value_currency: '',
  product_value_amount: '',
})

const wizardSteps = computed(() => [
  { key: 'describe', label: t('stepDescribe') },
  { key: 'location', label: t('stepLocation') },
  { key: 'product', label: t('stepProduct') },
  { key: 'documents', label: t('stepDocuments') },
])

function localizedCountry(c) {
  if (!c) return ''
  return locale.value === 'fr' ? (c.name_fr || c.name_en) : (c.name_en || c.name_fr)
}

const filteredLocations = computed(() =>
  lookups.locations.filter((l) => !form.corridor_id || l.corridor_id == form.corridor_id),
)

const attachDescribedBy = computed(() => {
  const ids = ['attach-hint']
  if (errors.attachments) ids.push('attach-error')
  return ids.join(' ')
})

const attachmentsCountLabel = computed(() =>
  t('attachmentsCount')
    .replace('{count}', String(files.value.length))
    .replace('{max}', String(MAX_ATTACHMENTS)),
)

function formatMessage(key, params = {}) {
  let message = t(key)
  Object.entries(params).forEach(([param, value]) => {
    message = message.replace(`{${param}}`, String(value))
  })
  return message
}

watch(() => form.corridor_id, () => {
  if (form.location_id && !filteredLocations.value.some((l) => String(l.id) === String(form.location_id))) {
    form.location_id = ''
  }
})

onMounted(async () => {
  await fetchAuthSession()
  const user = authUser.value
  if (user?.country && !form.country_origin.trim()) {
    form.country_origin = user.country
  }
  const { data } = await http.get('/public/lookups')
  Object.assign(lookups, data)
})

async function signOut() {
  if (signingOut.value) return
  signingOut.value = true
  try {
    await logout()
    await router.replace({ name: 'public-reporter-auth', query: { redirect: '/report', signedOut: '1' } })
  } finally {
    signingOut.value = false
  }
}

function clearErrors() {
  Object.keys(errors).forEach((k) => delete errors[k])
  stepError.value = ''
}

function validateStep(current) {
  clearErrors()
  let valid = true

  if (current === 1) {
    if (!form.description.trim()) { errors.description = t('fieldRequired'); valid = false }
    if (!form.occurrence_date) { errors.occurrence_date = t('fieldRequired'); valid = false }
    if (!form.country_affected) { errors.country_affected = t('fieldRequired'); valid = false }
    if (!form.ntb_category_id) { errors.ntb_category_id = t('fieldRequired'); valid = false }
  }

  if (current === 2) {
    if (!form.corridor_id) { errors.corridor_id = t('fieldRequired'); valid = false }
    if (!form.location_id) {
      errors.location_id = filteredLocations.value.length
        ? t('fieldRequired')
        : t('locationNoneForCorridor')
      valid = false
    }
    if (!form.country_origin.trim()) { errors.country_origin = t('fieldRequired'); valid = false }
    if (!form.country_destination.trim()) { errors.country_destination = t('fieldRequired'); valid = false }
  }

  if (current === 3) {
    const hasAmount = form.product_value_amount !== '' && form.product_value_amount != null
    const hasCurrency = !!form.product_value_currency
    if (hasAmount && !hasCurrency) {
      errors.product_value_currency = t('fieldRequired')
      valid = false
    }
    if (hasCurrency && !hasAmount) {
      errors.product_value_amount = t('fieldRequired')
      valid = false
    }
  }

  if (current === 4 && files.value.length > MAX_ATTACHMENTS) {
    errors.attachments = formatMessage('attachmentsMaxError', { max: MAX_ATTACHMENTS })
    valid = false
  }

  if (!valid) stepError.value = t('stepValidationError')
  return valid
}

function goNext() {
  if (validateStep(step.value)) step.value++
}

function goBack() {
  clearErrors()
  step.value--
}

function onFiles(e) {
  const picked = Array.from(e.target.files || [])
  if (!picked.length) return

  const merged = [...files.value, ...picked]
  if (merged.length > MAX_ATTACHMENTS) {
    errors.attachments = formatMessage('attachmentsMaxError', { max: MAX_ATTACHMENTS })
    files.value = merged.slice(0, MAX_ATTACHMENTS)
  } else {
    delete errors.attachments
    files.value = merged
  }

  if (fileInput.value) fileInput.value.value = ''
}

function removeFile(index) {
  files.value.splice(index, 1)
  if (files.value.length <= MAX_ATTACHMENTS) {
    delete errors.attachments
  }
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

async function onSubmit() {
  if (!validateStep(1)) {
    step.value = 1
    return
  }
  if (!validateStep(2)) {
    step.value = 2
    return
  }
  if (!validateStep(3)) {
    step.value = 3
    return
  }
  if (!validateStep(4)) return

  loading.value = true
  submitError.value = ''
  try {
    const payload = new FormData()
    Object.entries(form).forEach(([k, v]) => {
      if (v !== '' && v != null) payload.append(k, v)
    })
    files.value.forEach((f) => payload.append('attachments[]', f))
    const { data } = await http.post('/public/complaints', payload)
    await router.push({
      name: 'public-report-confirmation',
      params: { code: data.complaint.complaint_code },
    })
  } catch (e) {
    submitError.value = e?.response?.data?.message || t('submissionFailed')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.portal-reporter-account {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-radius: 0.75rem;
  border: 1px solid var(--portal-border, #d0d7de);
  background: #f8fafc;
}

.portal-reporter-account__label {
  margin: 0;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}

.portal-reporter-account__name {
  margin: 0.15rem 0 0;
  font-weight: 700;
}

.portal-reporter-account__meta {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: #475569;
}

.portal-form-grid--2-span {
  grid-column: 1 / -1;
}

.portal-value-row {
  display: grid;
  grid-template-columns: minmax(7rem, 9rem) 1fr;
  gap: 0.75rem;
  align-items: center;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>

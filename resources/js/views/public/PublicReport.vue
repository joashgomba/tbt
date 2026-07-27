<template>
  <div class="portal-wizard">
    <div class="portal-wizard__header">
      <h1 class="portal-wizard__title">{{ t('reportBarrier') }}</h1>
      <p class="portal-wizard__intro">{{ t('reportIntro') }}</p>
      <PublicStepIndicator :steps="wizardSteps" :current="step" :aria-label="`${t('step')} ${step} ${t('of')} 4`" />
    </div>

    <form @submit.prevent="onSubmit" novalidate>
      <div class="portal-wizard__body">
        <div v-if="stepError" class="portal-alert portal-alert--error" role="alert">
          {{ stepError }}
        </div>

        <!-- Step 1: Describe -->
        <fieldset v-show="step === 1" class="border-0 p-0 m-0">
          <legend class="visually-hidden">{{ t('stepDescribe') }}</legend>
          <div class="portal-form-grid">
            <div class="portal-field">
              <label for="title">{{ t('title') }} <span class="required" aria-hidden="true">*</span></label>
              <p id="title-hint" class="portal-field__hint">{{ t('titleHint') }}</p>
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
              <label for="location">{{ t('location') }}</label>
              <p id="loc-hint" class="portal-field__hint">{{ t('locationHint') }}</p>
              <select id="location" v-model="form.location_id" required aria-describedby="loc-hint">
                <option value="">{{ t('selectOption') }}</option>
                <option v-for="l in filteredLocations" :key="l.id" :value="l.id">{{ localizedName(l) }}</option>
              </select>
            </div>
            <div class="portal-field">
              <label for="origin">{{ t('originCountry') }} <span class="required" aria-hidden="true">*</span></label>
              <input
                id="origin"
                v-model="form.country_origin"
                type="text"
                :aria-invalid="!!errors.country_origin"
                aria-describedby="origin-error"
              />
              <p v-if="errors.country_origin" id="origin-error" class="portal-field__error">{{ errors.country_origin }}</p>
            </div>
            <div class="portal-field">
              <label for="destination">{{ t('destinationCountry') }} <span class="required" aria-hidden="true">*</span></label>
              <input
                id="destination"
                v-model="form.country_destination"
                type="text"
                :aria-invalid="!!errors.country_destination"
                aria-describedby="dest-error"
              />
              <p v-if="errors.country_destination" id="dest-error" class="portal-field__error">{{ errors.country_destination }}</p>
            </div>
          </div>
        </fieldset>

        <!-- Step 3: Contact -->
        <fieldset v-show="step === 3" class="border-0 p-0 m-0">
          <legend class="visually-hidden">{{ t('stepContact') }}</legend>
          <div class="portal-alert portal-alert--info mb-3" role="note">
            <i class="ti ti-info-circle me-1" aria-hidden="true"></i>
            {{ t('contactPrivacyNote') }}
          </div>
          <div class="portal-form-grid portal-form-grid--2">
            <div class="portal-field">
              <label for="name">{{ t('reporterName') }}</label>
              <p id="name-hint" class="portal-field__hint">{{ t('reporterNameHint') }}</p>
              <input id="name" v-model="form.reporter_name" type="text" autocomplete="name" aria-describedby="name-hint" />
            </div>
            <div class="portal-field">
              <label for="email">{{ t('reporterEmail') }}</label>
              <p id="email-hint" class="portal-field__hint">{{ t('reporterEmailHint') }}</p>
              <input id="email" v-model="form.reporter_email" type="email" autocomplete="email" aria-describedby="email-hint" />
            </div>
            <div class="portal-field">
              <label for="phone">{{ t('reporterPhone') }}</label>
              <p id="phone-hint" class="portal-field__hint">{{ t('reporterPhoneHint') }}</p>
              <input id="phone" v-model="form.reporter_phone" type="tel" autocomplete="tel" aria-describedby="phone-hint" />
            </div>
            <div class="portal-field">
              <label for="gender">{{ t('gender') }}</label>
              <p id="gender-hint" class="portal-field__hint">{{ t('genderOptional') }}</p>
              <select id="gender" v-model="form.gender" aria-describedby="gender-hint">
                <option value="">{{ t('selectOption') }}</option>
                <option value="M">{{ t('genderM') }}</option>
                <option value="F">{{ t('genderF') }}</option>
                <option value="NB">{{ t('genderNB') }}</option>
                <option value="NA">{{ t('genderNA') }}</option>
              </select>
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
              type="file"
              class="form-control"
              multiple
              accept=".pdf,.jpg,.jpeg,.png,.docx,application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
              aria-describedby="attach-hint"
              @change="onFiles"
            />
            <ul v-if="files.length" class="portal-file-list" aria-live="polite">
              <li v-for="f in files" :key="f.name">
                <i class="ti ti-paperclip" aria-hidden="true"></i>
                {{ f.name }} ({{ formatSize(f.size) }})
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
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'
import PublicStepIndicator from '@/components/public/PublicStepIndicator.vue'

const router = useRouter()
const { t, localizedName } = useI18n()

const step = ref(1)
const loading = ref(false)
const stepError = ref('')
const submitError = ref('')
const files = ref([])
const errors = reactive({})
const lookups = reactive({ categories: [], corridors: [], locations: [] })

const form = reactive({
  title: '',
  description: '',
  ntb_category_id: '',
  corridor_id: '',
  location_id: '',
  country_origin: 'Ethiopia',
  country_destination: 'Djibouti',
  reporter_name: '',
  reporter_email: '',
  reporter_phone: '',
  gender: '',
})

const wizardSteps = computed(() => [
  { key: 'describe', label: t('stepDescribe') },
  { key: 'location', label: t('stepLocation') },
  { key: 'contact', label: t('stepContact') },
  { key: 'documents', label: t('stepDocuments') },
])

const filteredLocations = computed(() =>
  lookups.locations.filter((l) => !form.corridor_id || l.corridor_id == form.corridor_id),
)

onMounted(async () => {
  const { data } = await http.get('/public/lookups')
  Object.assign(lookups, data)
})

function clearErrors() {
  Object.keys(errors).forEach((k) => delete errors[k])
  stepError.value = ''
}

function validateStep(current) {
  clearErrors()
  let valid = true

  if (current === 1) {
    if (!form.title.trim()) { errors.title = t('fieldRequired'); valid = false }
    if (!form.description.trim()) { errors.description = t('fieldRequired'); valid = false }
    if (!form.ntb_category_id) { errors.ntb_category_id = t('fieldRequired'); valid = false }
  }

  if (current === 2) {
    if (!form.corridor_id) { errors.corridor_id = t('fieldRequired'); valid = false }
    if (!form.location_id) { errors.location_id = t('fieldRequired'); valid = false }
    if (!form.country_origin.trim()) { errors.country_origin = t('fieldRequired'); valid = false }
    if (!form.country_destination.trim()) { errors.country_destination = t('fieldRequired'); valid = false }
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
  files.value = Array.from(e.target.files || [])
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

async function onSubmit() {
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

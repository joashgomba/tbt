<template>
  <PublicHeroSlider />

  <section class="portal-categories-band" aria-labelledby="categories-title">
    <div class="portal-categories-band__inner">
      <header class="portal-categories-band__header">
        <p class="portal-categories-band__eyebrow">{{ t('categoriesEyebrow') }}</p>
        <h2 id="categories-title" class="portal-categories-band__title">{{ t('categoriesTitle') }}</h2>
        <p class="portal-categories-band__subtitle">{{ t('categoriesSubtitle') }}</p>
      </header>

      <div v-if="categoriesLoading" class="text-center py-4" style="color: rgba(248,250,252,0.6)">{{ t('loading') }}</div>
      <div v-else class="portal-categories-grid" role="list">
        <article
          v-for="cat in categoryCards"
          :key="cat.id"
          class="portal-category-card"
          role="listitem"
        >
          <div :class="['portal-category-card__icon', cat.iconClass]" aria-hidden="true">
            <i :class="cat.icon"></i>
          </div>
          <span class="portal-category-card__code">{{ cat.code }}</span>
          <h3 class="portal-category-card__title">{{ cat.name }}</h3>
          <p class="portal-category-card__text">{{ cat.description }}</p>
          <RouterLink
            class="portal-category-card__link"
            :to="{ name: 'public-transparency', query: { category_id: cat.id } }"
          >
            {{ t('readMore') }}
            <i class="ti ti-arrow-right" aria-hidden="true"></i>
          </RouterLink>
        </article>
      </div>

      <div class="portal-categories-band__footer">
        <RouterLink class="portal-btn portal-btn--secondary" :to="{ name: 'public-resources' }">
          {{ t('categoriesViewAll') }}
          <i class="ti ti-arrow-right ms-1" aria-hidden="true"></i>
        </RouterLink>
      </div>
    </div>
  </section>

  <section class="portal-section" aria-labelledby="how-title">
    <div class="portal-section__header">
      <h2 id="how-title" class="portal-section__title">{{ t('howItWorks') }}</h2>
      <p class="portal-section__subtitle">{{ t('howItWorksSubtitle') }}</p>
    </div>
    <div class="portal-steps-row">
      <div v-for="(item, index) in steps" :key="item.title" class="portal-step-card">
        <span class="portal-step-card__num" aria-hidden="true">{{ index + 1 }}</span>
        <p class="portal-step-card__title">{{ item.title }}</p>
        <p class="portal-step-card__text">{{ item.text }}</p>
      </div>
    </div>
  </section>

  <section class="portal-section" aria-labelledby="why-title">
    <div class="portal-section__header">
      <h2 id="why-title" class="portal-section__title">{{ t('whyUsePortal') }}</h2>
      <p class="portal-section__subtitle">{{ t('whyUsePortalSubtitle') }}</p>
    </div>
    <div class="portal-card-grid portal-card-grid--3">
      <article v-for="item in features" :key="item.title" class="portal-card">
        <div class="portal-card__icon" aria-hidden="true">
          <i :class="item.icon"></i>
        </div>
        <h3 class="portal-card__title">{{ item.title }}</h3>
        <p class="portal-card__text">{{ item.text }}</p>
      </article>
    </div>
  </section>

  <section class="portal-section" aria-labelledby="explore-title">
    <div class="portal-section__header">
      <h2 id="explore-title" class="portal-section__title">{{ t('browseReports') }}</h2>
      <p class="portal-section__subtitle">{{ t('browseReportsSubtitle') }}</p>
    </div>
    <div class="portal-home-links">
      <RouterLink class="portal-home-link-card" :to="{ name: 'public-transparency' }">
        <span class="portal-home-link-card__icon" aria-hidden="true"><i class="ti ti-chart-dots"></i></span>
        <span>
          <h3>{{ t('exploreTransparency') }}</h3>
          <p>{{ t('exploreTransparencyDesc') }}</p>
        </span>
      </RouterLink>
      <RouterLink class="portal-home-link-card" :to="{ name: 'public-resources' }">
        <span class="portal-home-link-card__icon" aria-hidden="true"><i class="ti ti-book-2"></i></span>
        <span>
          <h3>{{ t('exploreResources') }}</h3>
          <p>{{ t('exploreResourcesDesc') }}</p>
        </span>
      </RouterLink>
    </div>
  </section>

  <section class="portal-section" aria-labelledby="trust-title">
    <div class="portal-card" style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfeff 100%); border-color: #bbf7d0;">
      <div class="d-flex gap-3 align-items-start flex-wrap">
        <div class="portal-card__icon" style="background: #d1fae5; color: #047857;" aria-hidden="true">
          <i class="ti ti-certificate"></i>
        </div>
        <div style="flex: 1; min-width: 14rem;">
          <h2 id="trust-title" class="portal-section__title mb-2">{{ t('trustSectionTitle') }}</h2>
          <p class="portal-card__text" style="color: #334155; font-size: 1rem;">{{ t('trustSectionText') }}</p>
        </div>
      </div>
    </div>
  </section>

  <section class="portal-section" aria-labelledby="cta-title">
    <div class="portal-card text-center" style="padding: 2.5rem 1.5rem;">
      <h2 id="cta-title" class="portal-section__title">{{ t('getStarted') }}</h2>
      <p class="portal-section__subtitle mb-4">{{ t('getStartedText') }}</p>
      <div class="portal-hero__actions justify-content-center">
        <RouterLink class="portal-btn portal-btn--accent portal-btn--lg" :to="{ name: 'public-report' }">
          <i class="ti ti-arrow-right" aria-hidden="true"></i>
          {{ t('startReport') }}
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'
import PublicHeroSlider from '@/components/public/PublicHeroSlider.vue'

const { t, locale, localizedName } = useI18n()
const categories = ref([])
const categoriesLoading = ref(true)

const CATEGORY_META = {
  SPS: { icon: 'ti ti-plant-2', iconClass: 'portal-category-card__icon--sps', descKey: 'catSpsDesc' },
  TBT: { icon: 'ti ti-ruler-measure', iconClass: 'portal-category-card__icon--tbt', descKey: 'catTbtDesc' },
  CUST: { icon: 'ti ti-building-warehouse', iconClass: 'portal-category-card__icon--cust', descKey: 'catCustDesc' },
  LIC: { icon: 'ti ti-file-certificate', iconClass: 'portal-category-card__icon--lic', descKey: 'catLicDesc' },
  TAX: { icon: 'ti ti-receipt-tax', iconClass: 'portal-category-card__icon--tax', descKey: 'catTaxDesc' },
  TRN: { icon: 'ti ti-truck', iconClass: 'portal-category-card__icon--trn', descKey: 'catTrnDesc' },
}

onMounted(async () => {
  try {
    const { data } = await http.get('/public/lookups')
    categories.value = data.categories || []
  } finally {
    categoriesLoading.value = false
  }
})

const categoryCards = computed(() =>
  categories.value.map((cat) => {
    const meta = CATEGORY_META[cat.code] || {
      icon: cat.icon || 'ti ti-alert-circle',
      iconClass: 'portal-category-card__icon--default',
      descKey: null,
    }
    const dbDescription = locale.value === 'fr'
      ? (cat.description_fr || cat.description_en)
      : (cat.description_en || cat.description_fr)
    return {
      id: cat.id,
      code: cat.code,
      name: localizedName(cat),
      description: dbDescription || (meta.descKey ? t(meta.descKey) : localizedName(cat)),
      icon: cat.icon || meta.icon,
      iconClass: meta.iconClass,
    }
  }),
)

const steps = computed(() => [
  { title: t('stepReport'), text: t('stepReportDesc') },
  { title: t('stepSubmit'), text: t('stepSubmitDesc') },
  { title: t('stepReview'), text: t('stepReviewDesc') },
  { title: t('stepResolve'), text: t('stepResolveDesc') },
])

const features = computed(() => [
  { icon: 'ti ti-forms', title: t('featureMultichannel'), text: t('featureMultichannelDesc') },
  { icon: 'ti ti-timeline', title: t('featureTransparent'), text: t('featureTransparentDesc') },
  { icon: 'ti ti-building-community', title: t('featureCoordination'), text: t('featureCoordinationDesc') },
])
</script>

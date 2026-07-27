<template>
  <div>
    <section class="portal-section" aria-labelledby="resources-title">
      <div class="portal-section__header">
        <h1 id="resources-title" class="portal-section__title">{{ t('resourcesTitle') }}</h1>
        <p class="portal-section__subtitle">{{ t('resourcesSubtitle') }}</p>
      </div>
    </section>

    <section class="portal-section" aria-labelledby="definitions-title">
      <h2 id="definitions-title" class="portal-section__title">{{ t('definitionsTitle') }}</h2>
      <p class="portal-section__subtitle mb-3">{{ t('definitionsSubtitle') }}</p>
      <div class="portal-resource-grid portal-resource-grid--2">
        <article class="portal-definition-card">
          <h3>{{ t('ntbDefinitionTitle') }}</h3>
          <p>{{ t('ntbDefinitionText') }}</p>
          <div class="portal-example">
            <p class="portal-example__label">{{ t('exampleLabel') }}</p>
            <p class="mb-0 small">{{ t('ntbExample') }}</p>
          </div>
        </article>
        <article class="portal-definition-card portal-definition-card--tbt">
          <h3>{{ t('tbtDefinitionTitle') }}</h3>
          <p>{{ t('tbtDefinitionText') }}</p>
          <div class="portal-example">
            <p class="portal-example__label">{{ t('exampleLabel') }}</p>
            <p class="mb-0 small">{{ t('tbtExample') }}</p>
          </div>
        </article>
      </div>
    </section>

    <section class="portal-section" aria-labelledby="guide-title">
      <h2 id="guide-title" class="portal-section__title">{{ t('guideTitle') }}</h2>
      <p class="portal-section__subtitle mb-3">{{ t('guideSubtitle') }}</p>
      <div class="portal-steps-row">
        <div v-for="(step, i) in guideSteps" :key="i" class="portal-step-card">
          <span class="portal-step-card__num" aria-hidden="true">{{ i + 1 }}</span>
          <p class="portal-step-card__title">{{ step.title }}</p>
          <p class="portal-step-card__text">{{ step.text }}</p>
        </div>
      </div>
      <div class="text-center mt-4">
        <RouterLink class="portal-btn portal-btn--accent portal-btn--lg" :to="{ name: 'public-report' }">
          {{ t('startReport') }}
        </RouterLink>
      </div>
    </section>

    <section class="portal-section" aria-labelledby="examples-title">
      <h2 id="examples-title" class="portal-section__title">{{ t('examplesTitle') }}</h2>
      <div class="portal-resource-grid">
        <article v-for="ex in examples" :key="ex.title" class="portal-card">
          <span :class="`portal-status-chip portal-status-chip--${ex.type}`">{{ ex.badge }}</span>
          <h3 class="portal-card__title mt-2">{{ ex.title }}</h3>
          <p class="portal-card__text">{{ ex.text }}</p>
        </article>
      </div>
    </section>

    <section class="portal-section portal-faq" aria-labelledby="faq-title">
      <h2 id="faq-title" class="portal-section__title">{{ t('faqTitle') }}</h2>
      <details v-for="(item, i) in faqItems" :key="i">
        <summary>{{ item.q }}</summary>
        <div class="portal-faq__body">{{ item.a }}</div>
      </details>
    </section>

    <section class="portal-section" aria-labelledby="support-title">
      <div class="portal-resource-grid portal-resource-grid--2">
        <div class="portal-card">
          <h2 id="support-title" class="portal-card__title">{{ t('helpDeskTitle') }}</h2>
          <p class="portal-card__text mb-3">{{ t('helpDeskText') }}</p>
          <ul class="portal-link-list">
            <li><a href="mailto:focal@ntb.dj"><i class="ti ti-mail me-1"></i> focal@ntb.dj</a></li>
            <li><a href="tel:+25321300000"><i class="ti ti-phone me-1"></i> +253 21 30 00 00</a></li>
            <li><span class="text-muted"><i class="ti ti-clock me-1"></i> {{ t('helpDeskHours') }}</span></li>
          </ul>
        </div>
        <div class="portal-card">
          <h2 class="portal-card__title">{{ t('agreementsTitle') }}</h2>
          <p class="portal-card__text mb-3">{{ t('agreementsText') }}</p>
          <ul class="portal-link-list">
            <li v-for="link in agreementLinks" :key="link.url">
              <a :href="link.url" target="_blank" rel="noopener noreferrer">
                {{ link.label }} <i class="ti ti-external-link" aria-hidden="true"></i>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()

const guideSteps = computed(() => [
  { title: t('stepReport'), text: t('guideStep1') },
  { title: t('stepLocation'), text: t('guideStep2') },
  { title: t('stepContact'), text: t('guideStep3') },
  { title: t('stepDocuments'), text: t('guideStep4') },
])

const examples = computed(() => [
  { type: 'under_review', badge: t('exampleBadgeCustoms'), title: t('exampleCustomsTitle'), text: t('exampleCustomsText') },
  { type: 'received', badge: t('exampleBadgeSPS'), title: t('exampleSpsTitle'), text: t('exampleSpsText') },
  { type: 'resolved', badge: t('exampleBadgeLicence'), title: t('exampleLicenceTitle'), text: t('exampleLicenceText') },
])

const faqItems = computed(() => [
  { q: t('faq1q'), a: t('faq1a') },
  { q: t('faq2q'), a: t('faq2a') },
  { q: t('faq3q'), a: t('faq3a') },
  { q: t('faq4q'), a: t('faq4a') },
  { q: t('faq5q'), a: t('faq5a') },
])

const agreementLinks = computed(() => [
  { label: t('linkAfCFTA'), url: 'https://au.int/en/ti/cfta/about' },
  { label: t('linkComesa'), url: 'https://www.comesa.int/' },
  { label: t('linkWTO'), url: 'https://www.wto.org/english/tratop_e/tbt_e/tbt_e.htm' },
  { label: t('linkNationalEnquiry'), url: 'mailto:focal@ntb.dj' },
])
</script>

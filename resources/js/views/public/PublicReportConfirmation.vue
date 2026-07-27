<template>
  <div class="portal-wizard">
    <div class="portal-confirmation" role="status" aria-live="polite">
      <div class="portal-confirmation__icon" aria-hidden="true">
        <i class="ti ti-circle-check"></i>
      </div>
      <h1 class="portal-confirmation__title">{{ t('confirmationTitle') }}</h1>
      <p class="portal-confirmation__lead">{{ t('confirmationLead') }}</p>

      <p class="mb-2 fw-semibold">{{ t('yourReference') }}</p>
      <div class="portal-reference" tabindex="0" :aria-label="`${t('yourReference')}: ${code}`">
        {{ code }}
      </div>
      <button type="button" class="portal-btn portal-btn--outline mb-4" @click="copyReference">
        <i class="ti ti-copy" aria-hidden="true"></i>
        {{ copied ? t('copied') : t('copyReference') }}
      </button>

      <div class="portal-next-steps">
        <p class="portal-next-steps__title">{{ t('whatHappensNext') }}</p>
        <ol>
          <li v-for="(step, i) in nextSteps" :key="i">{{ step }}</li>
        </ol>
      </div>

      <div class="portal-hero__actions justify-content-center">
        <RouterLink class="portal-btn portal-btn--accent portal-btn--lg" :to="{ name: 'public-track', query: { code } }">
          <i class="ti ti-search" aria-hidden="true"></i>
          {{ t('trackCase') }}
        </RouterLink>
        <RouterLink class="portal-btn portal-btn--outline portal-btn--lg" :to="{ name: 'public-home' }">
          {{ t('backToHome') }}
        </RouterLink>
      </div>

      <div class="portal-privacy-note mt-4 text-start mx-auto" style="max-width: 28rem">
        <i class="ti ti-mail" aria-hidden="true"></i>
        <span>{{ t('confirmationEmailNote') }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from '@/composables/useI18n'

const route = useRoute()
const { t, locale } = useI18n()
const code = computed(() => route.params.code || '')
const copied = ref(false)

const nextSteps = computed(() =>
  locale.value === 'fr'
    ? [
        'Votre dossier est enregistré et en attente de validation.',
        'Un point focal national examinera votre signalement sous 2 jours ouvrables.',
        'Vous recevrez des mises à jour par courriel ou SMS si vous les avez fournis.',
        'Utilisez votre numéro de référence pour suivre l\'avancement à tout moment.',
      ]
    : [
        'Your case is logged and awaiting validation.',
        'A national focal point will review your report within 2 working days.',
        'You will receive updates by email or SMS if contact details were provided.',
        'Use your reference number to track progress at any time.',
      ],
)

async function copyReference() {
  try {
    await navigator.clipboard.writeText(code.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    /* clipboard unavailable */
  }
}
</script>

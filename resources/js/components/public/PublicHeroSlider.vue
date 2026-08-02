<template>
  <section
    class="portal-hero-slider"
    aria-labelledby="hero-title"
    @mouseenter="stopAutoplay"
    @mouseleave="startAutoplay"
    @focusin="stopAutoplay"
    @focusout="startAutoplay"
  >
    <div class="portal-hero-slider__media" aria-hidden="true">
      <div
        v-for="(slide, index) in slides"
        :key="slide.src"
        class="portal-hero-slider__slide"
        :class="{ 'portal-hero-slider__slide--active': index === current }"
        :style="{ backgroundImage: `url(${slide.src})` }"
      ></div>
    </div>

    <div class="portal-hero-slider__overlay" aria-hidden="true"></div>

    <div class="portal-hero-slider__inner">
      <div class="portal-hero-slider__content">
        <p class="portal-hero__eyebrow">
          <i class="ti ti-route" aria-hidden="true"></i>
          {{ t('homeEyebrow') }}
        </p>
        <h1 id="hero-title" class="portal-hero__title">{{ t('homeHeroTitle') }}</h1>
        <p class="portal-hero__lead">{{ t('homeHeroLead') }}</p>
        <div class="portal-hero__actions">
          <RouterLink class="portal-btn portal-btn--primary portal-btn--lg" :to="{ name: 'public-report' }">
            <i class="ti ti-flag" aria-hidden="true"></i>
            {{ t('startReport') }}
          </RouterLink>
          <RouterLink class="portal-btn portal-btn--secondary portal-btn--lg" :to="{ name: 'public-track' }">
            <i class="ti ti-search" aria-hidden="true"></i>
            {{ t('checkExisting') }}
          </RouterLink>
        </div>
      </div>
    </div>

    <div class="portal-hero-slider__ui">
      <button
        type="button"
        class="portal-hero-slider__arrow portal-hero-slider__arrow--prev"
        :aria-label="t('prevSlide')"
        @click="prev"
      >
        <i class="ti ti-chevron-left" aria-hidden="true"></i>
      </button>
      <button
        type="button"
        class="portal-hero-slider__arrow portal-hero-slider__arrow--next"
        :aria-label="t('nextSlide')"
        @click="next"
      >
        <i class="ti ti-chevron-right" aria-hidden="true"></i>
      </button>

      <div class="portal-hero-slider__dots" role="tablist" :aria-label="t('slideIndicators')">
        <button
          v-for="(slide, index) in slides"
          :key="slide.src"
          type="button"
          role="tab"
          class="portal-hero-slider__dot"
          :class="{ 'portal-hero-slider__dot--active': index === current }"
          :aria-selected="index === current"
          :aria-label="`${t('goToSlide')} ${index + 1}`"
          @click="goTo(index)"
        ></button>
      </div>
    </div>

    <p class="visually-hidden" aria-live="polite">{{ slideStatus }}</p>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from '@/composables/useI18n'

const { t, locale } = useI18n()

const slides = [
  { src: '/assets/img/slides/slide3.png' },
  { src: '/assets/img/slides/slide4.png' },
]

const current = ref(0)
const intervalMs = 6000
let timer = null
let reducedMotion = false

const slideStatus = computed(() => {
  const n = current.value + 1
  return locale.value === 'fr'
    ? `Diapositive ${n} sur ${slides.length}`
    : `Slide ${n} of ${slides.length}`
})

function next() {
  current.value = (current.value + 1) % slides.length
  restartAutoplay()
}

function prev() {
  current.value = (current.value - 1 + slides.length) % slides.length
  restartAutoplay()
}

function goTo(index) {
  current.value = index
  restartAutoplay()
}

function startAutoplay() {
  if (reducedMotion) return
  stopAutoplay()
  timer = setInterval(next, intervalMs)
}

function stopAutoplay() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

function restartAutoplay() {
  stopAutoplay()
  startAutoplay()
}

onMounted(() => {
  reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  startAutoplay()
})

onUnmounted(stopAutoplay)
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

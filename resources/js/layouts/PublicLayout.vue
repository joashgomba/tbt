<template>
  <div class="public-layout">
    <a href="#main-content" class="portal-skip-link">{{ t('skipToContent') }}</a>

    <div class="portal-trust-bar" role="complementary" :aria-label="t('officialPlatform')">
      <div class="portal-trust-bar__inner">
        <span class="portal-trust-item">
          <i class="ti ti-lock" aria-hidden="true"></i>
          {{ t('secureConnection') }}
        </span>
        <span class="portal-trust-item">
          <i class="ti ti-shield-check" aria-hidden="true"></i>
          {{ t('privacyProtected') }}
        </span>
        <span class="portal-trust-item">
          <i class="ti ti-building-bank" aria-hidden="true"></i>
          {{ t('officialPlatform') }}
        </span>
        <a class="portal-trust-item portal-trust-item--link" href="tel:+25321300000">
          <i class="ti ti-phone" aria-hidden="true"></i>
          <span>{{ t('trustHotline') }}: {{ publicContact.hotlineDisplay }}</span>
        </a>
        <a class="portal-trust-item portal-trust-item--link" href="mailto:focal@ntb.dj">
          <i class="ti ti-mail" aria-hidden="true"></i>
          <span>{{ t('trustEmail') }}: {{ publicContact.email }}</span>
        </a>
      </div>
    </div>

    <header class="portal-header">
      <div class="portal-header__inner">
        <RouterLink class="portal-brand" :to="{ name: 'public-home' }" :aria-label="t('appName')">
          <span class="portal-brand__mark" aria-hidden="true">
            <img
              class="portal-brand__flag"
              src="/assets/vendor/fonts/flags/4x3/dj.svg"
              alt=""
              width="44"
              height="33"
            />
          </span>
          <span class="portal-brand__text">
            <p class="portal-brand__title">{{ t('appName') }}</p>
            <p class="portal-brand__subtitle">{{ t('appSubtitle') }}</p>
          </span>
        </RouterLink>

        <button
          type="button"
          class="portal-nav-toggle"
          :aria-expanded="navOpen"
          :aria-label="navOpen ? t('closeMenu') : t('openMenu')"
          @click="navOpen = !navOpen"
        >
          <i :class="navOpen ? 'ti ti-x' : 'ti ti-menu-2'" aria-hidden="true"></i>
        </button>

        <nav class="portal-nav" :class="{ 'portal-nav--open': navOpen }" :aria-label="t('footerQuickLinks')">
          <div class="portal-nav__links">
            <RouterLink
              class="portal-nav__link"
              :class="{ 'portal-nav__link--active': isNavActive('public-home') }"
              :to="{ name: 'public-home' }"
              @click="navOpen = false"
            >
              <i class="ti ti-home" aria-hidden="true"></i>
              {{ t('home') }}
            </RouterLink>
            <RouterLink
              class="portal-nav__link portal-nav__cta"
              :class="{ 'portal-nav__link--active': isNavActive('public-report') }"
              :to="{ name: 'public-report' }"
              @click="navOpen = false"
            >
              <i class="ti ti-flag" aria-hidden="true"></i>
              {{ t('reportBarrier') }}
            </RouterLink>
            <RouterLink
              class="portal-nav__link"
              :class="{ 'portal-nav__link--active': isNavActive('public-transparency') }"
              :to="{ name: 'public-transparency' }"
              @click="navOpen = false"
            >
              <i class="ti ti-chart-bar" aria-hidden="true"></i>
              {{ t('navTransparency') }}
            </RouterLink>
            <RouterLink
              class="portal-nav__link"
              :class="{ 'portal-nav__link--active': isNavActive('public-resources') }"
              :to="{ name: 'public-resources' }"
              @click="navOpen = false"
            >
              <i class="ti ti-book" aria-hidden="true"></i>
              {{ t('navResources') }}
            </RouterLink>
            <RouterLink
              class="portal-nav__link"
              :class="{ 'portal-nav__link--active': isNavActive('public-track') }"
              :to="{ name: 'public-track' }"
              @click="navOpen = false"
            >
              <i class="ti ti-search" aria-hidden="true"></i>
              {{ t('trackCase') }}
            </RouterLink>
          </div>
          <div class="portal-nav__utilities">
            <select
              v-model="currentLocale"
              class="portal-locale"
              :aria-label="t('language')"
              @change="onLocaleChange"
            >
              <option value="en">English</option>
              <option value="fr">Français</option>
            </select>
            <RouterLink class="portal-nav__link" :to="{ name: 'login' }" @click="navOpen = false">
              <i class="ti ti-user-shield" aria-hidden="true"></i>
              {{ t('adminPortal') }}
            </RouterLink>
          </div>
        </nav>
      </div>
    </header>

    <main id="main-content" class="portal-main" tabindex="-1">
      <router-view />
    </main>

    <footer class="portal-footer">
      <div class="portal-footer__inner">
        <div class="portal-footer__grid">
          <div>
            <p class="portal-footer__heading">{{ t('footerAbout') }}</p>
            <p class="portal-footer__text">{{ t('footerAboutText') }}</p>
            <div class="portal-footer__partners" aria-label="Partner regions">
              <span class="portal-partner-badge">
                <img src="/assets/vendor/fonts/flags/4x3/et.svg" alt="" width="20" height="15" />
                {{ t('partnerEthiopia') }}
              </span>
              <span class="portal-partner-badge">
                <img src="/assets/vendor/fonts/flags/4x3/dj.svg" alt="" width="20" height="15" />
                {{ t('partnerDjibouti') }}
              </span>
              <span class="portal-partner-badge">
                <i class="ti ti-world" aria-hidden="true"></i>
                {{ t('partnerComesa') }}
              </span>
            </div>
          </div>
          <div>
            <p class="portal-footer__heading">{{ t('footerQuickLinks') }}</p>
            <ul class="portal-footer__links">
              <li><RouterLink :to="{ name: 'public-report' }">{{ t('reportBarrier') }}</RouterLink></li>
              <li><RouterLink :to="{ name: 'public-transparency' }">{{ t('navTransparency') }}</RouterLink></li>
              <li><RouterLink :to="{ name: 'public-resources' }">{{ t('navResources') }}</RouterLink></li>
              <li><RouterLink :to="{ name: 'public-track' }">{{ t('trackCase') }}</RouterLink></li>
              <li><RouterLink :to="{ name: 'login' }">{{ t('adminPortal') }}</RouterLink></li>
            </ul>
          </div>
          <div>
            <p class="portal-footer__heading">{{ t('footerPrivacy') }}</p>
            <p class="portal-footer__text">{{ t('footerPrivacyText') }}</p>
            <p class="portal-footer__text mt-3">{{ t('footerContactText') }}</p>
          </div>
        </div>
        <div class="portal-footer__bottom">
          <span>© {{ year }} {{ t('appSubtitle') }}</span>
          <span>{{ t('allRightsReserved') }}</span>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from '@/composables/useI18n'

const publicContact = {
  hotlineDisplay: '+253 21 30 00 00',
  email: 'focal@ntb.dj',
}

const route = useRoute()
const { locale, setLocale, t } = useI18n()
const currentLocale = ref(locale.value)
const navOpen = ref(false)
const year = new Date().getFullYear()

watch(locale, (v) => { currentLocale.value = v })
watch(() => route.path, () => { navOpen.value = false })

function onLocaleChange() {
  setLocale(currentLocale.value)
}

function isNavActive(name) {
  if (name === 'public-report') {
    return route.name === 'public-report' || route.name === 'public-report-confirmation'
  }
  return route.name === name
}
</script>

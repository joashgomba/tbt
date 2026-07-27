<template>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <Sidebar />

      <div class="layout-page">
        <header class="layout-navbar navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme px-3">
          <div class="navbar-nav-right d-flex align-items-center w-100 gap-2">
            <div class="navbar-nav align-items-center flex-grow-1">
              <div class="nav-item d-flex align-items-center w-100">
                <i class="ti ti-search fs-4 lh-0"></i>
                <input type="text" class="form-control border-0 shadow-none" :placeholder="t('search')" />
              </div>
            </div>
            <select v-model="currentLocale" class="form-select form-select-sm w-auto" @change="onLocaleChange">
              <option value="en">EN</option>
              <option value="fr">FR</option>
            </select>
            <RouterLink
              class="btn btn-outline-primary btn-sm"
              :to="{ name: 'public-home' }"
              target="_blank"
              rel="noopener noreferrer"
            >
              <i class="ti ti-world me-1"></i>{{ t('appName') }}
            </RouterLink>
            <button type="button" class="btn btn-outline-danger btn-sm" @click="doLogout">
              <i class="ti ti-logout me-1"></i>{{ t('logout') }}
            </button>
          </div>
        </header>

        <div class="content-wrapper">
          <div class="container-fluid flex-grow-1 container-p-y">
            <router-view />
          </div>
          <footer class="content-footer footer bg-footer-theme">
            <div class="container-fluid py-2 text-muted small">{{ t('appSubtitle') }}</div>
          </footer>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Sidebar from '@/components/layout/Sidebar.vue'
import { useI18n } from '@/composables/useI18n'
import { logout } from '@/auth/session'

const router = useRouter()
const { locale, setLocale, t } = useI18n()
const currentLocale = ref(locale.value)
watch(locale, (v) => { currentLocale.value = v })

function onLocaleChange() {
  setLocale(currentLocale.value)
}

async function doLogout() {
  await logout()
  router.push({ name: 'login' })
}
</script>

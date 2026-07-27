<template>
  <aside id="admin-sidebar-menu" class="layout-menu menu-vertical menu bg-menu-theme sidebar-shell">
    <div class="app-brand demo sidebar-brand">
      <RouterLink class="app-brand-link sidebar-brand-link" :to="{ name: 'admin-dashboard' }" :aria-label="t('appSubtitle')">
        <img
          class="sidebar-brand-flag"
          src="/assets/vendor/fonts/flags/4x3/dj.svg"
          alt=""
          width="36"
          height="27"
        />
        <span class="sidebar-brand-title">{{ t('appSubtitle') }}</span>
      </RouterLink>
    </div>

    <div class="menu-inner-shadow"></div>

    <div class="sidebar-menu-scroll">
      <ul class="menu-inner py-1">
        <template v-for="(item, idx) in visibleMenu" :key="idx">
          <li v-if="item.section" class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ item.section }}</span>
          </li>

          <li v-else class="menu-item" :class="{ active: isActive(item) }">
            <RouterLink class="menu-link" :to="item.to">
              <i class="menu-icon tf-icons" :class="item.icon"></i>
              <div>{{ item.title }}</div>
            </RouterLink>
          </li>
        </template>
      </ul>
    </div>
  </aside>
</template>

<script setup>
import menuConfig from '@/navigation/menu'
import { hasPermission } from '@/auth/session'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from '@/composables/useI18n'

const route = useRoute()
const { t, locale } = useI18n()

const visibleMenu = computed(() => {
  void locale.value
  return menuConfig
    .filter((item) => !item.permission || hasPermission(item.permission))
    .map((item) => ({
      ...item,
      section: item.sectionKey ? t(item.sectionKey) : undefined,
      title: item.titleKey ? t(item.titleKey) : undefined,
    }))
})

function isActive(item) {
  return item?.to?.name && route.name === item.to.name
}
</script>

<style scoped>
.sidebar-shell {
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.sidebar-brand { flex: 0 0 auto; padding: 0.65rem 0.5rem 0.5rem; }
.sidebar-brand-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  text-decoration: none;
  text-align: center;
  padding: 0.15rem 0.35rem;
  width: 100%;
  max-width: 100%;
}
.sidebar-brand-flag {
  display: block;
  width: 2rem;
  height: auto;
  border-radius: 0.2rem;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
  border: 1px solid rgba(15, 23, 42, 0.06);
}
.sidebar-brand-title {
  display: block;
  width: 100%;
  max-width: 11.5rem;
  font-size: 0.58rem;
  font-weight: 600;
  line-height: 1.3;
  color: var(--bs-heading-color, #334155);
  letter-spacing: 0.005em;
  word-wrap: break-word;
  overflow-wrap: anywhere;
  hyphens: auto;
}
.sidebar-menu-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; overflow-x: hidden; scrollbar-width: thin; }
.sidebar-menu-scroll::-webkit-scrollbar { width: 8px; }
.sidebar-menu-scroll::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.18); border-radius: 999px; }
.sidebar-menu-scroll::-webkit-scrollbar-track { background: transparent; }
.menu-inner { margin: 0; }
.menu-inner-shadow { flex: 0 0 auto; }
</style>

import { ref } from 'vue'
import { http } from '@/api/http'

const authUser = ref(null)
const authChecked = ref(false)

export async function fetchAuthSession() {
  try {
    const { data } = await http.get('/auth/me')
    authUser.value = data.user
    return data.user
  } catch {
    authUser.value = null
    return null
  } finally {
    authChecked.value = true
  }
}

export function getAuthUser() {
  return authUser
}

export function isAuthenticated() {
  return !!authUser.value
}

export async function login(credentials) {
  const { data } = await http.post('/auth/login', credentials)

  if (data.mfa_required) {
    sessionStorage.setItem('mfa_challenge_id', String(data.challenge_id))
    sessionStorage.setItem('mfa_channel', data.channel || 'email')
    authUser.value = null
    authChecked.value = true
    return { mfaRequired: true, ...data }
  }

  authUser.value = data.user
  authChecked.value = true
  return data.user
}

export async function logout() {
  try {
    await http.post('/auth/logout')
  } finally {
    authUser.value = null
    authChecked.value = true
  }
}

export function hasPermission(permission) {
  if (!permission) return true
  return authUser.value?.permissions?.includes(permission) ?? false
}

export function hasAnyPermission(permissions = []) {
  return permissions.some((p) => hasPermission(p))
}

export function registerAuthNavigationGuard(router) {
  router.beforeEach(async (to) => {
    if (!authChecked.value) {
      await fetchAuthSession()
    }

    if (to.meta?.public) return true

    if (to.meta?.guest && authUser.value) {
      return { name: 'admin-dashboard' }
    }

    if (to.meta?.requiresAuth && !authUser.value) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (authUser.value?.mfa_enrollment_required && to.name !== 'admin-security' && to.meta?.requiresAuth) {
      return { name: 'admin-security', query: { redirect: to.fullPath } }
    }

    if (authUser.value && authUser.value.mfa_verified === false && to.meta?.requiresAuth && to.name !== 'admin-mfa-verify') {
      const pending = sessionStorage.getItem('mfa_challenge_id')
      if (pending) {
        return { name: 'admin-mfa-verify', query: { redirect: to.fullPath } }
      }
    }

    if (to.meta?.permission && !hasPermission(to.meta.permission)) {
      // Allow route if user has any complaint view permission for complaint pages
      if (to.name?.startsWith('admin-complaint') && hasAnyPermission(['complaints.view_all', 'complaints.view_own'])) {
        return true
      }
      return { name: 'admin-dashboard' }
    }

    return true
  })
}

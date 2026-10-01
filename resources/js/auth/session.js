import { ref } from 'vue'
import { http } from '@/api/http'

const authUser = ref(null)
const authChecked = ref(false)

const PORTAL_ONLY_PERMISSIONS = new Set(['complaints.submit', 'complaints.view_own', 'complaints.update'])

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

export function canAccessAdminPortal(user = authUser.value) {
  if (!user) return false
  if (typeof user.can_access_admin_portal === 'boolean') {
    return user.can_access_admin_portal
  }
  const perms = user.permissions || []
  if (!perms.length) return false
  return perms.some((p) => !PORTAL_ONLY_PERMISSIONS.has(p))
}

export function isPortalReporterOnly(user = authUser.value) {
  if (!user) return false
  if (typeof user.is_portal_reporter_only === 'boolean') {
    return user.is_portal_reporter_only
  }
  return hasPermission('complaints.submit') && !canAccessAdminPortal(user)
}

/** @deprecated use isPortalReporterOnly */
export function isPortalReporter(user = authUser.value) {
  return isPortalReporterOnly(user)
}

function isSafeAdminRedirect(path) {
  return typeof path === 'string'
    && path.startsWith('/admin')
    && !path.startsWith('//')
}

export function resolveAfterAdminLogin(user = authUser.value, redirect = null) {
  if (!canAccessAdminPortal(user)) {
    return { name: 'public-reporter-auth', query: { redirect: '/report' } }
  }
  if (isSafeAdminRedirect(redirect)) {
    return redirect
  }
  return { name: 'admin-dashboard' }
}

export function resolveAfterPortalLogin(user = authUser.value, redirect = null) {
  if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('/admin')) {
    return redirect
  }
  return { name: 'public-report' }
}

export async function login(credentials, options = {}) {
  const context = options.context === 'portal' ? 'portal' : 'admin'
  const { data } = await http.post('/auth/login', credentials, {
    headers: { 'X-Auth-Context': context },
  })

  if (data.mfa_required) {
    sessionStorage.setItem('mfa_challenge_id', String(data.challenge_id))
    sessionStorage.setItem('mfa_channel', data.channel || 'email')
    sessionStorage.setItem('mfa_auth_context', context)
    authUser.value = null
    authChecked.value = true
    return { mfaRequired: true, ...data }
  }

  authUser.value = data.user
  authChecked.value = true
  return data.user
}

export async function registerReporter(payload) {
  const { data } = await http.post('/public/auth/register', payload)
  authUser.value = data.user
  authChecked.value = true
  return data.user
}

export async function logout() {
  try {
    await http.post('/auth/logout')
  } catch {
    // Clear client state even if the server request fails.
  }

  authUser.value = null
  authChecked.value = true

  try {
    const { data } = await http.get('/auth/me')
    if (!data?.user) {
      authUser.value = null
    }
  } catch {
    authUser.value = null
  } finally {
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

    if (to.meta?.requiresReporter) {
      if (!authUser.value) {
        return {
          name: 'public-reporter-auth',
          query: { redirect: to.fullPath, mode: to.query?.mode === 'register' ? 'register' : undefined },
        }
      }
      if (!hasPermission('complaints.submit')) {
        return { name: 'login', query: { redirect: to.fullPath } }
      }
    }

    if (to.meta?.public && !to.meta?.requiresReporter && !to.meta?.requiresAdminPortal) {
      return true
    }

    const accessingAdmin = to.meta?.requiresAdminPortal
      || to.path.startsWith('/admin')
      || to.name?.toString().startsWith('admin-')

    if (accessingAdmin) {
      if (!authUser.value) {
        return { name: 'login', query: { redirect: to.fullPath } }
      }
      if (!canAccessAdminPortal(authUser.value)) {
        return { name: 'public-report' }
      }
    }

    if (to.meta?.guest && authUser.value) {
      if (to.name === 'login') {
        if (canAccessAdminPortal(authUser.value)) {
          return resolveAfterAdminLogin(
            authUser.value,
            typeof to.query.redirect === 'string' ? to.query.redirect : null,
          )
        }
        return resolveAfterPortalLogin(
          authUser.value,
          typeof to.query.redirect === 'string' ? to.query.redirect : null,
        )
      }
    }

    if (to.meta?.requiresAuth && !authUser.value) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (authUser.value?.mfa_enrollment_required && to.name !== 'admin-security' && to.meta?.requiresAdminPortal) {
      return { name: 'admin-security', query: { redirect: to.fullPath } }
    }

    if (authUser.value && authUser.value.mfa_verified === false && to.meta?.requiresAdminPortal && to.name !== 'admin-mfa-verify') {
      const pending = sessionStorage.getItem('mfa_challenge_id')
      if (pending) {
        return { name: 'admin-mfa-verify', query: { redirect: to.fullPath } }
      }
    }

    if (to.meta?.permission && !hasPermission(to.meta.permission)) {
      if (!canAccessAdminPortal(authUser.value)) {
        return { name: 'public-report' }
      }
      if (to.name?.startsWith('admin-complaint') && hasAnyPermission(['complaints.view_all', 'complaints.view_own'])) {
        return true
      }
      return { name: 'admin-dashboard' }
    }

    return true
  })
}

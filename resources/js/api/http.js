import axios from 'axios'

export const http = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
  },
})

http.interceptors.request.use((config) => {
  const locale = localStorage.getItem('ntb_locale')
  if (locale) {
    config.headers['X-Locale'] = locale
  }
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (token) {
    config.headers['X-CSRF-TOKEN'] = token
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status
    const url = error?.config?.url || ''

    if (status === 401 && !url.includes('/auth/login') && !url.includes('/auth/me')) {
      const redirect = encodeURIComponent(window.location.pathname + window.location.search)
      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = `/login?redirect=${redirect}`
      }
    }

    if (status === 419) {
      window.location.reload()
    }

    return Promise.reject(error)
  },
)

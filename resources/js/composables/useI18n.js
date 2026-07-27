import { computed, ref } from 'vue'
import { messages, t as translate } from '@/i18n'

const locale = ref(localStorage.getItem('ntb_locale') || 'en')

export function useI18n() {
  function setLocale(code) {
    locale.value = code
    localStorage.setItem('ntb_locale', code)
    document.documentElement.lang = code
  }

  const t = (key) => translate(key, locale.value)

  const localizedName = (item) => {
    if (!item) return ''
    return locale.value === 'fr' ? (item.name_fr || item.name_en || item.location_name || item.corridor_name || item.institution_name) : (item.name_en || item.name_fr || item.location_name || item.corridor_name || item.institution_name)
  }

  return {
    locale: computed(() => locale.value),
    setLocale,
    t,
    localizedName,
    messages,
  }
}

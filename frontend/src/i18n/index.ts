import { ref, type Ref } from 'vue'
import it from './it'
import en from './en'

export type Locale = 'it' | 'en'

/** Union of all translation keys (derived from the Italian locale as source of truth) */
export type TranslationKey = keyof typeof it

const STORAGE_KEY = 'scopa-locale'

const messages: Record<Locale, Record<string, string>> = { it, en }

function detectLocale(): Locale {
  if (typeof localStorage === 'undefined') return 'en'
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored === 'it' || stored === 'en') return stored

  const browserLang = typeof navigator !== 'undefined' ? navigator.language?.slice(0, 2) : undefined
  if (browserLang === 'it') return 'it'
  return 'en'
}

const currentLocale = ref<Locale>(detectLocale())

export interface I18n {
  locale: Ref<Locale>
  setLocale: (locale: Locale) => void
  t: (key: TranslationKey | string, params?: Record<string, string | number>) => string
}

export function useI18n(): I18n {
  function setLocale(locale: Locale): void {
    currentLocale.value = locale
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(STORAGE_KEY, locale)
    }
  }

  function t(key: TranslationKey | string, params?: Record<string, string | number>): string {
    let text = messages[currentLocale.value]?.[key] ?? messages.en[key] ?? key
    if (params) {
      for (const [k, v] of Object.entries(params)) {
        text = text.replaceAll(`{${k}}`, String(v))
      }
    }
    return text
  }

  return {
    locale: currentLocale,
    setLocale,
    t,
  }
}

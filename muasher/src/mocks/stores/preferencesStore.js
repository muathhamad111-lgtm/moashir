import { readCookie } from '../contract/cookies'
import { DEFAULT_LOCALE, SUPPORTED_LOCALES } from '@/constants/locales'
import { LOCALE_COOKIE } from '@/utils/localeCookie'

const localesByAccount = new Map()

export function rememberLocale(accountId, locale) {
  localesByAccount.set(String(accountId), locale)
}

export function profileLocaleOf(accountId) {
  const remembered = localesByAccount.get(String(accountId))
  if (remembered) return remembered
  const guestLocale = readCookie(LOCALE_COOKIE)
  return SUPPORTED_LOCALES.includes(guestLocale) ? guestLocale : DEFAULT_LOCALE
}

export function forgetLocales() {
  localesByAccount.clear()
}

// The columns each account keeps visible per list table (PATCH /me/preferences `table_columns`).
// Kept across reloads in the browser, as the server keeps them; unit tests start empty every time.
export const TABLE_COLUMNS_STORAGE_KEY = 'moashir_mock_table_columns'
const PERSISTED = import.meta.env.MODE !== 'test'

function storage() {
  return PERSISTED && typeof sessionStorage !== 'undefined' ? sessionStorage : null
}

function restoredTableColumns() {
  try {
    return new Map(
      Object.entries(JSON.parse(storage()?.getItem(TABLE_COLUMNS_STORAGE_KEY) ?? '{}')),
    )
  } catch {
    return new Map()
  }
}

const tableColumnsByAccount = restoredTableColumns()

export function tableColumnsOf(accountId) {
  return { ...tableColumnsByAccount.get(String(accountId)) }
}

export function rememberTableColumns(accountId, choices) {
  const next = tableColumnsOf(accountId)
  for (const [table, columns] of Object.entries(choices)) {
    if (columns === null) delete next[table]
    else next[table] = [...columns]
  }
  tableColumnsByAccount.set(String(accountId), next)
  storage()?.setItem(
    TABLE_COLUMNS_STORAGE_KEY,
    JSON.stringify(Object.fromEntries(tableColumnsByAccount)),
  )
}

export function forgetTableColumns() {
  tableColumnsByAccount.clear()
  storage()?.removeItem(TABLE_COLUMNS_STORAGE_KEY)
}

import { toCamelKey } from '@/api/caseMapping'
import { fieldLabelKey } from './sections'

const ITEM_LABEL_KEYS = Object.freeze({
  name: 'portal.profile.pendingChange.itemName',
  logo: 'portal.profile.logo.title',
})

/** The translation key naming a change item: a profile field or a file. */
export function changeItemLabelKey(item) {
  return ITEM_LABEL_KEYS[item] ?? fieldLabelKey(toCamelKey(item))
}

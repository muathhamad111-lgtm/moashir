import {
  ACTION_ITEM_KEYS as K,
  ATTENTION_ITEM_PRESENTATION,
  FALLBACK_ATTENTION_PRESENTATION,
} from '@/constants/actionCenter'
import { isInternalPath } from '@/utils/internalPath'

const TEXT_ROOT = 'portal.strip.items'
const SINGLE = 1

function single(item) {
  return item.count === SINGLE && Boolean(item.context?.reference)
}

function draftText(item) {
  if (single(item) && item.context.remainingSubs !== null)
    return { variant: 'remaining', plural: item.context.remainingSubs }
  return single(item)
    ? { variant: 'single', plural: SINGLE }
    : { variant: 'many', plural: item.count }
}

function profileText(item) {
  return item.context?.percent === null || item.context?.percent === undefined
    ? { variant: 'plain', plural: SINGLE }
    : { variant: 'percent', plural: SINGLE }
}

function countedText(item) {
  return single(item)
    ? { variant: 'single', plural: SINGLE }
    : { variant: 'many', plural: item.count }
}

const TEXT_RESOLVERS = {
  [K.SUBMIT_ACCREDITED_DRAFT]: draftText,
  [K.COMPLETE_PROFILE]: profileText,
}

function textOf(item) {
  const resolve = TEXT_RESOLVERS[item.key] ?? countedText
  const { variant, plural } = resolve(item)
  return { textKey: `${TEXT_ROOT}.${item.key}.${variant}`, plural }
}

export function isKnownAttentionItem(item) {
  return Object.values(K).includes(item.key)
}

export function attentionView(item) {
  const presentation = ATTENTION_ITEM_PRESENTATION[item.key] ?? FALLBACK_ATTENTION_PRESENTATION
  return {
    id: item.key,
    ...presentation,
    ...textOf(item),
    count: item.count,
    reference: item.context?.reference ?? null,
    percent: item.context?.percent ?? null,
    to: isInternalPath(item.link) ? item.link : null,
    dueAt: item.dueAt,
  }
}

export function attentionViews(items) {
  return items.filter(isKnownAttentionItem).map(attentionView)
}

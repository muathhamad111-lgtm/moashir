import { useI18n } from 'vue-i18n'
import { useProfileChangeFormat } from '@/composables/useProfileChangeFormat'
import { formatDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

const FIELD_CHANGE = 'profile_field_changed'
const EMAIL_KINDS = ['email_change_requested', 'email_change_decided']
const DATE_TIME = { dateStyle: 'medium', timeStyle: 'short' }

function camelOf(kind) {
  return kind.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase())
}

// The staff who decided an entry, with their first role when known.
function useStaffLabel() {
  const { t, locale } = useI18n()
  return function staffLabel(actor) {
    if (!actor?.name) return ''
    const role = actor.roles?.[0]?.label
    return role
      ? t('entities.history.staffWithRole', {
          name: actor.name,
          role: localizedText(role, locale.value),
        })
      : actor.name
  }
}

// Turns one account-timeline entry from the server into the lines the «سجل الحساب» tab shows.
export function useTimelineEntryFormat() {
  const { t, locale } = useI18n()
  const format = useProfileChangeFormat()
  const staffLabel = useStaffLabel()

  function titleOf(entry) {
    if (entry.kind === FIELD_CHANGE)
      return t('entities.record.timeline.changed', { field: format.fieldLabel(entry.change) })
    return t(`entities.record.timeline.events.${camelOf(entry.kind)}`)
  }

  function transitionOf(entry) {
    if (entry.kind === FIELD_CHANGE)
      return {
        before: format.valueLabel(entry.change.field, entry.change.oldValue),
        after: format.valueLabel(entry.change.field, entry.change.newValue),
      }
    if (EMAIL_KINDS.includes(entry.kind) && entry.email)
      return { before: entry.email.old, after: entry.email.new, ltr: true }
    return null
  }

  function detailOf(entry) {
    const parts = []
    if (entry.status) parts.push(t(`entities.record.timeline.statuses.${entry.status}`))
    if (entry.fields?.length)
      parts.push(t('entities.record.timeline.fields', { count: entry.fields.length }))
    if (entry.reason) parts.push(t('entities.record.timeline.reason', { reason: entry.reason }))
    return parts.join(' · ')
  }

  function actorOf(entry) {
    if (entry.kind === FIELD_CHANGE)
      return format.actorLabel(entry.change, { showStaffIdentity: true })
    return staffLabel(entry.actor)
  }

  function itemOf(entry) {
    const transition = transitionOf(entry)
    return {
      id: entry.id,
      kind: entry.kind,
      isChange: transition !== null,
      title: titleOf(entry),
      transition,
      detail: detailOf(entry),
      actor: actorOf(entry),
      time: entry.occurredAt,
      timeLabel: formatDate(entry.occurredAt, locale.value, DATE_TIME),
    }
  }

  return { itemOf }
}

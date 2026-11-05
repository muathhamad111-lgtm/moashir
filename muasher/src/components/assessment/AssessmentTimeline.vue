<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { statusLabelKey } from '@/utils/canonicalStatus'

const DATE_OPTIONS = { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Riyadh' }
const VERB_ICONS = {
  start: 'fa-solid fa-play',
  submit: 'fa-solid fa-paper-plane',
  resubmit: 'fa-solid fa-paper-plane',
  compute_result: 'fa-solid fa-calculator',
  assign: 'fa-solid fa-user-check',
  reassign: 'fa-solid fa-user-pen',
  start_review: 'fa-solid fa-magnifying-glass',
  request_completion: 'fa-solid fa-reply',
  complete: 'fa-solid fa-flag-checkered',
  refund: 'fa-solid fa-rotate-left',
  expire_completion: 'fa-solid fa-hourglass-end',
}

const props = defineProps({
  history: { type: Array, required: true },
})

const { t, te, locale } = useI18n()

function actorLabel(change) {
  if (change.actor !== 'staff') return t(`assessment.timeline.actors.${change.actor}`)
  const staff = change.actorStaff
  if (!staff?.name) return t('assessment.timeline.actors.staff')
  const roles = staff.roles.map((role) => localizedText(role.label, locale.value)).filter(Boolean)
  if (!roles.length) return staff.name
  const list = new Intl.ListFormat(locale.value, { style: 'long', type: 'conjunction' }).format(
    roles,
  )
  return t('assessment.timeline.actors.staffWithRole', { name: staff.name, roles: list })
}

function verbLabel(change) {
  const key = `assessment.timeline.verbs.${change.verb}`
  return te(key)
    ? t(key)
    : t('assessment.timeline.changedTo', { status: t(statusLabelKey(change.to)) })
}

const steps = computed(() =>
  props.history.map((change, index) => ({
    key: `${index}-${change.verb}`,
    at: change.at,
    icon: VERB_ICONS[change.verb] ?? 'fa-solid fa-circle-check',
    verb: verbLabel(change),
    actor: actorLabel(change),
    date: change.at ? formatDate(change.at, locale.value, DATE_OPTIONS) : '',
  })),
)
</script>

<template>
  <ol v-if="steps.length" class="assessment-timeline" :aria-label="t('assessment.timeline.label')">
    <li
      v-for="step in steps"
      :key="step.key"
      class="assessment-timeline__step assessment-timeline__step--done"
    >
      <i :class="step.icon" class="assessment-timeline__icon" aria-hidden="true"></i>
      <span class="assessment-timeline__name">{{ step.verb }}</span>
      <span class="assessment-timeline__actor">{{ step.actor }}</span>
      <time class="assessment-timeline__date" :datetime="step.at">{{ step.date }}</time>
    </li>
  </ol>
  <p v-else class="assessment-timeline__empty">{{ t('assessment.timeline.empty') }}</p>
</template>

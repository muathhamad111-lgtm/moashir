<script setup>
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import Badge from '@/components/ui/Badge.vue'

defineProps({
  changes: { type: Array, required: true },
})

const { t, locale } = useI18n()
const { localized, weight } = useCriteria()

const CHANGE_VARIANTS = {
  added: 'success',
  removed: 'danger',
  modified: 'info',
  text_corrected: 'muted',
}

function fieldsOf(change) {
  const names = change.fields.map((field) => t(`criteria.publish.preview.fields.${field}`))
  return new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(names)
}

function weightChange(change) {
  if (!change.fields.includes('weight')) return ''
  return t('criteria.publish.preview.weightChange', {
    before: weight(change.before.weight),
    after: weight(change.after.weight),
  })
}
</script>

<template>
  <ul class="criteria-change-list">
    <li
      v-for="change in changes"
      :key="`${change.nodeType}:${change.code}`"
      class="criteria-change-list__item"
    >
      <span class="criteria-change-list__badges">
        <Badge :variant="CHANGE_VARIANTS[change.change] ?? 'muted'">
          {{ t(`criteria.publish.preview.changeTypes.${change.change}`) }}
        </Badge>
        <Badge v-if="change.scoreAffecting" variant="warning">
          {{ t('criteria.publish.preview.scoreAffecting') }}
        </Badge>
      </span>
      <span class="criteria-change-list__node">
        {{ t(`criteria.publish.preview.nodeTypes.${change.nodeType}`) }}
        <bdi class="criteria-change-list__code">{{ change.code }}</bdi>
        <template v-if="change.label"> — {{ localized(change.label) }}</template>
      </span>
      <span v-if="change.fields.length" class="criteria-change-list__fields">
        {{ t('criteria.publish.preview.changedFields', { fields: fieldsOf(change) }) }}
        <bdi v-if="weightChange(change)">({{ weightChange(change) }})</bdi>
      </span>
    </li>
  </ul>
</template>

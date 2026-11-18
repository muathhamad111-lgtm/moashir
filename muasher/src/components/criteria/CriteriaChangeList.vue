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
  <ul class="change-list">
    <li
      v-for="change in changes"
      :key="`${change.nodeType}:${change.code}`"
      class="change-list__item"
    >
      <span class="change-list__badges">
        <Badge :variant="CHANGE_VARIANTS[change.change] ?? 'muted'">
          {{ t(`criteria.publish.preview.changeTypes.${change.change}`) }}
        </Badge>
        <Badge v-if="change.scoreAffecting" variant="warning">
          {{ t('criteria.publish.preview.scoreAffecting') }}
        </Badge>
      </span>
      <span class="change-list__node">
        {{ t(`criteria.publish.preview.nodeTypes.${change.nodeType}`) }}
        <bdi class="change-list__code">{{ change.code }}</bdi>
        <template v-if="change.label"> — {{ localized(change.label) }}</template>
      </span>
      <span v-if="change.fields.length" class="change-list__fields">
        {{ t('criteria.publish.preview.changedFields', { fields: fieldsOf(change) }) }}
        <bdi v-if="weightChange(change)">({{ weightChange(change) }})</bdi>
      </span>
    </li>
  </ul>
</template>

<style scoped lang="scss">
.change-list {
  display: grid;
  gap: var(--space-2);
  max-block-size: 16rem;
  margin: var(--space-2) 0 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.change-list__item {
  display: grid;
  gap: var(--space-1);
  padding-block-end: var(--space-2);
  border-block-end: 1px solid var(--theme-border);
}

.change-list__badges {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1);
}

.change-list__code {
  font-variant-numeric: tabular-nums;
}

.change-list__fields {
  color: var(--theme-text-muted);
  font-size: var(--fs-sm);
}
</style>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReorderButtons from './ReorderButtons.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaRowActions } from '@/composables/useCriteriaRowActions'
import { percentOf } from '@/utils/weight'
import { EVALUATION_TYPES } from '@/constants/criteria'

const props = defineProps({
  subs: { type: Array, required: true },
  mainWeight: { type: String, required: true },
  access: { type: Object, required: true },
})

const emit = defineEmits(['action', 'move'])
const { t } = useI18n()
const { localized, weight } = useCriteria()
const { actionsFor } = useCriteriaRowActions(props.access)

const columns = computed(() => [
  { key: 'order', label: t('criteria.columns.order') },
  { key: 'code', label: t('criteria.columns.code') },
  { key: 'name', label: t('criteria.columns.name') },
  { key: 'weight', label: t('criteria.columns.weight') },
  { key: 'type', label: t('criteria.columns.evaluationType') },
  { key: 'points', label: t('criteria.columns.points') },
  { key: 'active', label: t('criteria.columns.active') },
  { key: 'actions', label: t('components.actions.label') },
])

function isGuided(sub) {
  return sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS
}

function rowActions(sub) {
  const actions = actionsFor(
    sub,
    isGuided(sub) ? { label: 'criteria.actions.openPoints', icon: 'list-check' } : null,
  )
  if (!props.access.canEditTree.value) return actions
  const label = isGuided(sub)
    ? 'criteria.evaluationType.switchToDirect'
    : 'criteria.evaluationType.switchToPoints'
  return [...actions, { key: 'switch', label: t(label), icon: 'swap' }]
}
</script>

<template>
  <DataTable :columns="columns" :items="subs" status="success">
    <template #cell-order="{ item }">
      <ReorderButtons
        v-if="access.canEditTree.value"
        :index="subs.indexOf(item)"
        :count="subs.length"
        :label="localized(item.name)"
        @move="emit('move', subs.indexOf(item), $event)"
      />
      <span v-else class="criteria-page__order">{{ item.order }}</span>
    </template>
    <template #cell-code="{ item }"
      ><span class="criteria-page__code">{{ item.code }}</span></template
    >
    <template #cell-name="{ item }">
      <button
        v-if="isGuided(item)"
        type="button"
        class="criteria-page__link"
        @click="emit('action', 'open', item)"
      >
        {{ localized(item.name) }}
      </button>
      <span v-else class="criteria-page__name">{{ localized(item.name) }}</span>
    </template>
    <template #cell-weight="{ item }">
      <span class="criteria-page__weight">{{ weight(item.weight) }}</span>
      <span class="criteria-page__sub-count">{{
        t('criteria.weights.percent', { value: percentOf(item.weight, mainWeight) })
      }}</span>
    </template>
    <template #cell-type="{ item }">
      <Badge :variant="isGuided(item) ? 'info' : 'muted'">
        {{
          isGuided(item)
            ? t('criteria.evaluationType.short.guidingPoints')
            : t('criteria.evaluationType.short.direct')
        }}
      </Badge>
    </template>
    <template #cell-points="{ item }">
      <span v-if="isGuided(item)">{{
        t('criteria.main.pointCount', { count: item.pointCount }, item.pointCount)
      }}</span>
      <span v-else class="criteria-page__muted">{{ t('criteria.main.notApplicable') }}</span>
    </template>
    <template #cell-active="{ item }"
      ><StatusBadge :status="item.active ? 'active' : 'inactive'"
    /></template>
    <template #cell-actions="{ item }">
      <ActionsDropdown :actions="rowActions(item)" @select="emit('action', $event, item)" />
    </template>
  </DataTable>
</template>

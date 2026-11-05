<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReorderButtons from './ReorderButtons.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaRowActions } from '@/composables/useCriteriaRowActions'
import { percentOf } from '@/utils/weight'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  points: { type: Array, required: true },
  subWeight: { type: String, required: true },
  access: { type: Object, required: true },
})

const emit = defineEmits(['action', 'move'])
const { t } = useI18n()
const { localized, weight } = useCriteria()
const { actionsFor } = useCriteriaRowActions(props.access)

const columns = computed(() => [
  { key: 'order', label: t('criteria.columns.order') },
  { key: 'code', label: t('criteria.columns.code') },
  { key: 'description', label: t('criteria.columns.description') },
  { key: 'weight', label: t('criteria.columns.weight') },
  { key: 'attachments', label: t('criteria.columns.attachments') },
  { key: 'active', label: t('criteria.columns.active') },
  { key: 'actions', label: t('components.actions.label') },
])
</script>

<template>
  <DataTable :columns="columns" :items="points" status="success">
    <template #cell-order="{ item }">
      <ReorderButtons
        v-if="access.canEditTree.value"
        :index="points.indexOf(item)"
        :count="points.length"
        :label="item.code"
        @move="emit('move', points.indexOf(item), $event)"
      />
      <span v-else class="criteria-page__order">{{ item.order }}</span>
    </template>
    <template #cell-code="{ item }"
      ><span class="criteria-page__code">{{ item.code }}</span></template
    >
    <template #cell-description="{ item }">{{ localized(item.description) }}</template>
    <template #cell-weight="{ item }">
      <span class="criteria-page__weight">{{ weight(item.weight) }}</span>
      <span class="criteria-page__sub-count">{{
        t('criteria.weights.percent', { value: percentOf(item.weight, subWeight) })
      }}</span>
    </template>
    <template #cell-attachments="{ item }">
      <span v-if="item.attachments.length" class="criteria-page__attach">
        <AppIcon name="attachment" />
        {{
          t(
            'criteria.attachments.count',
            { count: item.attachments.length },
            item.attachments.length,
          )
        }}
      </span>
      <span v-else class="criteria-page__muted">{{ t('criteria.attachments.none') }}</span>
    </template>
    <template #cell-active="{ item }"
      ><StatusBadge :status="item.active ? 'active' : 'inactive'"
    /></template>
    <template #cell-actions="{ item }">
      <ActionsDropdown
        v-if="actionsFor(item).length"
        :actions="actionsFor(item)"
        @select="emit('action', $event, item)"
      />
    </template>
  </DataTable>
</template>

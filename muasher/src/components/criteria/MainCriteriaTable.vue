<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReorderButtons from './ReorderButtons.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaRowActions } from '@/composables/useCriteriaRowActions'

const props = defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  access: { type: Object, required: true },
  reorderable: { type: Boolean, default: false },
})

const emit = defineEmits(['action', 'move', 'retry', 'add'])
const { t } = useI18n()
const { localized, weight } = useCriteria()
const { actionsFor } = useCriteriaRowActions(props.access)

const OPEN_SUBS = { label: 'criteria.actions.openSubs', icon: 'fa-solid fa-diagram-project' }

const columns = computed(() => [
  { key: 'order', label: t('criteria.columns.order') },
  { key: 'code', label: t('criteria.columns.code') },
  { key: 'name', label: t('criteria.columns.name') },
  { key: 'group', label: t('criteria.columns.group') },
  { key: 'weight', label: t('criteria.columns.weight') },
  { key: 'balance', label: t('criteria.columns.balance') },
  { key: 'active', label: t('criteria.columns.active') },
  { key: 'actions', label: t('components.actions.label') },
])
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('criteria.index.emptyTitle')"
    :empty-message="t('criteria.index.emptyMessage')"
    @retry="emit('retry')"
  >
    <template v-if="access.canEditTree.value" #empty-action>
      <BaseButton variant="primary" icon="fa-solid fa-plus" @click="emit('add')">{{
        t('criteria.index.add')
      }}</BaseButton>
    </template>
    <template #cell-order="{ item }">
      <ReorderButtons
        v-if="reorderable"
        :index="items.indexOf(item)"
        :count="items.length"
        :label="localized(item.name)"
        @move="emit('move', items.indexOf(item), $event)"
      />
      <span v-else class="criteria-page__order">{{ item.order }}</span>
    </template>
    <template #cell-code="{ item }"
      ><span class="criteria-page__code">{{ item.code }}</span></template
    >
    <template #cell-name="{ item }">
      <button type="button" class="criteria-page__link" @click="emit('action', 'open', item)">
        {{ localized(item.name) }}
      </button>
      <span class="criteria-page__sub-count">{{
        t('criteria.index.subCount', { count: item.subCount }, item.subCount)
      }}</span>
    </template>
    <template #cell-group="{ item }"
      ><Badge variant="muted">{{ t(`criteria.groups.${item.group}`) }}</Badge></template
    >
    <template #cell-weight="{ item }">
      <span class="criteria-page__weight">{{ weight(item.weight) }}</span>
      <span class="criteria-page__sub-count">
        {{
          item.distributeEqually
            ? t('criteria.distribution.equal')
            : t('criteria.distribution.manual')
        }}
      </span>
    </template>
    <template #cell-balance="{ item }">
      <Badge v-if="item.weightBalanced" variant="success">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        {{ t('criteria.balance.balanced') }}
      </Badge>
      <Badge v-else variant="warning">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        {{ t('criteria.balance.mismatchShort', { diff: weight(item.remainingWeight) }) }}
      </Badge>
    </template>
    <template #cell-active="{ item }"
      ><StatusBadge :status="item.active ? 'active' : 'inactive'"
    /></template>
    <template #cell-actions="{ item }">
      <ActionsDropdown
        :actions="actionsFor(item, OPEN_SUBS)"
        @select="emit('action', $event, item)"
      />
    </template>
  </DataTable>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Accordion from '@/components/ui/Accordion.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  groups: { type: Array, required: true },
  expanded: { type: Boolean, default: false },
})

const { t, te, n } = useI18n()

function moduleName(module) {
  const key = `notifications.modules.${module}`
  return te(key) ? t(key) : module
}

const items = computed(() =>
  props.groups.map((group) => ({
    id: group.module,
    title: moduleName(group.module),
    count: formatNumber(n, group.types.length),
    types: group.types,
  })),
)
const openIds = computed(() => (props.expanded ? items.value.map((item) => item.id) : []))
</script>

<template>
  <EmptyState
    v-if="!groups.length"
    icon="search"
    :title="t('settings.notifications.catalogue.emptyTitle')"
    :message="t('settings.notifications.catalogue.emptyMessage')"
  />
  <Accordion
    v-else
    :key="expanded ? 'expanded' : 'collapsed'"
    class="notification-groups"
    :items="items"
    :initially-open="openIds"
    multiple
  >
    <template #title="{ item }">
      <span class="notification-groups__title">
        {{ item.title }}
        <span class="notification-groups__count">{{ item.count }}</span>
      </span>
    </template>
    <template #item="{ item }">
      <slot name="group" :group="item" />
    </template>
  </Accordion>
</template>

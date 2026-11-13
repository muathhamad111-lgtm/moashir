<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DashboardValue from '@/components/dashboard/DashboardValue.vue'

const props = defineProps({
  definition: { type: Object, required: true },
  data: { type: [Array, Object, null], default: null },
})

const { t } = useI18n()
const fact = computed(() =>
  props.data && !Array.isArray(props.data) && typeof props.data === 'object' ? props.data : null,
)
</script>

<template>
  <p v-if="!fact" class="dashboard-widget__empty">{{ t('dashboard.widgets.empty') }}</p>
  <dl v-else class="widget-facts">
    <div v-for="column in definition.columns" :key="column.key" class="widget-facts__row">
      <dt>{{ t(`dashboard.columns.${column.key}`) }}</dt>
      <dd><DashboardValue :value="fact[column.key]" :format="column.format" :row="fact" /></dd>
    </div>
  </dl>
</template>

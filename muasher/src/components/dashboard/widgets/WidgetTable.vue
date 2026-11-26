<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DashboardValue from '@/components/dashboard/DashboardValue.vue'

const props = defineProps({
  definition: { type: Object, required: true },
  data: { type: [Array, Object, null], default: null },
  caption: { type: String, required: true },
})

const { t } = useI18n()
const rows = computed(() => (Array.isArray(props.data) ? props.data : []))
</script>

<template>
  <p v-if="!rows.length" class="dashboard-widget__empty">{{ t('dashboard.widgets.empty') }}</p>
  <div v-else class="widget-table">
    <table class="widget-table__table">
      <caption class="widget-table__caption">
        {{
          caption
        }}
      </caption>
      <thead>
        <tr>
          <th v-for="column in definition.columns" :key="column.key" scope="col">
            {{ t(`dashboard.columns.${column.key}`) }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, index) in rows" :key="index">
          <td v-for="column in definition.columns" :key="column.key">
            <DashboardValue :value="row[column.key]" :format="column.format" :row="row" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

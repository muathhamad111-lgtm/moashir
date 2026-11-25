<script setup>
import { computed, onMounted, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import DataListCard from '@/components/data-list/DataListCard.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import { useEntityAssessments } from '@/composables/useEntityAssessments'
import { formatDate } from '@/utils/datetime'
import { percentText } from '@/utils/percentText'
import { ROUTE_NAMES } from '@/constants/routeNames'

const COLUMNS = ['number', 'path', 'createdAt', 'status', 'result']

const props = defineProps({
  entityId: { type: [Number, String], required: true },
})

const { t, locale, n } = useI18n()
const { status, items, pagination, load, goTo } = useEntityAssessments(toRef(props, 'entityId'))
// Wide enough for the longest request number and path label; with the table rule no value is cut,
// and a narrower screen scrolls the table sideways.
const WIDTHS = { number: 'lg', path: 'xl' }
const columns = computed(() =>
  COLUMNS.map((key) => ({
    key,
    width: WIDTHS[key],
    label: t(`entities.record.assessments.columns.${key}`),
  })),
)

function dateOf(value) {
  return formatDate(value, locale.value, { dateStyle: 'medium', timeZone: 'Asia/Riyadh' })
}

onMounted(load)
</script>

<template>
  <DataListCard class="entity-assessments-table">
    <template #toolbar>
      <div class="entity-assessments-table__toolbar">
        <h2 class="entity-assessments-table__title">{{ t('assessment.staff.entityCardTitle') }}</h2>
      </div>
    </template>
    <DataTable
      table-key="entity-assessments"
      :columns="columns"
      :items="items"
      :status="status"
      :empty-title="t('assessment.staff.noAssessments')"
      :error-message="t('assessment.staff.loadFailed')"
      @retry="load"
    >
      <template #cell-number="{ item }">
        <router-link
          class="entity-assessments-table__number"
          :to="{ name: ROUTE_NAMES.ADMIN_ASSESSMENT, params: { id: item.id } }"
          ><bdi dir="ltr">{{ item.number }}</bdi></router-link
        >
      </template>
      <template #cell-path="{ item }">
        <PathBadge :path="item.path" :label="item.pathLabel" compact />
      </template>
      <template #cell-createdAt="{ item }">{{ dateOf(item.createdAt) }}</template>
      <template #cell-status="{ item }">
        <span class="entity-assessments-table__status">
          <StatusBadge :status="item.status" />
          <FrozenBadge v-if="item.isFrozen" />
        </span>
      </template>
      <template #cell-result="{ item }">
        <bdi v-if="item.result" dir="ltr">{{ percentText(n, t, item.result.percent) }}</bdi>
        <span v-else class="entity-assessments-table__none">{{
          t('entities.record.assessments.noResult')
        }}</span>
      </template>
    </DataTable>
    <template v-if="pagination.lastPage > 1" #footer>
      <Pagination :page="pagination.page" :last-page="pagination.lastPage" @update:page="goTo" />
    </template>
  </DataListCard>
</template>

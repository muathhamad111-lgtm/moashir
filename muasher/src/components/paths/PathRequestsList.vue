<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PATH_COLUMNS } from '@/constants/entityPaths'
import { useCompactViewport } from '@/composables/useCompactViewport'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import PathRequestCell from './PathRequestCell.vue'

const props = defineProps({
  path: { type: String, required: true },
  rows: { type: Array, required: true },
  status: { type: String, required: true },
  page: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
})

const emit = defineEmits(['page', 'retry'])

const { t } = useI18n()
const isCompact = useCompactViewport()
const columns = computed(() => PATH_COLUMNS[props.path])
const dataColumns = computed(() => columns.value.filter((column) => column !== 'action'))
const titleId = computed(() => `path-requests-${props.path}`)
</script>

<template>
  <section class="path-requests" :aria-labelledby="titleId" data-testid="path-requests">
    <header class="path-requests__head">
      <h2 :id="titleId" class="path-requests__title">{{ t('paths.list.title') }}</h2>
      <p class="path-requests__hint">{{ t(`paths.list.hint.${path}`) }}</p>
    </header>
    <Skeleton v-if="status === 'loading'" variant="text" :count="4" />
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('paths.list.loadError')"
      @retry="emit('retry')"
    />
    <EmptyState
      v-else-if="!rows.length"
      icon="folder"
      :title="t('paths.list.empty.title')"
      :message="t(`paths.list.empty.${path}`)"
    />
    <ul v-else-if="isCompact" class="path-requests__cards">
      <li
        v-for="row in rows"
        :key="row.rowKey"
        class="path-request-card"
        :data-testid="`path-row-${row.rowKey}`"
      >
        <dl class="path-request-card__facts">
          <div v-for="column in dataColumns" :key="column" class="path-request-card__fact">
            <dt>{{ t(`paths.list.columns.${column}`) }}</dt>
            <dd>
              <PathRequestCell
                :column="column"
                :row="row"
                :path="path"
                :cycle-name="row.awardCycle?.name ?? null"
              />
            </dd>
          </div>
        </dl>
        <PathRequestCell column="action" :row="row" :path="path" />
      </li>
    </ul>
    <div v-else class="path-requests__scroll">
      <table class="path-requests__table">
        <thead>
          <tr>
            <th v-for="column in columns" :key="column" scope="col">
              {{ t(`paths.list.columns.${column}`) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.rowKey" :data-testid="`path-row-${row.rowKey}`">
            <td v-for="column in columns" :key="column">
              <PathRequestCell
                :column="column"
                :row="row"
                :path="path"
                :cycle-name="row.awardCycle?.name ?? null"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination
      v-if="status === 'success' && lastPage > 1"
      :page="page"
      :last-page="lastPage"
      @update:page="emit('page', $event)"
    />
  </section>
</template>

<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import PreparingRequestsTable from '@/components/requests/PreparingRequestsTable.vue'
import { usePreparingRequests } from '@/composables/usePreparingRequests'
import { ASSESSMENT_PATHS } from '@/constants/assessment'

const props = defineProps({
  path: { type: String, required: true },
})

const { t } = useI18n()
const list = usePreparingRequests(toRef(props, 'path'))
const { items, status, errorMessage, load, searchInput, paginator } = list
const { pagination, setPage } = paginator
const noteKey = computed(() =>
  props.path === ASSESSMENT_PATHS.ADVANCED
    ? 'requests.awaitingEntity.note.advanced'
    : 'requests.awaitingEntity.note.accredited',
)
</script>

<template>
  <div class="preparing-requests">
    <p class="preparing-requests__note" role="note">
      <AppIcon name="info-circle" />
      <span>{{ t(noteKey) }}</span>
    </p>
    <ListToolbar
      v-model:search="searchInput"
      :search-placeholder="t('requests.index.searchPlaceholder')"
    />
    <PreparingRequestsTable
      :items="items"
      :status="status"
      :error-message="errorMessage"
      @retry="load"
    />
    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      @update:page="setPage($event)"
    />
  </div>
</template>

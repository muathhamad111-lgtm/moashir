<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataListCard from '@/components/data-list/DataListCard.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ProfileChangeReviewDrawer from '@/components/entities/admin/ProfileChangeReviewDrawer.vue'
import { useProfileChangeReviews } from '@/composables/useProfileChangeReviews'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { PROFILE_CHANGE_STATUSES } from '@/constants/entityEnums'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const { dateTime } = useEntityDisplay()
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  statusOptions,
  pendingTotal,
  review,
  entityName,
  itemLabels,
  openReview,
} = useProfileChangeReviews()
const { search } = filters

const columns = computed(() =>
  ['entity', 'items', 'submittedAt', 'status', 'actions'].map((key) => ({
    key,
    label: t(`entities.profileChanges.columns.${key}`),
  })),
)
</script>

<template>
  <section class="entities-admin">
    <header class="entities-admin__header">
      <div>
        <h1 class="entities-admin__title">{{ t('entities.profileChanges.title') }}</h1>
        <p class="entities-admin__subtitle">{{ t('entities.profileChanges.subtitle') }}</p>
        <p v-if="pendingTotal" class="entities-admin__subtitle" data-testid="profile-changes-count">
          {{ t('entities.profileChanges.pendingCount', { count: pendingTotal }) }}
        </p>
      </div>
      <BaseButton variant="secondary" icon="arrow-left" :to="{ name: ROUTE_NAMES.ADMIN_ENTITIES }">
        {{ t('entities.admin.backToEntities') }}
      </BaseButton>
    </header>
    <DataListCard>
      <template #toolbar>
        <ListToolbar
          v-model:search="search"
          :search-placeholder="t('entities.profileChanges.searchLabel')"
          :filter-state="filters"
        >
          <template #filters="{ draft }">
            <AppSelect
              :model-value="draft.filters.status"
              :options="statusOptions"
              :label="t('entities.filters.status')"
              @update:model-value="draft.setFilter('status', $event)"
            />
          </template>
        </ListToolbar>
      </template>

      <DataTable
        :columns="columns"
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :empty-title="t('entities.profileChanges.emptyTitle')"
        :empty-message="t('entities.profileChanges.emptyMessage')"
        @retry="load"
      >
        <template #cell-entity="{ item }">
          <router-link
            class="entity-table__name"
            :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: item.entity.id } }"
          >
            {{ entityName(item) }}
          </router-link>
        </template>
        <template #cell-items="{ item }">{{ itemLabels(item) }}</template>
        <template #cell-submittedAt="{ item }">{{ dateTime(item.submittedAt) }}</template>
        <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
        <template #cell-actions="{ item }">
          <BaseButton
            :variant="item.status === PROFILE_CHANGE_STATUSES.PENDING ? 'primary' : 'secondary'"
            size="sm"
            @click="openReview(item)"
          >
            {{
              item.status === PROFILE_CHANGE_STATUSES.PENDING
                ? t('entities.profileChanges.reviewAction')
                : t('entities.profileChanges.view')
            }}
          </BaseButton>
        </template>
      </DataTable>

      <template #footer>
        <Pagination
          v-if="status === 'success' && paginator.pagination.lastPage > 1"
          :page="paginator.pagination.page"
          :last-page="paginator.pagination.lastPage"
          @update:page="paginator.setPage($event)"
        />
      </template>
    </DataListCard>

    <ProfileChangeReviewDrawer v-model="review.open" :request-id="review.id" @decided="load" />
  </section>
</template>

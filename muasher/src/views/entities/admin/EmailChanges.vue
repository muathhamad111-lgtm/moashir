<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import { useEmailChangeApprovals } from '@/composables/useEmailChangeApprovals'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { EMAIL_CHANGE_STATUSES } from '@/constants/entityEnums'
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
  rejection,
  entityName,
  approve,
  startReject,
  reject,
  afterReject,
} = useEmailChangeApprovals()

const columns = computed(() =>
  ['entity', 'oldEmail', 'newEmail', 'requestedAt', 'status', 'actions'].map((key) => ({
    key,
    label: t(`entities.emailChanges.columns.${key}`),
  })),
)
</script>

<template>
  <section class="entities-admin">
    <header class="entities-admin__header">
      <div>
        <h1 class="entities-admin__title">{{ t('entities.emailChanges.title') }}</h1>
        <p class="entities-admin__subtitle">{{ t('entities.emailChanges.subtitle') }}</p>
      </div>
      <AppSelect
        variant="filter"
        :model-value="filters.filters.status"
        :options="statusOptions"
        :label="t('entities.filters.status')"
        @update:model-value="filters.setFilter('status', $event)"
      />
    </header>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('entities.emailChanges.emptyTitle')"
      :empty-message="t('entities.emailChanges.emptyMessage')"
      @retry="load"
    >
      <template #cell-entity="{ item }">
        <router-link
          v-if="item.entity"
          class="entity-table__name"
          :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: item.entity.id } }"
        >
          {{ entityName(item) }}
        </router-link>
      </template>
      <template #cell-oldEmail="{ item }"
        ><span class="u-ltr">{{ item.oldEmail }}</span></template
      >
      <template #cell-newEmail="{ item }"
        ><span class="u-ltr">{{ item.newEmail }}</span></template
      >
      <template #cell-requestedAt="{ item }">{{ dateTime(item.requestedAt) }}</template>
      <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
      <template #cell-actions="{ item }">
        <span
          v-if="item.status === EMAIL_CHANGE_STATUSES.AWAITING_APPROVAL"
          class="entities-admin__row-actions"
        >
          <BaseButton size="sm" variant="primary" icon="check" @click="approve(item)">
            {{ t('entities.emailChanges.approve') }}
          </BaseButton>
          <BaseButton size="sm" variant="danger" icon="x" @click="startReject(item)">
            {{ t('entities.emailChanges.reject') }}
          </BaseButton>
        </span>
        <span v-else-if="item.decisionReason" class="entities-admin__muted">{{
          item.decisionReason
        }}</span>
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && paginator.pagination.lastPage > 1"
      :page="paginator.pagination.page"
      :last-page="paginator.pagination.lastPage"
      @update:page="paginator.setPage($event)"
    />

    <ReasonDialog
      v-model="rejection.open"
      :title="t('entities.emailChanges.rejectTitle')"
      :message="t('entities.emailChanges.rejectMessage')"
      :submit-label="t('entities.emailChanges.reject')"
      :action="reject"
      action-name="entities.emailChanges.reject.submit"
      @done="afterReject"
    />
  </section>
</template>

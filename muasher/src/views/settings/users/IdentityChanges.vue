<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataListCard from '@/components/data-list/DataListCard.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import RowActions from '@/components/data-list/RowActions.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import IdentityChangeDetail from '@/components/identity/IdentityChangeDetail.vue'
import { useIdentityChangeReviews } from '@/composables/useIdentityChangeReviews'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { IDENTITY_CHANGE_STATUSES, IDENTITY_CHANGE_KINDS } from '@/constants/identityChanges'

const { t, locale } = useI18n()
const { dateTime } = useEntityDisplay()
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  statusOptions,
  decision,
  detail,
  DECISIONS,
  view,
  startDecision,
  decide,
  afterDecision,
} = useIdentityChangeReviews()

const INLINE_KEYS = ['view', DECISIONS.APPROVE, DECISIONS.REJECT]

function actionsFor(item) {
  const viewAction = { key: 'view', label: t('nationalIdentity.review.view'), icon: 'eye' }
  if (item.status !== IDENTITY_CHANGE_STATUSES.PENDING) return [viewAction]
  return [
    viewAction,
    {
      key: DECISIONS.APPROVE,
      label: t('nationalIdentity.review.approve'),
      icon: 'check',
      variant: 'success',
    },
    {
      key: DECISIONS.REJECT,
      label: t('nationalIdentity.review.reject'),
      icon: 'x',
      variant: 'danger',
    },
  ]
}

function act(key, item) {
  if (key === 'view') return view(item)
  return startDecision(key, item)
}

function fieldsLabel(item) {
  if (item.kind === IDENTITY_CHANGE_KINDS.FIRST_ENTRY)
    return t('nationalIdentity.review.firstEntry')
  const labels = item.fields.map((field) => t(`nationalIdentity.fields.${field}`))
  return new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(labels)
}

const columns = computed(() =>
  ['user', 'fields', 'requestedAt', 'status', 'actions'].map((key) => ({
    key,
    label: t(`nationalIdentity.review.columns.${key}`),
  })),
)

const decisionTitle = computed(() =>
  t(
    decision.kind === DECISIONS.APPROVE
      ? 'nationalIdentity.review.approveTitle'
      : 'nationalIdentity.review.rejectTitle',
  ),
)
</script>

<template>
  <section class="entities-admin">
    <header class="entities-admin__header">
      <div>
        <h1 class="entities-admin__title">{{ t('nationalIdentity.review.title') }}</h1>
      </div>
    </header>
    <DataListCard>
      <template #toolbar>
        <ListToolbar :searchable="false" :filter-state="filters">
          <template #filters="{ draft }">
            <AppSelect
              :model-value="draft.filters.status"
              :options="statusOptions"
              :label="t('nationalIdentity.review.statusFilter')"
              @update:model-value="draft.setFilter('status', $event)"
            />
          </template>
        </ListToolbar>
      </template>

      <DataTable
        table-key="users-identity-changes"
        :columns="columns"
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :empty-title="t('nationalIdentity.review.emptyTitle')"
        :empty-message="filters.isAtDefaults.value ? '' : t('nationalIdentity.review.emptyMessage')"
        @retry="load"
      >
        <template #cell-user="{ item }">{{ item.user?.name }}</template>
        <template #cell-fields="{ item }">{{ fieldsLabel(item) }}</template>
        <template #cell-requestedAt="{ item }">{{ dateTime(item.createdAt) }}</template>
        <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
        <template #cell-actions="{ item }">
          <RowActions
            :actions="actionsFor(item)"
            :name="item.user?.name ?? ''"
            :inline-keys="INLINE_KEYS"
            @select="act($event, item)"
          />
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

    <IdentityChangeDetail
      v-model="detail.open"
      :status="detail.status"
      :change="detail.change"
      @approve="startDecision(DECISIONS.APPROVE, $event)"
      @reject="startDecision(DECISIONS.REJECT, $event)"
    />

    <ReasonDialog
      v-model="decision.open"
      :title="decisionTitle"
      :optional="decision.kind === DECISIONS.APPROVE"
      :destructive="decision.kind === DECISIONS.REJECT"
      :reason-label="t('nationalIdentity.review.note')"
      :submit-label="
        t(
          decision.kind === DECISIONS.APPROVE
            ? 'nationalIdentity.review.approve'
            : 'nationalIdentity.review.reject',
        )
      "
      :action="decide"
      action-name="nationalIdentity.review.decide"
      @done="afterDecision"
    />
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import PrivacyRequestForm from '@/components/entities/admin/PrivacyRequestForm.vue'
import ContactPrivacyTools from '@/components/entities/admin/ContactPrivacyTools.vue'
import { PRIVACY_DECISIONS, usePrivacyRequests } from '@/composables/usePrivacyRequests'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { useToast } from '@/composables/useToast'
import { PRIVACY_REQUEST_STATUSES } from '@/constants/entityEnums'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const toast = useToast()
const { localized, dateTime } = useEntityDisplay()
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  statusOptions,
  kindOptions,
  decision,
  startDecision,
  decide,
  afterDecision,
} = usePrivacyRequests()
const formOpen = ref(false)

const columns = computed(() =>
  ['entity', 'kind', 'details', 'createdAt', 'status', 'actions'].map((key) => ({
    key,
    label: t(`entities.privacy.columns.${key}`),
  })),
)

function decisionsFor(request) {
  if (request.status !== PRIVACY_REQUEST_STATUSES.OPEN) return []
  return [
    {
      key: PRIVACY_DECISIONS.FULFIL,
      label: t('entities.privacy.fulfil'),
      icon: 'check',
    },
    {
      key: PRIVACY_DECISIONS.REJECT,
      label: t('entities.privacy.reject'),
      icon: 'x',
      variant: 'danger',
    },
  ]
}

async function onCreated() {
  toast.success(t('entities.privacy.toasts.created'))
  await load()
}
</script>

<template>
  <section class="entities-admin">
    <header class="entities-admin__header">
      <div>
        <h1 class="entities-admin__title">{{ t('entities.privacy.title') }}</h1>
        <p class="entities-admin__subtitle">{{ t('entities.privacy.subtitle') }}</p>
      </div>
      <BaseButton variant="primary" size="sm" icon="plus" @click="formOpen = true">{{
        t('entities.privacy.add')
      }}</BaseButton>
    </header>

    <div class="entities-admin__filters">
      <AppSelect
        variant="filter"
        :model-value="filters.filters.status"
        :options="statusOptions"
        :label="t('entities.filters.status')"
        @update:model-value="filters.setFilter('status', $event)"
      />
      <AppSelect
        variant="filter"
        :model-value="filters.filters.kind"
        :options="kindOptions"
        :label="t('entities.privacy.kind')"
        @update:model-value="filters.setFilter('kind', $event)"
      />
    </div>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('entities.privacy.emptyTitle')"
      :empty-message="t('entities.privacy.emptyMessage')"
      @retry="load"
    >
      <template #cell-entity="{ item }">
        <router-link
          v-if="item.entity"
          class="entity-table__name"
          :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: item.entity.id } }"
        >
          {{ localized(item.entity.name) }}
        </router-link>
      </template>
      <template #cell-kind="{ item }">{{ t(`entities.enums.privacyKind.${item.kind}`) }}</template>
      <template #cell-details="{ item }">
        <span class="entities-admin__details">{{ item.details }}</span>
        <span v-if="item.decisionNote" class="entities-admin__muted">{{ item.decisionNote }}</span>
      </template>
      <template #cell-createdAt="{ item }">{{ dateTime(item.createdAt) }}</template>
      <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
      <template #cell-actions="{ item }">
        <AppMenu
          v-if="decisionsFor(item).length"
          :actions="decisionsFor(item)"
          :label="
            item.entity
              ? t('components.actions.menuFor', { name: localized(item.entity.name) })
              : ''
          "
          @select="startDecision($event, item)"
        />
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && paginator.pagination.lastPage > 1"
      :page="paginator.pagination.page"
      :last-page="paginator.pagination.lastPage"
      @update:page="paginator.setPage($event)"
    />

    <ContactPrivacyTools />

    <ReasonDialog
      :key="decision.kind"
      v-model="decision.open"
      :title="t(`entities.privacy.${decision.kind ?? 'fulfil'}Title`)"
      :subtitle="t('entities.privacy.decisionMessage')"
      :destructive="decision.kind === PRIVACY_DECISIONS.REJECT"
      :submit-label="t(`entities.privacy.${decision.kind ?? 'fulfil'}`)"
      :reason-label="t('entities.privacy.noteLabel')"
      :optional="decision.kind === PRIVACY_DECISIONS.FULFIL"
      :action="decide"
      :action-name="`privacyRequests.${decision.kind}.submit`"
      @done="afterDecision"
    />
    <PrivacyRequestForm v-model="formOpen" @created="onCreated" />
  </section>
</template>

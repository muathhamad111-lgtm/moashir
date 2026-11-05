<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Pagination from '@/components/data-list/Pagination.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AuditLogFilters from '@/components/audit/AuditLogFilters.vue'
import AuditLogTable from '@/components/audit/AuditLogTable.vue'
import AuditEntryDialog from '@/components/audit/AuditEntryDialog.vue'
import { useAuditLog } from '@/composables/useAuditLog'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS } from '@/constants/permissionKeys'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { can } = usePermissions()
const auth = useAuthStore()
const audit = useAuditLog()
const { draft, paginator, forbidden, exporting, isFiltered, items, status, errorMessage } = audit
const exportOpen = ref(false)
const selected = ref(null)
const detailOpen = ref(false)

useHead({ title: () => t('auditLog.title') })

function open(entry) {
  selected.value = entry
  detailOpen.value = true
}

function exportWith(reason) {
  return audit.exportCsv(reason)
}
</script>

<template>
  <section class="audit-log">
    <header class="audit-log__header">
      <div>
        <h1 class="audit-log__title">{{ t('auditLog.title') }}</h1>
        <p class="audit-log__subtitle">{{ t('auditLog.subtitle') }}</p>
      </div>
      <BaseButton
        v-if="can(PERMISSIONS.AUDIT_EXPORT) && !forbidden"
        variant="secondary"
        icon="download"
        :loading="exporting"
        @click="exportOpen = true"
        >{{ t('auditLog.export') }}</BaseButton
      >
    </header>

    <EmptyState
      v-if="forbidden"
      icon="lock"
      :title="t('integrations.forbidden.title')"
      :message="t('integrations.forbidden.message')"
    />
    <template v-else>
      <p class="audit-log__note" role="note">
        <AppIcon name="shield-info" />
        {{ t('auditLog.appendOnly') }}
      </p>
      <AuditLogFilters
        :filters="draft"
        :error-of="audit.filterError"
        :filtered="isFiltered"
        @update="draft[$event.key] = $event.value"
        @apply="audit.apply"
        @reset="audit.reset"
      />
      <AuditLogTable
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :filtered="isFiltered"
        @open="open"
        @retry="audit.load"
      />
      <Pagination
        v-if="status === 'success' && paginator.pagination.lastPage > 1"
        :page="paginator.pagination.page"
        :last-page="paginator.pagination.lastPage"
        @update:page="paginator.setPage($event)"
      />
    </template>

    <AuditEntryDialog v-model="detailOpen" :entry="selected" />
    <ReasonDialog
      v-model="exportOpen"
      :title="t('auditLog.exportTitle')"
      :message="t('auditLog.exportMessage')"
      :submit-label="t('auditLog.export')"
      :optional="!auth.isSuperAdmin"
      :action="exportWith"
      action-name="auditLog.export"
    />
  </section>
</template>

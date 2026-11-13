<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import Badge from '@/components/ui/Badge.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import PermissionForm from '@/components/permissions/PermissionForm.vue'
import { useToast } from '@/composables/useToast'
import { usePermissions } from '@/composables/usePermissions'
import { usePermissionMatrix } from '@/composables/usePermissionMatrix'
import { usePermissionsCatalog } from '@/composables/usePermissionsCatalog'
import { PERMISSIONS } from '@/constants/permissionKeys'

const { t } = useI18n()
const toast = useToast()
const { can } = usePermissions()
const { rows, status, editableRoles, load, save } = usePermissionMatrix()
const { localized, groupLabel, roleLabel, groupFilterOptions } = usePermissionsCatalog()

const canManage = computed(() => can(PERMISSIONS.ROLES_MATRIX_MANAGE))
const search = ref('')
const groupFilter = ref('')

onMounted(() => {
  load().catch(() => {})
})

const filteredRows = computed(() => {
  const term = search.value.trim().toLowerCase()
  return rows.value.filter((item) => {
    if (groupFilter.value && item.module !== groupFilter.value) return false
    if (!term) return true
    const haystack = [item.key, item.label?.ar, item.label?.en]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return haystack.includes(term)
  })
})

const tableStatus = computed(() => {
  if (status.value === 'loading' || status.value === 'idle') return 'loading'
  if (status.value === 'error') return 'error'
  return filteredRows.value.length ? 'success' : 'empty'
})

const statCards = computed(() => [
  {
    id: 'total',
    icon: 'shield-info',
    value: rows.value.length,
    label: t('permissions.stats.total'),
  },
  {
    id: 'locked',
    icon: 'lock',
    value: rows.value.filter((item) => item.lockedToRoles?.length).length,
    label: t('permissions.stats.locked'),
  },
  {
    id: 'confidential',
    icon: 'user-shield',
    value: rows.value.filter((item) => item.confidential).length,
    label: t('permissions.stats.confidential'),
  },
])

const columns = computed(() => [
  { key: 'name', label: t('permissions.columns.name') },
  { key: 'key', label: t('permissions.columns.key') },
  { key: 'group', label: t('permissions.columns.group') },
  { key: 'roles', label: t('permissions.columns.roles') },
  ...(canManage.value ? [{ key: 'actions', label: t('components.actions.label') }] : []),
])

const rowActions = computed(() => [
  { key: 'edit', label: t('permissions.actions.edit'), icon: 'pen' },
])

const formOpen = ref(false)
const editing = ref(null)

function openEdit(row) {
  editing.value = row
  formOpen.value = true
}

async function onSaved(changes) {
  try {
    await save(editing.value, changes)
    toast.success(t('permissions.toasts.updated'))
    formOpen.value = false
  } catch {
    toast.error(t('permissions.toasts.saveFailed'))
  }
}
</script>

<template>
  <section class="permissions-page">
    <header class="permissions-page__header">
      <div>
        <h1 class="permissions-page__title">{{ t('permissions.index.title') }}</h1>
        <p class="permissions-page__subtitle">{{ t('permissions.index.subtitle') }}</p>
      </div>
    </header>

    <StatsCards :stats="statCards" :loading="tableStatus === 'loading' && !rows.length" />

    <ListToolbar
      v-model:search="search"
      :search-placeholder="t('permissions.index.searchPlaceholder')"
    >
      <template #filters>
        <AppSelect
          variant="filter"
          :model-value="groupFilter"
          :options="groupFilterOptions"
          :label="t('permissions.filters.group')"
          @update:model-value="groupFilter = $event"
        />
      </template>
    </ListToolbar>

    <DataTable
      :columns="columns"
      :items="filteredRows"
      :status="tableStatus"
      :empty-title="t('permissions.index.emptyTitle')"
      :empty-message="t('permissions.index.emptyMessage')"
      :error-message="t('permissions.index.errorMessage')"
      row-key="key"
      @retry="load"
    >
      <template #cell-name="{ item }">
        <div class="permissions-page__name">
          <span class="permissions-page__name-label">{{ localized(item.label) }}</span>
          <span class="permissions-page__name-desc">{{ localized(item.description) }}</span>
        </div>
      </template>
      <template #cell-key="{ item }">
        <code class="permissions-page__key">{{ item.key }}</code>
      </template>
      <template #cell-group="{ item }">
        <Badge variant="info">{{ groupLabel(item.module) }}</Badge>
      </template>
      <template #cell-roles="{ item }">
        <div class="permissions-page__roles">
          <Badge v-if="item.lockedToRoles?.length" variant="warning">{{
            t('permissions.lockedBadge')
          }}</Badge>
          <Badge v-for="role in item.roles" :key="role" variant="muted">{{
            roleLabel(role)
          }}</Badge>
          <span v-if="!item.roles.length" class="permissions-page__roles-empty">{{
            t('permissions.rolesNone')
          }}</span>
        </div>
      </template>
      <template #cell-actions="{ item }">
        <AppMenu
          :actions="rowActions"
          :label="t('components.actions.menuFor', { name: localized(item.label) })"
          @select="openEdit(item)"
        />
      </template>
    </DataTable>

    <PermissionForm
      v-model="formOpen"
      :permission="editing"
      :editable-roles="editableRoles"
      @saved="onSaved"
    />
  </section>
</template>

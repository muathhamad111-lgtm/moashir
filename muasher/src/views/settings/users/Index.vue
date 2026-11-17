<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import UserForm from '@/components/users/UserForm.vue'
import UserStatusBadge from '@/components/users/UserStatusBadge.vue'
import RowActions from '@/components/data-list/RowActions.vue'
import UserPathAccessCell from '@/components/users/UserPathAccessCell.vue'
import AssessorQualificationsDialog from '@/components/users/AssessorQualificationsDialog.vue'
import RequestScopeDialog from '@/components/users/RequestScopeDialog.vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { useSearchPrefill } from '@/composables/useSearchPrefill'
import { usePagination } from '@/composables/usePagination'
import { useToast } from '@/composables/useToast'
import { useUsers } from '@/composables/useUsers'
import { useUserActions } from '@/composables/useUserActions'
import { useUserPathAccess } from '@/composables/useUserPathAccess'
import { useUserStatCards } from '@/composables/useUserStatCards'
import { useUsersExport } from '@/composables/useUsersExport'
import { usePermissions } from '@/composables/usePermissions'
import { usersService } from '@/services/usersService'
import { formatDate } from '@/utils/datetime'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { accessFor } from '@/constants/routeAccess'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { PATH_ACCESS_ACTIONS, USER_ACTIONS, USER_INLINE_ACTIONS } from '@/constants/users'

const { t, locale } = useI18n()
const toast = useToast()
const { can, canAccess } = usePermissions()
const { roleLabel, roleFilterOptions, statusFilterOptions } = useUsers()

const filters = useFilters({ role: '', status: '' })
const { search } = filters
useSearchPrefill(search, ROUTE_NAMES.ADMIN_USERS)
const paginator = usePagination({ perPage: 10 })
const { pagination, setPage } = paginator
const stats = ref(null)
const canManage = computed(() => can(PERMISSIONS.USERS_MANAGE))
const canViewRoles = computed(() => canAccess(accessFor(ROUTE_NAMES.ADMIN_PERMISSIONS)))

const { items, status, errorMessage, load } = useTable({
  fetcher: async (params) => {
    const envelope = await usersService.list(params)
    stats.value = envelope.meta?.stats ?? null
    return envelope
  },
  filters,
  pagination: paginator,
})

const actions = useUserActions({ onChanged: () => load() })

const statCards = useUserStatCards(stats)
const exporter = useUsersExport()
const router = useRouter()

const columns = computed(() => [
  { key: 'name', label: t('users.columns.name') },
  { key: 'email', label: t('users.columns.email') },
  { key: 'role', label: t('users.columns.role') },
  { key: 'paths', label: t('users.columns.paths') },
  { key: 'status', label: t('users.columns.status') },
  { key: 'lastLogin', label: t('users.columns.lastLogin'), date: true },
  ...(canManage.value ? [{ key: 'actions', label: t('components.actions.label') }] : []),
])

function lastLoginLabel(value) {
  if (!value) return t('users.lastLogin.never')
  return formatDate(value, locale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

const formOpen = ref(false)
const editing = ref(null)
const pathAccess = useUserPathAccess({ onSaved: () => load() })

function openCreate() {
  editing.value = null
  formOpen.value = true
}

async function save({ id, payload }) {
  if (id) await usersService.update(id, payload)
  else await usersService.create(payload)
}

function onSaved({ id }) {
  toast.success(id ? t('users.toasts.updated') : t('users.toasts.created'))
  load()
}

function onAction(key, row) {
  if (key === USER_ACTIONS.EDIT) {
    editing.value = row
    formOpen.value = true
    return
  }
  if (key === USER_ACTIONS.ACTIVITY)
    return router.push({ name: ROUTE_NAMES.ADMIN_AUDIT_LOG, query: { actorId: row.id } })
  if (PATH_ACCESS_ACTIONS.includes(key)) return pathAccess.open(key, row)
  actions.start(key, row)
}
</script>

<template>
  <section class="users-page">
    <header class="users-page__header">
      <h1 class="users-page__title">{{ t('users.index.title') }}</h1>
      <BaseButton
        v-if="canViewRoles"
        variant="secondary"
        icon="lock"
        :to="{ name: ROUTE_NAMES.ADMIN_PERMISSIONS }"
        >{{ t('users.index.rolesLink') }}</BaseButton
      >
    </header>

    <StatsCards :stats="statCards" :loading="status === 'loading' && !stats" />

    <ListToolbar
      v-model:search="search"
      :search-placeholder="t('users.index.searchPlaceholder')"
      :filter-state="filters"
    >
      <template #filters="{ draft }">
        <AppSelect
          :model-value="draft.filters.role"
          :options="roleFilterOptions"
          :label="t('users.filters.role')"
          @update:model-value="draft.setFilter('role', $event)"
        />
        <AppSelect
          :model-value="draft.filters.status"
          :options="statusFilterOptions"
          :label="t('users.filters.status')"
          @update:model-value="draft.setFilter('status', $event)"
        />
      </template>
      <template v-if="can(PERMISSIONS.USERS_EXPORT)" #export>
        <BaseButton
          variant="secondary"
          size="sm"
          icon="download"
          :loading="exporter.exporting.value"
          :disabled="status !== 'success'"
          @click="exporter.exportCsv(filters.toParams())"
        >
          {{ t('users.index.export') }}
        </BaseButton>
      </template>
      <template v-if="canManage" #actions>
        <BaseButton variant="primary" size="sm" icon="user" @click="openCreate">{{
          t('users.index.add')
        }}</BaseButton>
      </template>
    </ListToolbar>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('users.index.emptyTitle')"
      :empty-message="t('users.index.emptyMessage')"
      @retry="load"
    >
      <template v-if="canManage" #empty-action>
        <BaseButton variant="primary" icon="user" @click="openCreate">{{
          t('users.index.add')
        }}</BaseButton>
      </template>
      <template #cell-name="{ item }">
        <span class="users-page__name">{{ item.name }}</span>
      </template>
      <template #cell-email="{ item }">
        <a class="users-page__email" :href="`mailto:${item.email}`" dir="ltr">{{ item.email }}</a>
      </template>
      <template #cell-role="{ item }">
        <span class="users-page__roles">
          <Badge v-for="roleCode in item.roles" :key="roleCode" variant="info">{{
            roleLabel(roleCode)
          }}</Badge>
        </span>
      </template>
      <template #cell-paths="{ item }"><UserPathAccessCell :user="item" /></template>
      <template #cell-status="{ item }">
        <UserStatusBadge :status="item.status" />
      </template>
      <template #cell-lastLogin="{ item }">
        <span class="users-page__last-login">{{ lastLoginLabel(item.lastLoginAt) }}</span>
      </template>
      <template #cell-actions="{ item }">
        <RowActions
          :actions="actions.actionsFor(item)"
          :name="item.name"
          :inline-keys="USER_INLINE_ACTIONS"
          @select="onAction($event, item)"
        />
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      :disabled="status === 'loading'"
      @update:page="setPage($event)"
    />

    <UserForm v-if="canManage" v-model="formOpen" :user="editing" :save="save" @saved="onSaved" />
    <AssessorQualificationsDialog
      v-if="canManage"
      v-model="pathAccess.qualificationsOpen.value"
      :user="pathAccess.user.value"
      @saved="pathAccess.saved"
    />
    <RequestScopeDialog
      v-if="canManage"
      v-model="pathAccess.scopeOpen.value"
      :user="pathAccess.user.value"
      @saved="pathAccess.saved"
    />
    <ReasonDialog
      v-if="actions.dialog.value"
      v-model="actions.dialogOpen.value"
      :title="actions.dialog.value.title"
      :subtitle="actions.dialog.value.subtitle"
      :destructive="actions.dialog.value.destructive"
      :submit-label="actions.dialog.value.submitLabel"
      :optional="actions.dialog.value.optional"
      :action="actions.runWithReason"
      action-name="users.statusAction"
    />
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Badge from '@/components/ui/Badge.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import UserForm from '@/components/users/UserForm.vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { useSearchPrefill } from '@/composables/useSearchPrefill'
import { usePagination } from '@/composables/usePagination'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useUsers } from '@/composables/useUsers'
import { usersService } from '@/services/usersService'
import { formatDate } from '@/utils/datetime'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t, locale } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()
const { roleLabel, roleFilterOptions, statusFilterOptions } = useUsers()

const filters = useFilters({ role: '', status: '' })
const { search } = filters
useSearchPrefill(search, ROUTE_NAMES.ADMIN_USERS)
const paginator = usePagination({ perPage: 10 })
const { pagination, setPage } = paginator
const stats = ref(null)

const { items, status, errorMessage, load } = useTable({
  fetcher: async (params) => {
    const envelope = await usersService.list(params)
    stats.value = envelope.meta?.stats ?? null
    return envelope
  },
  filters,
  pagination: paginator,
})

const statCards = computed(() => [
  {
    id: 'total',
    icon: 'fa-solid fa-users',
    value: stats.value?.total ?? 0,
    label: t('users.stats.total'),
  },
  {
    id: 'active',
    icon: 'fa-solid fa-user-check',
    value: stats.value?.active ?? 0,
    label: t('users.stats.active'),
  },
  {
    id: 'inactive',
    icon: 'fa-solid fa-user-slash',
    value: stats.value?.inactive ?? 0,
    label: t('users.stats.inactive'),
  },
  {
    id: 'roles',
    icon: 'fa-solid fa-user-shield',
    value: Object.keys(stats.value?.byRole ?? {}).length,
    label: t('users.stats.roles'),
  },
])

const columns = computed(() => [
  { key: 'name', label: t('users.columns.name') },
  { key: 'email', label: t('users.columns.email') },
  { key: 'role', label: t('users.columns.role') },
  { key: 'status', label: t('users.columns.status') },
  { key: 'lastLogin', label: t('users.columns.lastLogin') },
  { key: 'actions', label: t('components.actions.label') },
])

function rowActions(row) {
  return [
    { key: 'edit', label: t('users.actions.edit'), icon: 'fa-solid fa-pen' },
    row.status === 'active'
      ? {
          key: 'deactivate',
          label: t('users.actions.deactivate'),
          icon: 'fa-solid fa-user-slash',
          variant: 'danger',
        }
      : { key: 'activate', label: t('users.actions.activate'), icon: 'fa-solid fa-user-check' },
  ]
}

function lastLoginLabel(value) {
  if (!value) return t('users.lastLogin.never')
  return formatDate(value, locale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

const formOpen = ref(false)
const editing = ref(null)

function openCreate() {
  editing.value = null
  formOpen.value = true
}

function openEdit(row) {
  editing.value = row
  formOpen.value = true
}

async function onSaved({ id, payload }) {
  try {
    if (id) {
      await usersService.update(id, payload)
      toast.success(t('users.toasts.updated'))
    } else {
      await usersService.create(payload)
      toast.success(t('users.toasts.created'))
    }
    formOpen.value = false
    await load()
  } catch {
    toast.error(t('users.toasts.saveFailed'))
  }
}

async function activateUser(row) {
  const accepted = await confirm({
    title: t('users.confirm.activateTitle'),
    message: t('users.confirm.activateMessage', { name: row.name }),
    confirmLabel: t('users.actions.activate'),
    variant: 'primary',
  })
  if (!accepted) return
  try {
    await usersService.activate(row.id)
    toast.success(t('users.toasts.activated'))
    await load()
  } catch {
    toast.error(t('users.toasts.statusFailed'))
  }
}

async function deactivateUser(row) {
  const accepted = await confirm({
    title: t('users.confirm.deactivateTitle'),
    message: t('users.confirm.deactivateMessage', { name: row.name }),
    confirmLabel: t('users.actions.deactivate'),
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await usersService.deactivate(row.id)
    toast.success(t('users.toasts.deactivated'))
    await load()
  } catch {
    toast.error(t('users.toasts.statusFailed'))
  }
}

function onAction(key, row) {
  if (key === 'edit') openEdit(row)
  else if (key === 'activate') activateUser(row)
  else if (key === 'deactivate') deactivateUser(row)
}
</script>

<template>
  <section class="users-page">
    <header class="users-page__header">
      <div>
        <h1 class="users-page__title">{{ t('users.index.title') }}</h1>
        <p class="users-page__subtitle">{{ t('users.index.subtitle') }}</p>
      </div>
    </header>

    <StatsCards :stats="statCards" :loading="status === 'loading' && !stats" />

    <ListToolbar v-model:search="search" :search-placeholder="t('users.index.searchPlaceholder')">
      <template #filters>
        <DropdownList
          :model-value="filters.filters.role"
          :options="roleFilterOptions"
          :placeholder="t('users.filters.role')"
          @update:model-value="filters.setFilter('role', $event)"
        />
        <DropdownList
          :model-value="filters.filters.status"
          :options="statusFilterOptions"
          :placeholder="t('users.filters.status')"
          @update:model-value="filters.setFilter('status', $event)"
        />
      </template>
      <template #actions>
        <BaseButton variant="primary" size="sm" icon="fa-solid fa-user-plus" @click="openCreate">
          {{ t('users.index.add') }}
        </BaseButton>
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
      <template #empty-action>
        <BaseButton variant="primary" icon="fa-solid fa-user-plus" @click="openCreate">
          {{ t('users.index.add') }}
        </BaseButton>
      </template>
      <template #cell-name="{ item }">
        <span class="users-page__name">{{ item.name }}</span>
      </template>
      <template #cell-email="{ item }">
        <a class="users-page__email" :href="`mailto:${item.email}`" dir="ltr">{{ item.email }}</a>
      </template>
      <template #cell-role="{ item }">
        <span class="users-page__roles">
          <Badge v-for="roleCode in item.roles" :key="roleCode" variant="info">
            {{ roleLabel(roleCode) }}
          </Badge>
        </span>
      </template>
      <template #cell-status="{ item }">
        <StatusBadge :status="item.status" />
      </template>
      <template #cell-lastLogin="{ item }">
        <span class="users-page__last-login">{{ lastLoginLabel(item.lastLogin) }}</span>
      </template>
      <template #cell-actions="{ item }">
        <ActionsDropdown :actions="rowActions(item)" @select="onAction($event, item)" />
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      :disabled="status === 'loading'"
      @update:page="setPage($event)"
    />

    <UserForm v-model="formOpen" :user="editing" @saved="onSaved" />
  </section>
</template>

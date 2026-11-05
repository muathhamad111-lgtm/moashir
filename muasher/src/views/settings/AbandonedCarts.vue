<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import Badge from '@/components/ui/Badge.vue'
import FinanceNav from '@/components/finance/FinanceNav.vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { messagingService } from '@/services/messagingService'
import { PAYMENT_PURPOSE_VALUES } from '@/constants/payments'
import { ABANDONED_CART_CONTACTED } from '@/constants/finance'
import { formatDate } from '@/utils/datetime'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'
import { MOCK_MODULES } from '@/constants/mockModules'

const CHANNELS = ['email', 'sms']

const { t, locale } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()
const { items, status, errorMessage, load, filters, paginator, searchInput, setFilter } =
  useFinanceList({
    fetcher: (params) => messagingService.listAbandonedCarts(params),
    initialFilters: { purpose: '', contacted: '' },
  })
const { pagination, setPage } = paginator

const purposeOptions = computed(() => [
  { value: '', label: t('settings.carts.allPurposes') },
  ...PAYMENT_PURPOSE_VALUES.map((value) => ({ value, label: t(`finance.purposes.${value}`) })),
])
const contactedOptions = computed(() => [
  { value: '', label: t('settings.carts.allContacts') },
  ...ABANDONED_CART_CONTACTED.map((value) => ({
    value,
    label: t(`settings.carts.contacted.${value}`),
  })),
])

const columns = computed(() => [
  { key: 'entity', label: t('settings.carts.columns.entity') },
  { key: 'contact', label: t('settings.carts.columns.contact') },
  { key: 'purpose', label: t('settings.carts.columns.purpose') },
  { key: 'startedAt', label: t('settings.carts.columns.startedAt') },
  { key: 'lastSeenAt', label: t('settings.carts.columns.lastSeenAt') },
  { key: 'contactedAt', label: t('settings.carts.columns.contactedAt') },
  { key: 'actions', label: t('components.actions.label') },
])

function nameOf(entity) {
  return entity.name?.[locale.value] || entity.name?.ar || ''
}

function dateOf(value) {
  return value
    ? formatDate(value, locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Riyadh',
      })
    : t('finance.common.none')
}

function rowActions(cart) {
  if (!cart.marketingConsent) return []
  return CHANNELS.map((channel) => ({
    key: channel,
    label: t(`settings.carts.actions.${channel}`),
    icon: channel === 'email' ? 'fa-solid fa-envelope' : 'fa-solid fa-comment-sms',
  }))
}

async function runCampaign(channel, cart) {
  const accepted = await confirm({
    title: t('settings.carts.confirmTitle'),
    message: t('settings.carts.confirmMessage', {
      entity: nameOf(cart.entity),
      channel: t(`settings.carts.channels.${channel}`),
    }),
    confirmLabel: t('settings.carts.send'),
    variant: 'primary',
  })
  if (!accepted) return
  try {
    await messagingService.sendCartCampaign({ channel, cartIds: [cart.id] })
    toast.success(t('settings.carts.campaignQueued'))
  } catch (error) {
    toast.error(error?.normalized?.message || t('settings.carts.campaignFailed'))
  }
}

const DEMO_MODULES = [MOCK_MODULES.MESSAGING]
</script>

<template>
  <SettingsSection
    :title="t('settings.carts.title')"
    :description="t('settings.carts.description')"
    status="success"
  >
    <FinanceNav />
    <DemoDataNotice scope="actions" :modules="DEMO_MODULES" />
    <ListToolbar
      v-model:search="searchInput"
      :search-placeholder="t('settings.carts.searchPlaceholder')"
    >
      <template #filters>
        <DropdownList
          :model-value="filters.filters.purpose"
          :options="purposeOptions"
          :placeholder="t('settings.carts.filterPurpose')"
          @update:model-value="setFilter('purpose', $event)"
        />
        <DropdownList
          :model-value="filters.filters.contacted"
          :options="contactedOptions"
          :placeholder="t('settings.carts.filterContacted')"
          @update:model-value="setFilter('contacted', $event)"
        />
      </template>
    </ListToolbar>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('settings.carts.emptyTitle')"
      :empty-message="t('settings.carts.emptyMessage')"
      :skeleton-rows="6"
      @retry="load"
    >
      <template #cell-entity="{ item }">
        <span class="settings-form__entity">{{ nameOf(item.entity) }}</span>
        <bdi class="settings-form__muted" dir="ltr">{{ item.entity.reference }}</bdi>
      </template>
      <template #cell-contact="{ item }">
        <div class="settings-form__contact">
          <bdi dir="ltr">{{ item.email ?? t('finance.common.none') }}</bdi>
          <Badge :variant="item.marketingConsent ? 'success' : 'muted'">
            {{
              t(item.marketingConsent ? 'settings.carts.consent.yes' : 'settings.carts.consent.no')
            }}
          </Badge>
        </div>
      </template>
      <template #cell-purpose="{ item }"
        ><Badge variant="info">{{ t(`finance.purposes.${item.purpose}`) }}</Badge></template
      >
      <template #cell-startedAt="{ item }"
        ><span class="settings-form__nowrap">{{ dateOf(item.startedAt) }}</span></template
      >
      <template #cell-lastSeenAt="{ item }"
        ><span class="settings-form__nowrap">{{ dateOf(item.lastSeenAt) }}</span></template
      >
      <template #cell-contactedAt="{ item }"
        ><span class="settings-form__nowrap">{{ dateOf(item.contactedAt) }}</span></template
      >
      <template #cell-actions="{ item }">
        <ActionsDropdown
          v-if="item.marketingConsent"
          :actions="rowActions(item)"
          @select="runCampaign($event, item)"
        />
        <span v-else class="settings-form__muted">{{ t('settings.carts.noConsent') }}</span>
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      @update:page="setPage($event)"
    />
  </SettingsSection>
</template>

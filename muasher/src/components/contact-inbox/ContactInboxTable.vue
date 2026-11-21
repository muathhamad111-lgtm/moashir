<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import MessageStatusBadge from '@/components/messages/MessageStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { formatNumber } from '@/utils/formatNumber'

defineProps({
  items: { type: Array, default: () => [] },
  status: { type: String, default: 'loading' },
  errorMessage: { type: String, default: '' },
  filtered: { type: Boolean, default: false },
})

const emit = defineEmits(['retry'])
const { t, n, locale } = useI18n()

const columns = computed(() =>
  ['reference', 'sender', 'enquiryType', 'status', 'assignee', 'lastActivityAt', 'entries'].map(
    (key) => ({ key, label: t(`contactInbox.columns.${key}`) }),
  ),
)
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t(filtered ? 'contactInbox.empty.filteredTitle' : 'contactInbox.empty.title')"
    :empty-message="
      t(filtered ? 'contactInbox.empty.filteredMessage' : 'contactInbox.empty.message')
    "
    @retry="emit('retry')"
  >
    <template #cell-reference="{ item }">
      <RouterLink
        class="contact-inbox__reference"
        :to="{ name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGE, params: { id: item.id } }"
      >
        <bdi dir="ltr">{{ item.reference }}</bdi>
      </RouterLink>
    </template>
    <template #cell-sender="{ item }">
      <span class="contact-inbox__sender">
        <span>{{ item.sender.name }}</span>
        <span v-if="item.sender.organization" class="contact-inbox__muted">{{
          item.sender.organization
        }}</span>
        <span class="contact-inbox__muted">{{
          item.isLinked ? t('contactInbox.linked') : t('contactInbox.guest')
        }}</span>
      </span>
    </template>
    <template #cell-enquiryType="{ item }">
      {{ t(`public.home.contact.form.enquiryTypes.${item.enquiryType}`) }}
    </template>
    <template #cell-status="{ item }"><MessageStatusBadge :status="item.status" /></template>
    <template #cell-assignee="{ item }">
      {{ item.assignee?.name || t('contactInbox.unassigned') }}
    </template>
    <template #cell-lastActivityAt="{ item }">
      <bdi>{{ formatRiyadhDateTime(item.lastActivityAt, locale) }}</bdi>
    </template>
    <template #cell-entries="{ item }">
      <bdi dir="ltr">{{ formatNumber(n, item.entryCount ?? 0) }}</bdi>
    </template>
  </DataTable>
</template>

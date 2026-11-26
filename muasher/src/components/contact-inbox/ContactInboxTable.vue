<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import ContactInboxStatusBadge from './ContactInboxStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { COLUMN_KINDS } from '@/utils/tableColumns'

defineProps({
  items: { type: Array, default: () => [] },
  status: { type: String, default: 'loading' },
  errorMessage: { type: String, default: '' },
  filtered: { type: Boolean, default: false },
})

// Messages that wait on the team read in bold, like unread mail.
const PENDING = ['new', 'awaiting-reply']

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()
const route = useRoute()

const COLUMN_KEYS = [
  'sender',
  'reference',
  'enquiryType',
  'status',
  'assignee',
  'lastActivityAt',
  'createdAt',
  'repliedAt',
  'closedAt',
  'closedBy',
  'entity',
  'organization',
  'language',
  'entries',
]
const COLUMN_KINDS_BY_KEY = { language: COLUMN_KINDS.STATUS }
const DATE_KEYS = ['createdAt', 'repliedAt', 'closedAt']

const columns = computed(() =>
  COLUMN_KEYS.map((key) => ({
    key,
    label: t(`contactInbox.columns.${key}`),
    kind: COLUMN_KINDS_BY_KEY[key],
  })),
)

function messageLink(item) {
  return { name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGE, params: { id: item.id }, query: route.query }
}
</script>

<template>
  <DataTable
    table-key="contact-inbox"
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    stacked
    :empty-title="t(filtered ? 'contactInbox.empty.filteredTitle' : 'contactInbox.empty.title')"
    :empty-message="
      t(filtered ? 'contactInbox.empty.filteredMessage' : 'contactInbox.empty.message')
    "
    @retry="emit('retry')"
  >
    <template #cell-sender="{ item }">
      <RouterLink
        class="contact-table__sender"
        :class="{ 'contact-table__sender--pending': PENDING.includes(item.inboxStatus) }"
        :data-message-id="item.id"
        :to="messageLink(item)"
      >
        <span class="contact-table__name" dir="auto">{{ item.sender.name }}</span>
        <span class="contact-table__excerpt" dir="auto">{{ item.excerpt }}</span>
      </RouterLink>
    </template>
    <template #cell-reference="{ item }"
      ><bdi dir="ltr">{{ item.reference }}</bdi></template
    >
    <template #cell-enquiryType="{ item }">
      {{ t(`public.home.contact.form.enquiryTypes.${item.enquiryType}`) }}
    </template>
    <template #cell-status="{ item }">
      <ContactInboxStatusBadge :status="item.inboxStatus" />
    </template>
    <template #cell-assignee="{ item }">
      <bdi>{{ item.assignee?.name || t('contactInbox.unassigned') }}</bdi>
    </template>
    <template #cell-lastActivityAt="{ item }">
      <bdi>{{ formatRiyadhDateTime(item.lastActivityAt, locale) }}</bdi>
    </template>
    <template v-for="key in DATE_KEYS" :key="key" #[`cell-${key}`]="{ item }">
      <bdi v-if="item[key]">{{ formatRiyadhDateTime(item[key], locale) }}</bdi>
    </template>
    <template #cell-closedBy="{ item }">{{ item.closedBy?.name }}</template>
    <template #cell-entity="{ item }">
      <span v-if="item.entity" class="contact-table__entity">
        <span>{{ localizedText(item.entity.name, locale) }}</span>
        <bdi dir="ltr">{{ item.entity.reference }}</bdi>
      </span>
    </template>
    <template #cell-organization="{ item }"
      ><span dir="auto">{{ item.sender.organization }}</span></template
    >
    <template #cell-language="{ item }">{{
      item.sender.locale ? t(`contactInbox.languages.${item.sender.locale}`) : ''
    }}</template>
    <template #cell-entries="{ item }">{{ item.entryCount }}</template>
  </DataTable>
</template>

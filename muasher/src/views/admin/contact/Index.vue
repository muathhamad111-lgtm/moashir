<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import ContactInboxFilters from '@/components/contact-inbox/ContactInboxFilters.vue'
import ContactInboxTable from '@/components/contact-inbox/ContactInboxTable.vue'
import { useContactInbox } from '@/composables/useContactInbox'
import { useContactAssignees } from '@/composables/useContactAssignees'
import { formatNumber } from '@/utils/formatNumber'

const { t, n } = useI18n()
const inbox = useContactInbox()
const { filters, searchInput, paginator, stats, isFiltered, items, status, errorMessage } = inbox
const { assignees, load: loadAssignees } = useContactAssignees()

useHead({ title: () => t('contactInbox.title') })

const STAT_ICONS = { new: 'envelope', inProgress: 'clock', replied: 'send', closed: 'check-circle' }
const statCards = computed(() =>
  Object.entries(STAT_ICONS).map(([key, icon]) => ({
    id: key,
    icon,
    value: formatNumber(n, stats.value?.[key] ?? 0),
    label: t(`contactInbox.stats.${key}`),
  })),
)

onMounted(loadAssignees)
</script>

<template>
  <section class="contact-inbox">
    <header class="contact-inbox__header">
      <h1 class="contact-inbox__title">{{ t('contactInbox.title') }}</h1>
      <p class="contact-inbox__subtitle">{{ t('contactInbox.subtitle') }}</p>
    </header>

    <StatsCards :stats="statCards" :loading="!stats" />

    <ContactInboxFilters
      :filters="filters"
      :search="searchInput"
      :assignees="assignees"
      :filtered="isFiltered"
      @update:search="searchInput = $event"
      @update:filter="inbox.setFilter($event.key, $event.value)"
      @reset="inbox.reset"
    />

    <ContactInboxTable
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :filtered="isFiltered"
      @retry="inbox.load"
    />

    <Pagination
      v-if="status === 'success' && paginator.pagination.lastPage > 1"
      :page="paginator.pagination.page"
      :last-page="paginator.pagination.lastPage"
      @update:page="paginator.setPage($event)"
    />
  </section>
</template>

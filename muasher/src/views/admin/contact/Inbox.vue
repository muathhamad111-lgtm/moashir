<script setup>
import { computed, nextTick, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { useRoute, useRouter } from 'vue-router'
import DataListCard from '@/components/data-list/DataListCard.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ContactStatusChips from '@/components/contact-inbox/ContactStatusChips.vue'
import ContactInboxFilters from '@/components/contact-inbox/ContactInboxFilters.vue'
import ContactInboxList from '@/components/contact-inbox/ContactInboxList.vue'
import ContactInboxTable from '@/components/contact-inbox/ContactInboxTable.vue'
import ContactMessagePanel from '@/components/contact-inbox/ContactMessagePanel.vue'
import { useContactInbox } from '@/composables/useContactInbox'
import { useContactAssignees } from '@/composables/useContactAssignees'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { useInboxKeyboard } from '@/composables/useInboxKeyboard'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const inbox = useContactInbox()
const { filters, searchInput, paginator, stats, items, status, errorMessage } = inbox
const { assignees, load: loadAssignees } = useContactAssignees()
const isPhone = useCompactViewport()

const selectedId = computed(() => (route.params.id ? String(route.params.id) : ''))
// With no message open the inbox is a full-width table; on a phone it stays the compact list.
const showTable = computed(() => !selectedId.value && !isPhone.value)
const filtered = computed(() => Boolean(filters.inboxStatus || searchInput.value))
const showPagination = computed(
  () => status.value === 'success' && paginator.pagination.lastPage > 1,
)

useHead({ title: () => t('contactInbox.title') })

function open(id) {
  router.replace({ name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGE, params: { id }, query: route.query })
}

function close() {
  router.replace({ name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES, query: route.query })
}

useInboxKeyboard({
  ids: () => items.value.map((item) => item.id),
  selectedId: () => selectedId.value,
  open,
  close,
})

watch(selectedId, (id) =>
  nextTick(() =>
    document.querySelector(`[data-message-id="${id}"]`)?.scrollIntoView({ block: 'nearest' }),
  ),
)

onMounted(loadAssignees)
</script>

<template>
  <section class="contact-desk" :class="{ 'contact-desk--open': selectedId }">
    <header class="contact-desk__header">
      <h1 class="contact-desk__title">{{ t('contactInbox.title') }}</h1>
      <p v-if="selectedId" class="contact-desk__hint">{{ t('contactInbox.split.keyboardHint') }}</p>
    </header>

    <ContactStatusChips
      class="contact-desk__chips"
      :model-value="filters.inboxStatus"
      :stats="stats"
      @update:model-value="inbox.setFilter('inboxStatus', $event)"
    />

    <DataListCard v-if="showTable">
      <template #toolbar>
        <ContactInboxFilters
          :filters="filters"
          :search="searchInput"
          :assignees="assignees"
          @update:search="searchInput = $event"
          @update:filter="inbox.setFilter($event.key, $event.value)"
        />
      </template>
      <ContactInboxTable
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :filtered="filtered"
        @retry="inbox.load"
      />
      <template v-if="showPagination" #footer>
        <Pagination
          :page="paginator.pagination.page"
          :last-page="paginator.pagination.lastPage"
          @update:page="paginator.setPage($event)"
        />
      </template>
    </DataListCard>

    <div v-else class="contact-desk__layout">
      <div class="contact-desk__list">
        <ContactInboxFilters
          :filters="filters"
          :search="searchInput"
          :assignees="assignees"
          @update:search="searchInput = $event"
          @update:filter="inbox.setFilter($event.key, $event.value)"
        />
        <div class="contact-desk__items">
          <Skeleton v-if="status === 'loading'" variant="text" :count="6" />
          <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="inbox.load" />
          <EmptyState
            v-else-if="status === 'empty'"
            icon="envelope"
            :title="t(filtered ? 'contactInbox.empty.filteredTitle' : 'contactInbox.empty.title')"
            :message="
              t(filtered ? 'contactInbox.empty.filteredMessage' : 'contactInbox.empty.message')
            "
          />
          <ContactInboxList v-else :items="items" :selected-id="selectedId" />
        </div>
        <Pagination
          v-if="showPagination"
          :page="paginator.pagination.page"
          :last-page="paginator.pagination.lastPage"
          @update:page="paginator.setPage($event)"
        />
      </div>

      <div v-if="selectedId" class="contact-desk__panel">
        <ContactMessagePanel
          :message-id="selectedId"
          :assignees="assignees"
          @changed="inbox.load"
        />
      </div>
    </div>
  </section>
</template>

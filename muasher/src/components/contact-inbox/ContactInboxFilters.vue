<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import {
  CONTACT_ASSIGNEE_FILTERS,
  CONTACT_INBOX_STATUSES,
  CONTACT_LINKED_FILTERS,
} from '@/constants/contactInbox'
import { CONTACT_ENQUIRY_TYPES } from '@/constants/contact'

const props = defineProps({
  filters: { type: Object, required: true },
  search: { type: String, default: '' },
  assignees: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:filter', 'update:search'])
const { t } = useI18n()

function withAll(options) {
  return [{ value: '', label: t('dataList.filter.all') }, ...options]
}

const statusOptions = computed(() =>
  withAll(
    CONTACT_INBOX_STATUSES.map((value) => ({ value, label: t(`portal.messages.status.${value}`) })),
  ),
)
const typeOptions = computed(() =>
  withAll(
    CONTACT_ENQUIRY_TYPES.map((value) => ({
      value,
      label: t(`public.home.contact.form.enquiryTypes.${value}`),
    })),
  ),
)
const linkedOptions = computed(() =>
  withAll(
    CONTACT_LINKED_FILTERS.map((value) => ({
      value,
      label: t(`contactInbox.filters.linked.${value}`),
    })),
  ),
)
const assigneeOptions = computed(() =>
  withAll([
    { value: CONTACT_ASSIGNEE_FILTERS.ME, label: t('contactInbox.filters.mine') },
    { value: CONTACT_ASSIGNEE_FILTERS.NONE, label: t('contactInbox.filters.unassigned') },
    ...props.assignees.map((person) => ({ value: String(person.id), label: person.name })),
  ]),
)

function update(key, value) {
  emit('update:filter', { key, value })
}

const filterState = { filters: props.filters, setFilter: update }
</script>

<template>
  <div class="contact-inbox__filters">
    <ListToolbar
      :search="search"
      :search-placeholder="t('contactInbox.filters.search')"
      :filter-state="filterState"
      @update:search="emit('update:search', $event)"
    >
      <template #filters="{ draft }">
        <AppSelect
          :label="t('contactInbox.columns.status')"
          :model-value="draft.filters.status"
          :options="statusOptions"
          @update:model-value="draft.setFilter('status', $event)"
        />
        <AppSelect
          :label="t('contactInbox.columns.enquiryType')"
          :model-value="draft.filters.enquiryType"
          :options="typeOptions"
          @update:model-value="draft.setFilter('enquiryType', $event)"
        />
        <AppSelect
          :label="t('contactInbox.columns.sender')"
          :model-value="draft.filters.linked"
          :options="linkedOptions"
          @update:model-value="draft.setFilter('linked', $event)"
        />
        <AppSelect
          :label="t('contactInbox.columns.assignee')"
          :model-value="draft.filters.assignee"
          :options="assigneeOptions"
          @update:model-value="draft.setFilter('assignee', $event)"
        />
        <BaseDatePicker
          :model-value="draft.filters.from"
          name="from"
          :label="t('contactInbox.filters.from')"
          :max="draft.filters.to || ''"
          @update:model-value="draft.setFilter('from', $event)"
        />
        <BaseDatePicker
          :model-value="draft.filters.to"
          name="to"
          :label="t('contactInbox.filters.to')"
          :min="draft.filters.from || ''"
          @update:model-value="draft.setFilter('to', $event)"
        />
      </template>
    </ListToolbar>
  </div>
</template>

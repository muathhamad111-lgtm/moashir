<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DropdownList from '@/components/ui/DropdownList.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
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
  filtered: { type: Boolean, default: false },
})

const emit = defineEmits(['update:filter', 'update:search', 'reset'])
const { t } = useI18n()

function withAll(allKey, options) {
  return [{ value: '', label: t(allKey) }, ...options]
}

const statusOptions = computed(() =>
  withAll(
    'contactInbox.filters.allStatuses',
    CONTACT_INBOX_STATUSES.map((value) => ({ value, label: t(`portal.messages.status.${value}`) })),
  ),
)
const typeOptions = computed(() =>
  withAll(
    'contactInbox.filters.allTypes',
    CONTACT_ENQUIRY_TYPES.map((value) => ({
      value,
      label: t(`public.home.contact.form.enquiryTypes.${value}`),
    })),
  ),
)
const linkedOptions = computed(() =>
  withAll(
    'contactInbox.filters.anySender',
    CONTACT_LINKED_FILTERS.map((value) => ({
      value,
      label: t(`contactInbox.filters.linked.${value}`),
    })),
  ),
)
const assigneeOptions = computed(() =>
  withAll('contactInbox.filters.anyAssignee', [
    { value: CONTACT_ASSIGNEE_FILTERS.ME, label: t('contactInbox.filters.mine') },
    { value: CONTACT_ASSIGNEE_FILTERS.NONE, label: t('contactInbox.filters.unassigned') },
    ...props.assignees.map((person) => ({ value: String(person.id), label: person.name })),
  ]),
)

function update(key, value) {
  emit('update:filter', { key, value })
}
</script>

<template>
  <div class="contact-inbox__filters">
    <ListToolbar
      :search="search"
      :search-placeholder="t('contactInbox.filters.search')"
      @update:search="emit('update:search', $event)"
    >
      <template #filters>
        <DropdownList
          :model-value="filters.status"
          :options="statusOptions"
          @update:model-value="update('status', $event)"
        />
        <DropdownList
          :model-value="filters.enquiryType"
          :options="typeOptions"
          @update:model-value="update('enquiryType', $event)"
        />
        <DropdownList
          :model-value="filters.linked"
          :options="linkedOptions"
          @update:model-value="update('linked', $event)"
        />
        <DropdownList
          :model-value="filters.assignee"
          :options="assigneeOptions"
          @update:model-value="update('assignee', $event)"
        />
      </template>
      <template v-if="filtered" #actions>
        <BaseButton variant="ghost" size="sm" icon="x" @click="emit('reset')">
          {{ t('contactInbox.filters.reset') }}
        </BaseButton>
      </template>
    </ListToolbar>
    <div class="contact-inbox__dates">
      <BaseDatePicker
        :model-value="filters.from"
        name="from"
        :label="t('contactInbox.filters.from')"
        :max="filters.to || ''"
        @update:model-value="update('from', $event)"
      />
      <BaseDatePicker
        :model-value="filters.to"
        name="to"
        :label="t('contactInbox.filters.to')"
        :min="filters.from || ''"
        @update:model-value="update('to', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseSelect from '@/components/form/BaseSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const UNASSIGNED = 'none'

const props = defineProps({
  assignee: { type: Object, default: null },
  assignees: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['assign'])
const { t } = useI18n()
const choice = ref('')

watch(
  () => props.assignee,
  (assignee) => {
    choice.value = assignee ? String(assignee.id) : UNASSIGNED
  },
  { immediate: true },
)

const options = computed(() => [
  { value: UNASSIGNED, label: t('contactInbox.assign.nobody') },
  ...props.assignees.map((person) => ({ value: String(person.id), label: person.name })),
])
const current = computed(() => (props.assignee ? String(props.assignee.id) : UNASSIGNED))

function assign() {
  emit('assign', choice.value === UNASSIGNED ? null : Number(choice.value))
}
</script>

<template>
  <div class="contact-assign">
    <BaseSelect
      v-model="choice"
      name="assigneeId"
      :label="t('contactInbox.assign.label')"
      :options="options"
      :error="error"
      :disabled="disabled"
    />
    <BaseButton
      variant="secondary"
      size="sm"
      icon="user-check"
      :loading="busy"
      :disabled="disabled || choice === current"
      @click="assign"
      >{{ t('contactInbox.assign.save') }}</BaseButton
    >
  </div>
</template>

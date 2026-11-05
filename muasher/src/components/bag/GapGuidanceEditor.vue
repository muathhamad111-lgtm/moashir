<script setup>
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseSelect from '@/components/form/BaseSelect.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps({
  gap: { type: Object, required: true },
  saving: { type: Boolean, default: false },
  localized: { type: Function, required: true },
})

const emit = defineEmits(['save'])

const { t, n, locale } = useI18n()

const form = reactive({
  priority: props.gap.priority ?? '',
  status: props.gap.status ?? 'approved',
  note: props.localized(props.gap.consultantNote),
  action: props.localized(props.gap.recommendedAction),
})

watch(
  () => props.gap.id,
  () => {
    form.priority = props.gap.priority ?? ''
    form.status = props.gap.status ?? 'approved'
    form.note = props.localized(props.gap.consultantNote)
    form.action = props.localized(props.gap.recommendedAction)
  },
)

const priorityOptions = computed(() => [
  { value: 'high', label: t('bag.gap.priority.high') },
  { value: 'medium', label: t('bag.gap.priority.medium') },
  { value: 'low', label: t('bag.gap.priority.low') },
])

const statusOptions = computed(() => [
  { value: 'draft', label: t('status.draft') },
  { value: 'approved', label: t('status.approved') },
  { value: 'deferred', label: t('status.deferred') },
  { value: 'excluded', label: t('status.excluded') },
])

function bilingual(value) {
  const trimmed = String(value ?? '').trim()
  if (!trimmed) return null
  return { ar: trimmed, en: trimmed, [locale.value]: trimmed }
}

function onSave() {
  emit('save', {
    priority: form.priority || null,
    status: form.status,
    consultantNote: bilingual(form.note),
    recommendedAction: bilingual(form.action),
  })
}
</script>

<template>
  <form class="gap-guidance" @submit.prevent="onSave">
    <div class="gap-guidance__head">
      <h4 class="gap-guidance__title">{{ t('bag.manage.guidance.title') }}</h4>
      <p class="gap-guidance__indicators">
        {{
          t('bag.manage.guidance.indicators', {
            gap: n(gap.gap, 'default'),
            weight: n(gap.target, 'default'),
          })
        }}
      </p>
    </div>

    <div class="gap-guidance__row">
      <BaseSelect
        v-model="form.priority"
        :label="t('bag.manage.guidance.priorityLabel')"
        :name="`gap-priority-${gap.id}`"
        :options="priorityOptions"
        :placeholder="t('bag.manage.guidance.priorityPlaceholder')"
        :disabled="saving"
      />
      <BaseSelect
        v-model="form.status"
        :label="t('bag.manage.guidance.statusLabel')"
        :name="`gap-status-${gap.id}`"
        :options="statusOptions"
        :disabled="saving"
      />
    </div>

    <BaseTextarea
      v-model="form.note"
      :label="t('bag.manage.guidance.noteLabel')"
      :name="`gap-note-${gap.id}`"
      :rows="2"
      :hint="t('bag.manage.guidance.noteHint')"
      :disabled="saving"
    />

    <BaseTextarea
      v-model="form.action"
      :label="t('bag.manage.guidance.actionLabel')"
      :name="`gap-action-${gap.id}`"
      :rows="2"
      :hint="t('bag.manage.guidance.actionHint')"
      :disabled="saving"
    />

    <div class="gap-guidance__footer">
      <BaseButton
        type="submit"
        variant="secondary"
        icon="fa-solid fa-floppy-disk"
        :loading="saving"
      >
        {{ t('bag.manage.guidance.save') }}
      </BaseButton>
    </div>
  </form>
</template>

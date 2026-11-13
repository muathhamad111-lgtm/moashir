<script setup>
import { useI18n } from 'vue-i18n'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  filters: { type: Object, required: true },
  errorOf: { type: Function, required: true },
  filtered: { type: Boolean, default: false },
})

const emit = defineEmits(['update', 'apply', 'reset'])
const { t } = useI18n()

const TEXT_FIELDS = [
  { key: 'event', inputmode: undefined },
  { key: 'subjectType', inputmode: undefined },
  { key: 'subjectId', inputmode: 'numeric' },
  { key: 'actorId', inputmode: 'numeric' },
  { key: 'ip', inputmode: 'decimal' },
]
</script>

<template>
  <form
    class="audit-log__filters"
    novalidate
    :aria-label="t('auditLog.filters.label')"
    @submit.prevent="emit('apply')"
  >
    <BaseDatePicker
      :model-value="filters.from"
      name="auditFrom"
      :label="t('auditLog.filters.from')"
      :max="filters.to || ''"
      :error="errorOf('from')"
      @update:model-value="emit('update', { key: 'from', value: $event })"
    />
    <BaseDatePicker
      :model-value="filters.to"
      name="auditTo"
      :label="t('auditLog.filters.to')"
      :min="filters.from || ''"
      :error="errorOf('to')"
      @update:model-value="emit('update', { key: 'to', value: $event })"
    />
    <BaseInput
      v-for="field in TEXT_FIELDS"
      :key="field.key"
      :model-value="filters[field.key]"
      :name="`audit-${field.key}`"
      :label="t(`auditLog.filters.${field.key}`)"
      :hint="t(`auditLog.filters.hints.${field.key}`)"
      :inputmode="field.inputmode"
      :error="errorOf(field.key)"
      input-dir="ltr"
      autocomplete="off"
      @update:model-value="emit('update', { key: field.key, value: $event })"
    />
    <div class="audit-log__filter-actions">
      <BaseButton type="submit" variant="primary" icon="search">{{
        t('auditLog.filters.apply')
      }}</BaseButton>
      <BaseButton v-if="filtered" variant="ghost" icon="x" @click="emit('reset')">{{
        t('auditLog.filters.reset')
      }}</BaseButton>
    </div>
  </form>
</template>

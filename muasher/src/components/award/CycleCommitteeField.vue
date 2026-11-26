<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppCombobox from '@/components/form/AppCombobox.vue'

const props = defineProps({
  committee: { type: Object, required: true },
  editable: { type: Boolean, default: false },
})

const { t } = useI18n()
const stageLocked = computed(() => props.committee.stageLocked.value)
const disabled = computed(() => !props.editable || props.committee.locked.value)

watch(
  disabled,
  (now) => {
    if (!now) props.committee.search()
  },
  { immediate: true },
)
</script>

<template>
  <section
    class="cycle-staff__group"
    data-testid="cycle-committee"
    :aria-label="t('cycles.staff.committee.title')"
  >
    <h3 class="cycle-staff__subtitle">{{ t('cycles.staff.committee.title') }}</h3>
    <AppCombobox
      multiple
      remote
      name="staff-committee"
      :label="t('cycles.staff.committee.label')"
      :placeholder="t('cycles.staff.choose')"
      :hint="stageLocked ? '' : t('cycles.staff.committee.hint')"
      :options="committee.options.value"
      :model-value="committee.selection.value"
      :error="committee.fieldError.value"
      :loading="committee.searching.value"
      :disabled="disabled"
      @update:model-value="committee.select"
      @search="committee.search"
    />
    <p v-if="stageLocked" class="cycle-staff__hint" data-testid="cycle-committee-locked">
      {{ t('cycles.staff.committee.locked') }}
    </p>
  </section>
</template>

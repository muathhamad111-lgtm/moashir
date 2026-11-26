<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { sameValue } from '@/utils/dropdown/optionValue'

const props = defineProps({
  options: { type: Array, required: true },
  modelValue: { type: Array, required: true },
  countLabel: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const selectable = computed(() => props.options.filter((option) => !option.disabled))
const allChosen = computed(
  () => selectable.value.length > 0 && selectable.value.every((option) => isChosen(option)),
)
const count = computed(
  () =>
    props.countLabel ||
    t('components.dropdown.selectedOf', {
      count: props.modelValue.length,
      total: props.options.length,
    }),
)

function isChosen(option) {
  return props.modelValue.some((value) => sameValue(value, option.value))
}

function toggleAll() {
  const next = props.options.filter((option) =>
    option.disabled ? isChosen(option) : !allChosen.value,
  )
  emit(
    'update:modelValue',
    next.map((option) => option.value),
  )
}
</script>

<template>
  <div class="dropdown-select-all">
    <span class="dropdown-select-all__count" aria-live="polite">{{ count }}</span>
    <button
      type="button"
      class="dropdown-select-all__toggle"
      :disabled="disabled || !selectable.length"
      @click="toggleAll"
    >
      {{ allChosen ? t('components.dropdown.clearAll') : t('components.dropdown.selectAll') }}
    </button>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { valueKey } from '@/utils/dropdown/optionValue'
import DropdownOption from '@/components/ui/DropdownOption.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  id: { type: String, required: true },
  label: { type: String, required: true },
  options: { type: Array, required: true },
  optionId: { type: Function, required: true },
  activeIndex: { type: Number, default: -1 },
  selectedValues: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  query: { type: String, default: '' },
})

const emit = defineEmits(['choose', 'hover'])

const { t } = useI18n()
const listRef = ref(null)

const selectedKeys = computed(() => new Set(props.selectedValues.map(valueKey)))

const status = computed(() => {
  if (props.loading) return { icon: 'spinner', text: t('components.dropdown.loading') }
  if (props.options.length) return null
  if (props.query)
    return { icon: 'search', text: t('components.dropdown.noResults', { query: props.query }) }
  return { icon: 'inbox', text: t('components.dropdown.empty') }
})

const resultCount = computed(() =>
  props.query && !status.value
    ? t('components.dropdown.resultCount', { count: props.options.length }, props.options.length)
    : '',
)

function isSelected(option) {
  return selectedKeys.value.has(valueKey(option.value))
}

defineExpose({ element: listRef })
</script>

<template>
  <div class="dropdown-listbox">
    <p v-if="status" class="dropdown-listbox__status" role="status">
      <AppIcon :name="status.icon" />
      <span>{{ status.text }}</span>
    </p>
    <p v-else-if="resultCount" class="dropdown-listbox__count" role="status">{{ resultCount }}</p>
    <ul
      v-show="!loading"
      :id="id"
      ref="listRef"
      class="dropdown-listbox__list"
      role="listbox"
      :aria-label="label"
      :aria-multiselectable="multiple || undefined"
      v-bind="$attrs"
    >
      <DropdownOption
        v-for="(option, index) in options"
        :id="optionId(index)"
        :key="valueKey(option.value)"
        role="option"
        :data-value="valueKey(option.value)"
        :label="option.label"
        :description="option.description"
        :icon="option.icon"
        :badge="option.badge"
        :active="index === activeIndex"
        :selected="isSelected(option)"
        :disabled="option.disabled"
        :aria-selected="isSelected(option) ? 'true' : 'false'"
        :aria-disabled="option.disabled || undefined"
        @mousedown.prevent
        @mousemove="emit('hover', index)"
        @click="emit('choose', index)"
      />
    </ul>
  </div>
</template>

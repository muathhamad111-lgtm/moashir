<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  search: { type: String, default: '' },
  searchPlaceholder: { type: String, default: '' },
})

const emit = defineEmits(['update:search'])
const { t } = useI18n()
</script>

<template>
  <div class="toolbar">
    <div class="toolbar__search">
      <input
        class="field__control"
        type="search"
        :value="search"
        :placeholder="searchPlaceholder || t('common.search')"
        :aria-label="searchPlaceholder || t('common.search')"
        @input="emit('update:search', $event.target.value)"
      />
    </div>
    <div v-if="$slots.filters" class="toolbar__filters">
      <slot name="filters" />
    </div>
    <div v-if="$slots.actions" class="toolbar__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

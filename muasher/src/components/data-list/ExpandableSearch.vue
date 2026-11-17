<script setup>
import { computed, nextTick, ref, useId, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useTtsShell } from '@/composables/useTtsShell'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const inputId = useId()
const input = useTemplateRef('input')
const toggle = useTemplateRef('toggle')
const opened = ref(false)
const { isTtsShell } = useTtsShell()

const label = computed(() => props.placeholder || t('common.search'))
const isExpanded = computed(() => isTtsShell.value || opened.value || Boolean(props.modelValue))
const canClose = computed(() => !isTtsShell.value || Boolean(props.modelValue))

async function expand() {
  opened.value = true
  await nextTick()
  input.value?.focus()
}

async function collapse() {
  emit('update:modelValue', '')
  opened.value = false
  await nextTick()
  toggle.value?.focus()
}
</script>

<template>
  <div class="expandable-search" :class="{ 'is-expanded': isExpanded }">
    <button
      v-if="!isExpanded"
      ref="toggle"
      type="button"
      class="btn btn--secondary btn--sm expandable-search__toggle"
      :aria-label="label"
      :aria-controls="inputId"
      aria-expanded="false"
      @click="expand"
    >
      <AppIcon name="search" />
      <span>{{ t('common.search') }}</span>
    </button>
    <div v-else class="expandable-search__field">
      <AppIcon name="search" class="expandable-search__icon" />
      <input
        :id="inputId"
        ref="input"
        class="expandable-search__input"
        type="search"
        :value="modelValue"
        :placeholder="label"
        :aria-label="label"
        @input="emit('update:modelValue', $event.target.value)"
        @keydown.esc.prevent="collapse"
      />
      <button
        v-if="canClose"
        type="button"
        class="expandable-search__close"
        :aria-label="t('dataList.search.close')"
        @click="collapse"
      >
        <AppIcon name="x" />
      </button>
    </div>
  </div>
</template>

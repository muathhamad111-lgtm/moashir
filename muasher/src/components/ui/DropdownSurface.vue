<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAnchoredPopup } from '@/composables/useAnchoredPopup'
import { DROPDOWN_PLACEMENTS } from '@/constants/dropdown'
import { trapTabKey } from '@/utils/dropdown/focusables'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  open: { type: Boolean, required: true },
  anchor: { type: Object, default: null },
  boundary: { type: Object, default: null },
  placement: {
    type: String,
    default: DROPDOWN_PLACEMENTS.field,
    validator: (value) => Object.values(DROPDOWN_PLACEMENTS).includes(value),
  },
  sheet: { type: Boolean, default: false },
  title: { type: String, required: true },
})

const emit = defineEmits(['dismiss'])

const { t } = useI18n()
const popupRef = ref(null)
const titleId = useId()

useAnchoredPopup({
  anchor: () => props.anchor,
  boundary: () => props.boundary,
  popup: popupRef,
  isOpen: () => props.open,
  isSheet: () => props.sheet,
  placement: () => props.placement,
  onDismiss: () => emit('dismiss'),
})

function onSheetKeydown(event) {
  if (event.key === 'Tab') trapTabKey(event, popupRef.value)
  if (event.key !== 'Escape') return
  event.preventDefault()
  event.stopPropagation()
  emit('dismiss')
}

defineExpose({ element: popupRef })
</script>

<template>
  <div
    v-if="open && !sheet"
    ref="popupRef"
    popover="manual"
    class="dropdown-popup"
    :class="`dropdown-popup--${placement}`"
  >
    <slot />
  </div>
  <div
    v-else-if="open"
    ref="popupRef"
    popover="manual"
    class="dropdown-sheet"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="titleId"
    @click.self="emit('dismiss')"
    @keydown="onSheetKeydown"
  >
    <div class="dropdown-sheet__panel">
      <span class="dropdown-sheet__handle" aria-hidden="true"></span>
      <header class="dropdown-sheet__header">
        <h2 :id="titleId" class="dropdown-sheet__title">{{ title }}</h2>
        <button
          type="button"
          class="dropdown-sheet__close"
          :aria-label="t('components.dropdown.close')"
          @click="emit('dismiss')"
        >
          <AppIcon name="x" />
        </button>
      </header>
      <div class="dropdown-sheet__body">
        <slot />
      </div>
      <footer v-if="$slots.footer" class="dropdown-sheet__footer">
        <slot name="footer" />
      </footer>
    </div>
  </div>
</template>

<script setup>
import { nextTick, ref, useId } from 'vue'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { DROPDOWN_PLACEMENTS } from '@/constants/dropdown'
import DropdownSurface from '@/components/ui/DropdownSurface.vue'

defineProps({
  label: { type: String, required: true },
  triggerLabel: { type: String, required: true },
  triggerClass: { type: String, default: 'btn btn--ghost btn--sm btn--icon' },
})

const emit = defineEmits(['open', 'close'])

const baseId = useId()
const panelId = `${baseId}-panel`
const headingId = `${baseId}-heading`
const rootRef = ref(null)
const triggerRef = ref(null)
const panelRef = ref(null)
const isOpen = ref(false)
const isCompact = useCompactViewport()

async function focusPanel() {
  await nextTick()
  const heading = document.getElementById(headingId)
  const target =
    heading?.checkVisibility?.() === false ? panelRef.value : (heading ?? panelRef.value)
  target?.focus()
}

function open() {
  if (isOpen.value) return
  isOpen.value = true
  emit('open')
  focusPanel()
}

function dismiss() {
  if (!isOpen.value) return
  isOpen.value = false
  emit('close')
}

function close() {
  dismiss()
  triggerRef.value?.focus()
}

function toggle() {
  if (isOpen.value) close()
  else open()
}

function onKeydown(event) {
  if (event.key !== 'Escape') return
  event.preventDefault()
  event.stopPropagation()
  close()
}

function onFocusOut(event) {
  if (isCompact.value || !event.relatedTarget) return
  if (!rootRef.value?.contains(event.relatedTarget)) dismiss()
}

defineExpose({ close, dismiss })
</script>

<template>
  <div ref="rootRef" class="dropdown-popover">
    <button
      ref="triggerRef"
      type="button"
      :class="triggerClass"
      aria-haspopup="dialog"
      :aria-expanded="isOpen ? 'true' : 'false'"
      :aria-controls="isOpen ? panelId : undefined"
      :aria-label="triggerLabel"
      @click="toggle"
    >
      <slot name="trigger" :is-open="isOpen" />
    </button>
    <DropdownSurface
      :open="isOpen"
      :anchor="triggerRef"
      :boundary="rootRef"
      :placement="DROPDOWN_PLACEMENTS.panel"
      :sheet="isCompact"
      :title="label"
      @dismiss="isCompact ? close() : dismiss()"
    >
      <div
        :id="panelId"
        ref="panelRef"
        class="dropdown-popover__panel"
        :role="isCompact ? undefined : 'dialog'"
        :aria-labelledby="isCompact ? undefined : headingId"
        tabindex="-1"
        @keydown="onKeydown"
        @focusout="onFocusOut"
      >
        <slot :heading-id="headingId" :is-sheet="isCompact" :close="close" :dismiss="dismiss" />
      </div>
    </DropdownSurface>
  </div>
</template>

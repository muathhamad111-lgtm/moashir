<script setup>
import { useCompactViewport } from '@/composables/useCompactViewport'
import { DROPDOWN_PLACEMENTS } from '@/constants/dropdown'
import DropdownSurface from '@/components/ui/DropdownSurface.vue'
import DateCalendar from './DateCalendar.vue'

defineProps({
  open: { type: Boolean, required: true },
  anchor: { type: Object, default: null },
  boundary: { type: Object, default: null },
  panelId: { type: String, required: true },
  selected: { type: String, default: '' },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  label: { type: String, required: true },
})

const emit = defineEmits(['select', 'close', 'dismiss'])

const isCompact = useCompactViewport()

function onSurfaceDismiss() {
  if (isCompact.value) emit('close')
  else emit('dismiss')
}
</script>

<template>
  <DropdownSurface
    :open="open"
    :anchor="anchor"
    :boundary="boundary"
    :placement="DROPDOWN_PLACEMENTS.calendar"
    :sheet="isCompact"
    :title="label"
    @dismiss="onSurfaceDismiss"
  >
    <DateCalendar
      :id="panelId"
      :selected="selected"
      :min="min"
      :max="max"
      :label="label"
      :is-sheet="isCompact"
      @select="emit('select', $event)"
      @close="emit('close')"
    />
  </DropdownSurface>
</template>

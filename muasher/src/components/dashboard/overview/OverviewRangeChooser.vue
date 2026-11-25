<script setup>
import { computed, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { DROPDOWN_PLACEMENTS } from '@/constants/dropdown'
import { OVERVIEW_FIRST_DAY } from '@/constants/dashboardOverview'
import AppIcon from '@/components/ui/AppIcon.vue'
import DropdownSurface from '@/components/ui/DropdownSurface.vue'
import DateCalendar from '@/components/form/DateCalendar.vue'
import { rangeLabel } from '@/utils/dashboard/overviewCalendar'

// A custom period picked on one calendar: the first tap is the first day, the second the last
// (the same day twice, or "one day", is a single day). Opens by itself while no range is chosen.
const props = defineProps({
  from: { type: String, default: '' },
  to: { type: String, default: '' },
  today: { type: String, required: true },
})

const emit = defineEmits(['choose'])
const { t, locale } = useI18n()
const panelId = useId()
const rootRef = ref(null)
const triggerRef = ref(null)
const isOpen = ref(false)
const start = ref('')
const isCompact = useCompactViewport()

const label = computed(() =>
  props.from && props.to
    ? rangeLabel(props.from, props.to, locale.value)
    : t('dashboard.overview.period.range.empty'),
)
const hint = computed(() =>
  t(start.value ? 'dashboard.overview.period.range.end' : 'dashboard.overview.period.range.start'),
)

function open() {
  start.value = ''
  isOpen.value = true
}

function dismiss() {
  isOpen.value = false
}

function close() {
  dismiss()
  triggerRef.value?.focus()
}

function apply(first, last) {
  emit('choose', first <= last ? first : last, first <= last ? last : first)
  close()
}

function pick(day) {
  if (!start.value) start.value = day
  else apply(start.value, day)
}

onMounted(() => {
  if (!props.from || !props.to) open()
})
</script>

<template>
  <div ref="rootRef" class="overview-period__chooser">
    <button
      ref="triggerRef"
      type="button"
      class="overview-period__trigger"
      aria-haspopup="dialog"
      :aria-expanded="isOpen ? 'true' : 'false'"
      :aria-controls="isOpen ? panelId : undefined"
      :aria-label="t('dashboard.overview.period.choose', { current: label })"
      @click="isOpen ? close() : open()"
    >
      <AppIcon name="calendar" />
      <span class="overview-period__current">{{ label }}</span>
      <AppIcon name="chevron-sm-down" />
    </button>
    <DropdownSurface
      :open="isOpen"
      :anchor="triggerRef"
      :boundary="rootRef"
      :placement="DROPDOWN_PLACEMENTS.calendar"
      :sheet="isCompact"
      :title="t('dashboard.overview.period.pick.custom')"
      @dismiss="isCompact ? close() : dismiss()"
    >
      <DateCalendar
        :id="panelId"
        :selected="start || from"
        :span-from="start ? '' : from"
        :span-to="start ? '' : to"
        :min="OVERVIEW_FIRST_DAY"
        :max="today"
        :label="t('dashboard.overview.period.pick.custom')"
        :is-sheet="isCompact"
        @select="pick"
        @close="close"
      >
        <div class="range-chooser__foot">
          <p class="range-chooser__hint" aria-live="polite">{{ hint }}</p>
          <button
            v-if="start"
            type="button"
            class="range-chooser__one-day"
            @click="apply(start, start)"
          >
            {{ t('dashboard.overview.period.range.oneDay') }}
          </button>
        </div>
      </DateCalendar>
    </DropdownSurface>
  </div>
</template>

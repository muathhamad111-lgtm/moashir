<script setup>
import { computed, nextTick, ref } from 'vue'
import { useCalendarLocale } from '@/composables/useCalendarLocale'
import { isOutsideRange, isSameMonth } from '@/utils/calendarMonth'

const props = defineProps({
  weeks: { type: Array, required: true },
  activeDate: { type: String, required: true },
  selected: { type: String, default: '' },
  today: { type: String, required: true },
  range: { type: Object, required: true },
  labelledBy: { type: String, required: true },
  // A chosen span of days to highlight (a range picker); empty for a single-date picker.
  spanFrom: { type: String, default: '' },
  spanTo: { type: String, default: '' },
})

const emit = defineEmits(['choose'])

const gridRef = ref(null)
const { weekdaysOf, dayNumber, fullDate } = useCalendarLocale()

const weekdays = computed(() => weekdaysOf(props.weeks[0]))

function isDisabled(iso) {
  return isOutsideRange(iso, props.range)
}

function isInSpan(iso) {
  return Boolean(props.spanFrom && props.spanTo) && iso >= props.spanFrom && iso <= props.spanTo
}

function isChosen(iso) {
  return iso === props.selected || iso === props.spanFrom || iso === props.spanTo
}

function dayClasses(iso) {
  return {
    'is-selected': isChosen(iso),
    'is-in-span': isInSpan(iso) && !isChosen(iso),
    'is-today': iso === props.today,
    'is-outside-month': !isSameMonth(iso, props.activeDate),
  }
}

async function focusActive() {
  await nextTick()
  gridRef.value?.querySelector('.date-calendar__day[tabindex="0"]')?.focus()
}

defineExpose({ focusActive })
</script>

<template>
  <table ref="gridRef" class="date-calendar__grid" role="grid" :aria-labelledby="labelledBy">
    <thead>
      <tr class="date-calendar__row" role="row">
        <th
          v-for="weekday in weekdays"
          :key="weekday.long"
          class="date-calendar__weekday"
          role="columnheader"
          scope="col"
          :abbr="weekday.long"
        >
          {{ weekday.short }}
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="week in weeks" :key="week[0]" class="date-calendar__row" role="row">
        <td
          v-for="iso in week"
          :key="iso"
          class="date-calendar__cell"
          role="gridcell"
          :aria-selected="isChosen(iso) || isInSpan(iso) ? 'true' : 'false'"
          :aria-disabled="isDisabled(iso) ? 'true' : undefined"
        >
          <button
            type="button"
            class="date-calendar__day"
            :class="dayClasses(iso)"
            :tabindex="iso === activeDate ? 0 : -1"
            :disabled="isDisabled(iso)"
            :aria-label="fullDate(iso)"
            :aria-current="iso === today ? 'date' : undefined"
            :data-date="iso"
            @click="emit('choose', iso)"
          >
            {{ dayNumber(iso) }}
          </button>
        </td>
      </tr>
    </tbody>
  </table>
</template>

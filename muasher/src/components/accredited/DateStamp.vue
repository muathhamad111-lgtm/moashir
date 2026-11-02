<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatHijriDate, formatRiyadhDate, formatRiyadhDateTime } from '@/utils/datetime'

const props = defineProps({
  value: { type: String, default: null },
  withTime: { type: Boolean, default: true },
  withHijri: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const gregorian = computed(() => (props.withTime ? formatRiyadhDateTime(props.value, locale.value) : formatRiyadhDate(props.value, locale.value)))
const hijri = computed(() => (props.withHijri && props.value ? formatHijriDate(props.value, locale.value) : ''))
</script>

<template>
  <span class="date-stamp">
    <time v-if="value" :datetime="value" class="date-stamp__gregorian">{{ gregorian }}</time>
    <span v-else class="date-stamp__empty">{{ t('accredited.common.notSet') }}</span>
    <span v-if="hijri" class="date-stamp__hijri">{{ hijri }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatRiyadhDate } from '@/utils/datetime'

// The record's status, shown once and large, with the date it entered it; a flag on the whole record (a frozen
// entity) is a short text line under it.
const props = defineProps({
  status: { type: String, required: true },
  since: { type: String, default: null },
})

const { t, locale } = useI18n()
const sinceDate = computed(() => (props.since ? formatRiyadhDate(props.since, locale.value) : ''))
</script>

<template>
  <div class="record-status">
    <span class="record-status__label">{{ t('records.status') }}</span>
    <StatusBadge class="record-status__badge" :status="status" />
    <span v-if="sinceDate" class="record-status__since"
      >{{ t('records.since') }} <bdi dir="ltr">{{ sinceDate }}</bdi></span
    >
    <slot />
  </div>
</template>

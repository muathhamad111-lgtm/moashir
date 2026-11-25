<script setup>
import DateStamp from '@/components/accredited/DateStamp.vue'

// A cycle period (submission or committee assessment) as two dates, in the cycle lists.
defineProps({
  start: { type: String, default: null },
  end: { type: String, default: null },
})
</script>

<template>
  <span class="cycle-table__window">
    <DateStamp :value="start" :with-time="false" />
    <span aria-hidden="true">–</span>
    <DateStamp :value="end" :with-time="false" />
  </span>
</template>

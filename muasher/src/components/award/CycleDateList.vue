<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DateStamp from '@/components/accredited/DateStamp.vue'

const props = defineProps({
  cycle: { type: Object, required: true },
})

const { t } = useI18n()

const rows = computed(() => [
  {
    id: 'submission',
    label: t('cycles.dates.submission'),
    start: props.cycle.submissionStart,
    end: props.cycle.submissionEnd,
  },
  {
    id: 'committee',
    label: t('cycles.dates.committee'),
    start: props.cycle.committeeStart,
    end: props.cycle.committeeEnd,
  },
  {
    id: 'announcement',
    label: t('cycles.dates.announcement'),
    start: props.cycle.announcementAt,
    end: null,
  },
])
</script>

<template>
  <dl class="cycle-dates">
    <div v-for="row in rows" :key="row.id" class="cycle-dates__row">
      <dt class="cycle-dates__label">{{ row.label }}</dt>
      <dd class="cycle-dates__value">
        <DateStamp :value="row.start" :with-time="false" with-hijri />
        <template v-if="row.end">
          <span class="cycle-dates__separator">{{ t('cycles.dates.to') }}</span>
          <DateStamp :value="row.end" :with-time="false" with-hijri />
        </template>
      </dd>
    </div>
  </dl>
</template>

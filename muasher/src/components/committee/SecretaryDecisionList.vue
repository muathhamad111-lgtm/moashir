<script setup>
import { useI18n } from 'vue-i18n'
import DateStamp from '@/components/accredited/DateStamp.vue'

defineProps({
  decisions: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <ul v-if="decisions.length" class="filtering-decisions">
    <li v-for="decision in decisions" :key="decision.id" class="filtering-decisions__item">
      <div class="filtering-decisions__head">
        <strong>{{ t(`award.secretary.decisions.${decision.kind}`) }}</strong>
        <DateStamp :value="decision.decidedAt" />
      </div>
      <p v-if="decision.decidedBy?.name" class="filtering-decisions__meta">
        {{ t('award.secretary.decidedBy', { name: decision.decidedBy.name }) }}
      </p>
      <p v-if="decision.note" class="filtering-decisions__note">{{ decision.note }}</p>
    </li>
  </ul>
  <p v-else class="filtering-decisions__empty">{{ t('award.secretary.noDecisions') }}</p>
</template>

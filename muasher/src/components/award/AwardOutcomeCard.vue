<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { RANK_KEYS, RESULT_STATUSES as R } from '@/constants/committee'
import { localizedText } from '@/utils/localizedText'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  award: { type: Object, required: true },
})

const { t, locale } = useI18n()
const winner = computed(() => props.award.status === R.NATIONAL_WINNER)
const outcomeKey = computed(() =>
  Object.values(R).includes(props.award.status) ? props.award.status : 'not-qualified',
)
</script>

<template>
  <Card class="award-outcome" :class="{ 'award-outcome--winner': winner }">
    <div class="award-outcome__head">
      <AppIcon :name="winner ? 'trophy' : 'award'" class="award-outcome__icon" />
      <div>
        <h2 class="award-outcome__title">
          {{ t(`advanced.result.outcomes.${toCanonicalStatus(outcomeKey)}`) }}
        </h2>
        <StatusBadge :status="award.status" />
      </div>
    </div>
    <dl class="request-facts">
      <div v-if="award.region" class="request-facts__item">
        <dt>{{ t('advanced.result.region') }}</dt>
        <dd>{{ localizedText(award.region.name, locale) }}</dd>
      </div>
      <div v-if="award.regionalRank" class="request-facts__item">
        <dt>{{ t('advanced.result.regionalRank') }}</dt>
        <dd>{{ t(`award.results.rank.${RANK_KEYS[award.regionalRank]}`) }}</dd>
      </div>
      <div v-if="award.nationalRank" class="request-facts__item">
        <dt>{{ t('advanced.result.nationalRank') }}</dt>
        <dd>{{ t(`award.results.rank.${RANK_KEYS[award.nationalRank]}`) }}</dd>
      </div>
    </dl>
    <p class="award-outcome__note">{{ t('advanced.result.averageHidden') }}</p>
  </Card>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import PathPrice from '@/components/paths/PathPrice.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { AVAILABILITY } from '@/constants/entityPaths'
import { localizedText } from '@/utils/localizedText'
import { isoDate } from '@/utils/datetime'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { rowAction } from '@/utils/pathActions'

const props = defineProps({
  cycles: { type: Array, required: true },
  selectedId: { type: Number, default: null },
  applying: { type: Boolean, default: false },
})

const emit = defineEmits(['apply'])
const { t, locale } = useI18n()

function regionsOf(cycle) {
  return cycle.regions
    .map((region) => localizedText(region.name, locale.value))
    .join(t('common.listSeparator'))
}

function pricingOf(cycle) {
  const fee = cycle.entryFee
  return {
    price: fee,
    isFree: !fee || Number(fee.total) === 0,
    availability: AVAILABILITY.AVAILABLE,
  }
}

function standingOf(cycle) {
  if (cycle.request)
    return t('paths.pages.award.openCycles.request', {
      number: cycle.request.number,
      status: t(statusLabelKey(cycle.request.status)),
    })
  if (cycle.eligibility.eligible) return t('paths.pages.award.openCycles.eligible')
  return t(`paths.award.reasons.${cycle.eligibility.reason ?? 'unknown'}`)
}

const items = computed(() =>
  props.cycles.map((cycle) => ({
    cycle,
    continueTo: cycle.request ? rowAction('award', cycle.request) : null,
    canApply: !cycle.request && cycle.eligibility.eligible,
  })),
)
</script>

<template>
  <section class="award-open-cycles" data-testid="award-open-cycles">
    <header class="award-open-cycles__head">
      <h2 class="award-open-cycles__title">{{ t('paths.pages.award.openCycles.title') }}</h2>
      <p class="award-open-cycles__subtitle">{{ t('paths.pages.award.openCycles.subtitle') }}</p>
    </header>
    <ul class="award-open-cycles__list">
      <li
        v-for="{ cycle, continueTo, canApply } in items"
        :key="cycle.id"
        class="award-open-cycles__item"
        :class="{ 'award-open-cycles__item--selected': cycle.id === selectedId }"
        :aria-current="cycle.id === selectedId ? 'true' : undefined"
        :data-testid="`award-open-cycle-${cycle.id}`"
      >
        <h3 class="award-open-cycles__name">
          {{ localizedText(cycle.name, locale) }} <bdi class="u-ltr">{{ cycle.year }}</bdi>
        </h3>
        <p class="award-open-cycles__line">
          {{ t('paths.pages.award.openCycles.submission') }}:
          <bdi dir="ltr">{{ isoDate(cycle.submissionStart) }}</bdi>
          –
          <bdi dir="ltr">{{ isoDate(cycle.submissionEnd) }}</bdi>
        </p>
        <p v-if="cycle.regions.length" class="award-open-cycles__note">
          {{ t('paths.pages.award.regions', { regions: regionsOf(cycle) }) }}
        </p>
        <div class="award-open-cycles__line">
          <span>{{ t('paths.pages.award.openCycles.fee') }}</span>
          <PathPrice :pricing="pricingOf(cycle)" />
        </div>
        <p class="award-open-cycles__note" data-testid="award-open-cycle-standing">
          {{ standingOf(cycle) }}
        </p>
        <RouterLink
          v-if="continueTo"
          class="btn btn--secondary btn--sm award-open-cycles__action"
          :to="continueTo"
          data-testid="award-open-cycle-continue"
        >
          <span>{{ t('paths.pages.award.openCycles.continue') }}</span>
          <AppIcon name="arrow-right" />
        </RouterLink>
        <BaseButton
          v-else-if="canApply"
          class="award-open-cycles__action"
          variant="primary"
          size="sm"
          icon="arrow-right"
          :loading="applying"
          data-testid="award-open-cycle-apply"
          @click="emit('apply', cycle.id)"
        >
          {{ t('paths.pages.award.openCycles.apply') }}
        </BaseButton>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import MaturityBandChip from './MaturityBandChip.vue'
import InsightPercent from './InsightPercent.vue'
import InsightFigure from './InsightFigure.vue'
import InsightsDelta from './InsightsDelta.vue'
import { DELTA_COMPARISONS } from '@/constants/insights'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  main: { type: Object, required: true },
})

const { t, n, locale } = useI18n()
const subsId = useId()
const open = ref(false)
const name = computed(() => localizedText(props.main.name, locale.value))
const description = computed(() => localizedText(props.main.description, locale.value))
const interpretation = computed(() => localizedText(props.main.interpretation, locale.value))
const isNew = computed(() => props.main.comparison === DELTA_COMPARISONS.NEW_IN_VERSION)
</script>

<template>
  <article class="maturity-card" :data-code="main.code" data-testid="maturity-card">
    <header class="maturity-card__header">
      <h3 class="maturity-card__title">
        <bdi dir="ltr">{{ main.code }}</bdi
        >. {{ name }}
      </h3>
      <p v-if="description" class="maturity-card__measures">
        {{ t('insights.maturity.measures', { text: description }) }}
      </p>
    </header>
    <div class="maturity-card__score">
      <InsightPercent class="maturity-card__percent" :value="main.percent" />
      <i18n-t keypath="insights.maturity.earned" tag="span" class="maturity-card__earned">
        <template #earned><InsightFigure :value="main.earned" /></template>
        <template #weight><InsightFigure :value="main.weight" /></template>
      </i18n-t>
      <MaturityBandChip class="maturity-card__band" :band="main.band" />
    </div>
    <p v-if="interpretation" class="maturity-card__text" dir="auto">{{ interpretation }}</p>
    <p class="maturity-card__delta">
      <span v-if="isNew" class="maturity-card__new">{{ t('insights.delta.newInVersion') }}</span>
      <template v-else-if="main.deltaPp !== null">
        <InsightsDelta :delta-pp="main.deltaPp" />
        <span v-if="main.previousBand" class="maturity-card__previous">
          {{
            t('insights.maturity.previousBand', { band: t(`insights.bands.${main.previousBand}`) })
          }}
        </span>
      </template>
    </p>
    <button
      v-if="main.subs.length"
      type="button"
      class="maturity-card__toggle"
      :aria-expanded="open"
      :aria-controls="subsId"
      @click="open = !open"
    >
      {{
        open
          ? t('insights.maturity.hideSubs')
          : t('insights.maturity.showSubs', { count: n(main.subs.length) })
      }}
      <AppIcon :name="open ? 'chevron-up' : 'chevron-down'" />
    </button>
    <ul
      v-show="open"
      :id="subsId"
      class="maturity-card__subs"
      :aria-label="t('insights.maturity.subsLabel', { name })"
    >
      <li v-for="sub in main.subs" :key="sub.code" class="maturity-card__sub">
        <span class="maturity-card__sub-name">
          <bdi dir="ltr">{{ sub.code }}</bdi> · {{ localizedText(sub.name, locale) }}
        </span>
        <span class="maturity-card__sub-facts">
          <InsightPercent :value="sub.percent" />
          <MaturityBandChip :band="sub.band" />
          <InsightsDelta v-if="sub.deltaPp !== null" :delta-pp="sub.deltaPp" />
          <span
            v-else-if="main.comparison === DELTA_COMPARISONS.MATCHED"
            class="maturity-card__new"
          >
            {{ t('insights.delta.newInVersion') }}
          </span>
        </span>
      </li>
    </ul>
  </article>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  legend: { type: Array, required: true },
  ranking: { type: Array, required: true },
  region: { type: Object, default: null },
  cities: { type: Array, required: true },
  selected: { type: String, default: '' },
})

const emit = defineEmits(['select'])

const { t } = useI18n()
</script>

<template>
  <div class="overview-geography__detail">
    <section class="overview-geography__block">
      <h4 class="overview-geography__heading">{{ t('dashboard.overview.geography.legend') }}</h4>
      <ul class="overview-geography__legend">
        <li class="overview-geography__key">
          <span class="overview-geography__swatch region-map__region--step-0" aria-hidden="true" />
          {{ t('dashboard.overview.geography.none') }}
        </li>
        <li v-for="item in legend" :key="item.step" class="overview-geography__key">
          <span
            class="overview-geography__swatch"
            :class="`region-map__region--step-${item.step}`"
            aria-hidden="true"
          />
          <bdi>{{ t('dashboard.overview.geography.upTo', { value: item.upTo }) }}</bdi>
        </li>
      </ul>
    </section>

    <section v-if="ranking.length" class="overview-geography__block">
      <h4 class="overview-geography__heading">
        {{ t('dashboard.overview.geography.topRegions') }}
      </h4>
      <ol class="overview-geography__ranking">
        <li v-for="row in ranking" :key="row.key">
          <button
            type="button"
            class="overview-geography__rank"
            :class="{ 'is-active': selected === row.key }"
            :aria-pressed="selected === row.key ? 'true' : 'false'"
            :data-rank="row.key"
            @click="emit('select', row.key)"
          >
            <span class="overview-geography__name">{{ row.label }}</span>
            <bdi class="overview-geography__value" dir="ltr">{{ row.display }}</bdi>
          </button>
        </li>
      </ol>
    </section>
    <p v-else class="dashboard-widget__empty">{{ t('dashboard.widgets.empty') }}</p>

    <section v-if="region" class="overview-geography__block" data-testid="geography-cities">
      <h4 class="overview-geography__heading">
        {{ t('dashboard.overview.geography.citiesOf', { region: region.label }) }}
      </h4>
      <ul v-if="cities.length" class="overview-geography__cities">
        <li v-for="city in cities" :key="city.key" class="overview-geography__city">
          <span class="overview-geography__name">{{ city.label }}</span>
          <bdi class="overview-geography__value" dir="ltr">{{ city.display }}</bdi>
        </li>
      </ul>
      <p v-else class="overview-geography__none">
        {{ t('dashboard.overview.geography.noCities') }}
      </p>
    </section>
  </div>
</template>

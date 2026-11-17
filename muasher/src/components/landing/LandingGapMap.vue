<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const ROWS = [
  { key: 'leadership', actual: 62, target: 90, stage: 1 },
  { key: 'strategy', actual: 78, target: 90, stage: 3 },
  { key: 'people', actual: 55, target: 85, stage: 1 },
  { key: 'resources', actual: 70, target: 85, stage: 2 },
  { key: 'operations', actual: 58, target: 82, stage: 2 },
]
const PHASES = [
  { stage: 1, key: 'high', done: '100%' },
  { stage: 2, key: 'medium', done: '60%' },
  { stage: 3, key: 'low', done: '0%' },
]
</script>

<template>
  <div v-reveal class="lp-gapmap lp-reveal" aria-hidden="true">
    <div class="lp-gapmap__head">
      <b>{{ t('landing.gapMap.title') }}</b>
      <span class="lp-gapmap__legend">
        <span class="lp-gapmap__key lp-gapmap__key--actual"></span>{{ t('landing.gapMap.actual') }}
        <span class="lp-gapmap__key lp-gapmap__key--gap"></span>{{ t('landing.gapMap.gap') }}
        <span class="lp-gapmap__key lp-gapmap__key--target"></span>{{ t('landing.gapMap.target') }}
      </span>
    </div>
    <ol class="lp-gapmap__rows">
      <li
        v-for="row in ROWS"
        :key="row.key"
        class="lp-gapmap__row"
        :data-stage="row.stage"
        :style="{ '--a': row.actual, '--t': row.target }"
      >
        <span class="lp-gapmap__name">{{ t(`landing.gapMap.rows.${row.key}`) }}</span>
        <span class="lp-gapmap__bar">
          <span class="lp-gapmap__actual"></span><span class="lp-gapmap__gap"></span
          ><span class="lp-gapmap__target"></span>
        </span>
        <span class="lp-gapmap__stage"></span>
      </li>
    </ol>
    <div class="lp-gapmap__phases">
      <div
        v-for="phase in PHASES"
        :key="phase.key"
        class="lp-gapmap__phase"
        :data-phase="phase.stage"
        :style="{ '--w': phase.done }"
      >
        <span>{{ t(`landing.gapMap.phases.${phase.key}`) }}</span>
        <span class="lp-gapmap__phase-bar"></span>
      </div>
    </div>
  </div>
</template>

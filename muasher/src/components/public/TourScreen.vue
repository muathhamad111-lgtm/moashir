<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBrandLogo } from '@/composables/useBrandLogo'
import {
  TOUR_EVIDENCE_FILES,
  TOUR_PATH_TILES,
  TOUR_REGISTER_FIELDS,
  TOUR_RESULT_SCORE,
  TOUR_REVIEW_ROWS,
  TOUR_SCREENS,
} from '@/constants/tourScreens'
import { percentText } from '@/utils/percentText'
import MeterBar from '@/components/public/MeterBar.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  index: { type: Number, required: true },
})

const { n, t } = useI18n()
const { logoOnDark } = useBrandLogo()
const kind = computed(() => TOUR_SCREENS[props.index % TOUR_SCREENS.length])
const label = (key) => t(`public.home.tour.screens.${kind.value}.${key}`)
</script>

<template>
  <div class="tour-screen" :class="`tour-screen--${kind}`" aria-hidden="true">
    <template v-if="kind === 'register'">
      <div class="tour-screen__panel">
        <img class="tour-screen__logo" :src="logoOnDark" alt="" />
        <span class="tour-screen__panel-caption">{{ label('panel') }}</span>
      </div>
      <div class="tour-screen__form">
        <strong class="tour-screen__title">{{ label('title') }}</strong>
        <span v-for="field in TOUR_REGISTER_FIELDS" :key="field" class="tour-screen__field">
          <span class="tour-screen__field-label">{{ label(`fields.${field}`) }}</span>
          <span class="tour-screen__input"></span>
        </span>
        <span class="tour-screen__button">{{ label('submit') }}</span>
      </div>
    </template>

    <div v-else-if="kind === 'path'" class="tour-screen__body">
      <strong class="tour-screen__title">{{ label('title') }}</strong>
      <span class="tour-screen__tiles">
        <span
          v-for="tile in TOUR_PATH_TILES"
          :key="tile"
          class="tour-screen__tile"
          :class="`tour-screen__tile--${tile}`"
        >
          <span class="tour-screen__tile-icon"></span>
          <span class="tour-screen__tile-name">{{ label(`tiles.${tile}`) }}</span>
          <span class="tour-screen__line"></span>
          <span class="tour-screen__line tour-screen__line--short"></span>
        </span>
      </span>
    </div>

    <div v-else-if="kind === 'evidence'" class="tour-screen__body">
      <strong class="tour-screen__title">{{ label('title') }}</strong>
      <span class="tour-screen__dropzone">
        <AppIcon name="cloud-upload" />
        {{ label('dropzone') }}
      </span>
      <span v-for="file in TOUR_EVIDENCE_FILES" :key="file" class="tour-screen__file">
        <AppIcon name="doc" class="tour-screen__file-icon" />
        <bdi class="tour-screen__file-name">{{ label(`files.${file}`) }}</bdi>
        <span class="tour-screen__chip tour-screen__chip--done">{{ label('verified') }}</span>
      </span>
    </div>

    <div v-else-if="kind === 'review'" class="tour-screen__body">
      <strong class="tour-screen__title">{{ label('title') }}</strong>
      <span v-for="row in TOUR_REVIEW_ROWS" :key="row.key" class="tour-screen__row">
        <span class="tour-screen__row-name">{{ label(`rows.${row.key}`) }}</span>
        <span class="tour-screen__chip" :class="`tour-screen__chip--${row.state}`">{{
          label(`states.${row.state}`)
        }}</span>
      </span>
      <span class="tour-screen__note">
        <AppIcon name="chat" />
        {{ label('note') }}
      </span>
    </div>

    <div v-else class="tour-screen__body tour-screen__body--result">
      <strong class="tour-screen__title">{{ label('title') }}</strong>
      <span class="tour-screen__result">
        <bdi class="tour-screen__score" dir="ltr">{{ percentText(n, t, TOUR_RESULT_SCORE) }}</bdi>
        <span class="tour-screen__level">{{ label('level') }}</span>
      </span>
      <MeterBar :value="TOUR_RESULT_SCORE" />
      <span class="tour-screen__actions">
        <span class="tour-screen__button">{{ label('certificate') }}</span>
        <span class="tour-screen__button tour-screen__button--ghost">{{ label('report') }}</span>
      </span>
    </div>
  </div>
</template>

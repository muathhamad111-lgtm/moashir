<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTourStepper } from '@/composables/useTourStepper'
import { useInView } from '@/composables/useInView'
import { useMotionControls } from '@/composables/useMotionControls'
import { contentImageUrl } from '@/utils/contentImage'
import { TOUR_SCREEN_PATHS, TOUR_SCREENS } from '@/constants/tourScreens'
import HomeSectionHead from '@/components/public/HomeSectionHead.vue'
import TourScreen from '@/components/public/TourScreen.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { t } = useI18n()
const rootRef = ref(null)
const { inView } = useInView(rootRef)

const steps = computed(() => props.section.items ?? [])
const count = computed(() => steps.value.length)
const { active, playing, running, select, toggle } = useTourStepper(count, { visible: inView })
const { isVisible: isMotionControlVisible } = useMotionControls()
const current = computed(() => steps.value[active.value] ?? null)
const currentShot = computed(() => contentImageUrl(current.value?.image))
const host = typeof window !== 'undefined' ? window.location.host : ''
const screenPath = computed(
  () => TOUR_SCREEN_PATHS[TOUR_SCREENS[active.value % TOUR_SCREENS.length]],
)
</script>

<template>
  <section
    ref="rootRef"
    class="home-section home-tour"
    :class="{ 'is-running': running }"
    aria-labelledby="home-tour-title"
  >
    <div class="home-section__container">
      <HomeSectionHead :section="section" :localized="localized" title-id="home-tour-title" />
      <div v-if="current" class="home-tour__layout">
        <div class="home-tour__aside">
          <ol
            v-reveal="{ stagger: true }"
            class="home-tour__steps"
            :aria-label="t('public.home.tour.stepsLabel')"
          >
            <li v-for="(step, index) in steps" :key="step.id">
              <button
                type="button"
                class="home-tour__step"
                :class="{ 'is-active': index === active }"
                :aria-current="index === active ? 'step' : undefined"
                @click="select(index)"
              >
                <span class="home-tour__number" aria-hidden="true">{{ index + 1 }}</span>
                <span class="home-tour__text">
                  <span class="home-tour__title">{{ localized(step.title) }}</span>
                  <span v-if="step.description" class="home-tour__desc">{{
                    localized(step.description)
                  }}</span>
                  <span v-if="index === active" class="home-tour__track" aria-hidden="true">
                    <span :key="active" class="home-tour__progress"></span>
                  </span>
                </span>
              </button>
            </li>
          </ol>
          <button
            v-if="isMotionControlVisible"
            type="button"
            class="home-tour__toggle"
            :aria-pressed="playing"
            @click="toggle"
          >
            <AppIcon :name="playing ? 'pause' : 'play'" />
            {{ playing ? t('public.home.tour.pause') : t('public.home.tour.play') }}
          </button>
        </div>

        <div v-reveal class="home-tour__device">
          <div class="home-tour__chrome" aria-hidden="true">
            <span class="home-tour__dots"><span></span><span></span><span></span></span>
            <span class="home-tour__address">
              <AppIcon name="lock" />
              <bdi dir="ltr">{{ host }}{{ screenPath }}</bdi>
            </span>
          </div>
          <div class="home-tour__viewport" :aria-live="running ? 'off' : 'polite'">
            <div :key="current.id" class="home-tour__screen">
              <img
                v-if="currentShot"
                class="home-tour__shot"
                :src="currentShot"
                :alt="localized(current.title)"
                loading="lazy"
              />
              <TourScreen v-else :index="active" />
              <span class="home-tour__sr">
                {{ t('public.home.tour.progress', { n: active + 1, total: count }) }} —
                {{ localized(current.title) }}
              </span>
            </div>
          </div>
          <ol class="home-tour__pager" aria-hidden="true">
            <li
              v-for="(step, index) in steps"
              :key="step.id"
              class="home-tour__pager-dot"
              :class="{ 'is-active': index === active }"
            ></li>
          </ol>
        </div>
      </div>
    </div>
  </section>
</template>

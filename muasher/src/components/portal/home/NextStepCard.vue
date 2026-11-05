<script setup>
import { computed, onMounted, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { nextStepView } from '@/utils/nextStepView'
import { NEXT_STEPS } from '@/composables/useEntityJourney'
import { useAccreditedPrice } from '@/composables/useAccreditedPrice'
import { useCountdown } from '@/composables/useCountdown'
import { formatNumber } from '@/utils/formatNumber'
import { formatRelativeTime, riyadhDateKey } from '@/utils/datetime'
import RemainingSubsList from './RemainingSubsList.vue'
import NextStepProgress from './NextStepProgress.vue'
import StationGuideDialog from './StationGuideDialog.vue'
import BrandPattern from '@/components/ui/BrandPattern.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  step: { type: Object, required: true },
})

const { t, n, locale } = useI18n()
const view = computed(() => nextStepView(props.step))
const guideOpen = ref(false)
const { priceBeforeVat, load: loadPrice } = useAccreditedPrice({ auto: false })
const deadline = toRef(() => (props.step.kind === NEXT_STEPS.PAYMENT ? props.step.expiresAt : null))
const { remaining: secondsLeft, expired } = useCountdown(deadline)

const progress = computed(() => props.step.progress ?? null)
const remaining = computed(() => progress.value?.remaining ?? [])
const plural = computed(() => (progress.value ? remaining.value.length : 1))
const minutesLeft = computed(() => Math.max(1, Math.ceil(secondsLeft.value / 60)))
const lastSaved = computed(() =>
  props.step.updatedAt ? formatRelativeTime(props.step.updatedAt, locale.value) : '',
)
const showsPrice = computed(
  () => props.step.kind === NEXT_STEPS.ACCREDITED_START && priceBeforeVat.value !== null,
)

onMounted(() => {
  if (props.step.kind === NEXT_STEPS.ACCREDITED_START) loadPrice()
})
</script>

<template>
  <section class="next-step" aria-labelledby="next-step-title" data-testid="next-step">
    <span class="next-step__pattern"><BrandPattern variant="lattice" /></span>
    <div class="next-step__main">
      <p class="next-step__eyebrow">{{ t('portal.home.next.eyebrow') }}</p>
      <h2 id="next-step-title" class="next-step__title">{{ t(view.titleKey) }}</h2>
      <i18n-t
        :keypath="view.textKey"
        :plural="plural"
        scope="global"
        tag="p"
        class="next-step__text"
      >
        <template #count>{{ formatNumber(n, plural) }}</template>
        <template #reference>
          <bdi dir="ltr">{{ step.reference }}</bdi>
        </template>
      </i18n-t>

      <p v-if="step.dueAt" class="next-step__due">
        <AppIcon name="calendar" />
        <i18n-t keypath="portal.home.next.due" scope="global" tag="span">
          <template #date>
            <bdi dir="ltr">{{ riyadhDateKey(step.dueAt) }}</bdi>
          </template>
        </i18n-t>
      </p>

      <p v-if="showsPrice" class="next-step__price">
        {{ t('portal.home.next.price', { price: formatNumber(n, priceBeforeVat, 'money') }) }}
      </p>

      <p
        v-if="step.kind === NEXT_STEPS.PAYMENT && !expired"
        class="next-step__countdown"
        aria-live="polite"
      >
        <AppIcon name="refresh-dots" />
        {{ t('portal.home.next.expiresIn', { count: minutesLeft }, minutesLeft) }}
      </p>

      <NextStepProgress v-if="progress" :progress="progress" />

      <ul v-if="step.kind === NEXT_STEPS.EVIDENCE" class="next-step__chips">
        <li class="next-step__chip">
          <bdi dir="ltr">{{ step.reference }}</bdi>
        </li>
        <li v-if="lastSaved" class="next-step__chip">
          {{ t('portal.home.next.lastSaved', { time: lastSaved }) }}
        </li>
        <li class="next-step__chip">{{ t('portal.home.next.reviewAfterSubmit') }}</li>
      </ul>

      <div class="next-step__actions">
        <router-link class="next-step__action next-step__action--primary" :to="view.primary.to">
          <span>{{ t(view.primary.labelKey) }}</span>
          <AppIcon :name="view.primary.icon" />
        </router-link>
        <button
          v-if="view.showsGuide"
          type="button"
          class="next-step__action next-step__action--ghost"
          @click="guideOpen = true"
        >
          <AppIcon name="question-circle" />
          <span>{{ t('portal.home.next.actions.guide') }}</span>
        </button>
        <router-link
          v-else-if="view.secondary"
          class="next-step__action next-step__action--ghost"
          :to="view.secondary.to"
        >
          <AppIcon :name="view.secondary.icon" />
          <span>{{ t(view.secondary.labelKey) }}</span>
        </router-link>
      </div>
    </div>

    <RemainingSubsList v-if="remaining.length" class="next-step__aside" :items="remaining" />

    <StationGuideDialog v-model="guideOpen" />
  </section>
</template>

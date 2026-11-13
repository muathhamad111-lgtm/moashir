<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { nextStepView } from '@/utils/nextStepView'
import BrandPattern from '@/components/ui/BrandPattern.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import NextStepPayment from './NextStepPayment.vue'

const props = defineProps({
  step: { type: Object, required: true },
})

const { t } = useI18n()
const view = computed(() => nextStepView(props.step))
</script>

<template>
  <section
    class="next-step"
    aria-labelledby="next-step-title"
    data-testid="next-step"
    :data-rank="step.rank"
  >
    <span class="next-step__pattern"><BrandPattern variant="lattice" /></span>
    <div class="next-step__main">
      <p class="next-step__eyebrow">{{ t('portal.home.next.eyebrow') }}</p>
      <h2 id="next-step-title" class="next-step__title">{{ t(view.titleKey) }}</h2>
      <i18n-t :keypath="view.textKey" scope="global" tag="p" class="next-step__text">
        <template #number>
          <bdi dir="ltr">{{ step.context.number }}</bdi>
        </template>
        <template #date>
          <bdi dir="ltr">{{ view.date }}</bdi>
        </template>
        <template #path>{{ t(`paths.names.${view.pathKey}`) }}</template>
        <template #reference>
          <bdi dir="ltr">{{ step.context.paymentReference }}</bdi>
        </template>
      </i18n-t>
      <NextStepPayment v-if="view.payment" :payment="view.payment" />
      <div v-if="view.to" class="next-step__actions">
        <router-link class="next-step__action next-step__action--primary" :to="view.to">
          <span>{{ t(view.actionKey) }}</span>
          <AppIcon name="arrow-right" />
        </router-link>
      </div>
    </div>
  </section>
</template>

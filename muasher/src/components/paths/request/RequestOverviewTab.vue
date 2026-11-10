<script setup>
import { computed, onMounted, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { accreditedService } from '@/services/accreditedService'
import { ENTITY_EDITABLE_STATUSES, ACCREDITED_STATUSES } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import { isoDate } from '@/utils/datetime'
import { percentText } from '@/utils/percentText'
import { statusLabelKey } from '@/utils/canonicalStatus'
import AssessmentStatusHistory from '@/components/assessment/AssessmentStatusHistory.vue'
import ProgressMeter from '@/components/ui/ProgressMeter.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  record: { type: Object, required: true },
})

const { t, n, locale } = useI18n()
const progress = shallowRef(null)
const isEditable = computed(() => ENTITY_EDITABLE_STATUSES.includes(props.record.status))
const needsCompletion = computed(() => props.record.status === ACCREDITED_STATUSES.NEEDS_COMPLETION)
const formRoute = computed(() => ({
  name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM,
  params: { id: String(props.record.id) },
}))
const payment = computed(() => props.record.payment)
const facts = computed(() => [
  { key: 'number', value: props.record.number, ltr: true },
  { key: 'period', value: localizedText(props.record.periodLabel, locale.value) },
  { key: 'submittedAt', value: isoDate(props.record.submittedAt), ltr: true },
  { key: 'completedAt', value: isoDate(props.record.completedAt), ltr: true },
  {
    key: 'result',
    value: props.record.result ? percentText(n, t, props.record.result.percent) : '',
    ltr: true,
  },
  { key: 'level', value: props.record.level ? t(`level.names.${props.record.level}`) : '' },
])

onMounted(async () => {
  if (!isEditable.value) return
  const current = await accreditedService
    .getCurrent()
    .then(({ data }) => data)
    .catch(() => null)
  if (current?.active?.id === props.record.id) progress.value = current.active.progress
})
</script>

<template>
  <div class="request-overview" data-testid="request-tab-request">
    <section v-if="isEditable" class="request-overview__task" data-testid="request-task">
      <h2 class="request-overview__title">
        {{ t(needsCompletion ? 'paths.request.task.completion' : 'paths.request.task.draft') }}
      </h2>
      <ProgressMeter
        v-if="progress"
        :label="t('paths.request.task.progress')"
        :current="progress.subsComplete"
        :total="progress.subsTotal"
      />
      <router-link class="btn btn--primary" :to="formRoute" data-testid="request-open-form">
        {{ t(needsCompletion ? 'paths.actions.complete' : 'paths.actions.continueRequest') }}
        <AppIcon name="arrow-right" />
      </router-link>
    </section>
    <div class="request-overview__grid">
      <section class="request-overview__card" aria-labelledby="request-card-title">
        <h2 id="request-card-title" class="request-overview__title">
          {{ t('paths.request.card.title') }}
        </h2>
        <dl class="request-overview__facts">
          <div v-for="fact in facts" :key="fact.key" class="request-overview__fact">
            <dt>{{ t(`paths.request.card.${fact.key}`) }}</dt>
            <dd>
              <bdi v-if="fact.ltr && fact.value" dir="ltr">{{ fact.value }}</bdi>
              <template v-else>{{ fact.value || '—' }}</template>
            </dd>
          </div>
        </dl>
      </section>
      <section class="request-overview__card" aria-labelledby="request-payment-title">
        <h2 id="request-payment-title" class="request-overview__title">
          {{ t('paths.request.payment.title') }}
        </h2>
        <dl v-if="payment" class="request-overview__facts" data-testid="request-payment">
          <div class="request-overview__fact">
            <dt>{{ t('paths.request.payment.reference') }}</dt>
            <dd>
              <bdi dir="ltr">{{ payment.reference }}</bdi>
            </dd>
          </div>
          <div class="request-overview__fact">
            <dt>{{ t('paths.request.payment.channel') }}</dt>
            <dd>{{ payment.channel ? t(`finance.channels.${payment.channel}`) : '—' }}</dd>
          </div>
          <div class="request-overview__fact">
            <dt>{{ t('paths.request.payment.status') }}</dt>
            <dd>{{ t(statusLabelKey(payment.status)) }}</dd>
          </div>
          <div class="request-overview__fact">
            <dt>{{ t('paths.request.payment.paidAt') }}</dt>
            <dd>
              <bdi dir="ltr">{{ isoDate(payment.paidAt) || '—' }}</bdi>
            </dd>
          </div>
          <div class="request-overview__fact">
            <dt>{{ t('paths.request.payment.receipt') }}</dt>
            <dd>
              <bdi v-if="payment.receipt" dir="ltr">{{ payment.receipt.number }}</bdi>
              <template v-else>—</template>
            </dd>
          </div>
        </dl>
        <p v-else class="request-overview__muted">{{ t('paths.request.payment.none') }}</p>
        <router-link class="request-overview__link" :to="{ name: ROUTE_NAMES.PAYMENTS_MINE }">
          {{ t('paths.request.payment.open') }}
        </router-link>
      </section>
    </div>
    <section class="request-overview__card" aria-labelledby="request-timeline-title">
      <h2 id="request-timeline-title" class="request-overview__title">
        {{ t('paths.request.timeline') }}
      </h2>
      <AssessmentStatusHistory :assessment-id="record.id" />
    </section>
  </div>
</template>

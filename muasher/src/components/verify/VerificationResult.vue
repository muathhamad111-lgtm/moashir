<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CERTIFICATE_SEALS } from '@/constants/seals'
import { VERIFICATION_RESULTS } from '@/constants/certificates'
import { isoDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { percentText } from '@/utils/percentText'
import AppIcon from '@/components/ui/AppIcon.vue'

const ICONS = {
  [VERIFICATION_RESULTS.VALID]: 'check-circle',
  [VERIFICATION_RESULTS.UNDER_REVIEW]: 'pause',
  [VERIFICATION_RESULTS.EXPIRED]: 'clock',
  [VERIFICATION_RESULTS.REVOKED]: 'x',
  [VERIFICATION_RESULTS.SUPERSEDED]: 'refresh',
  [VERIFICATION_RESULTS.NOT_FOUND]: 'question-circle',
  [VERIFICATION_RESULTS.UNVERIFIABLE]: 'x',
}

const props = defineProps({
  verification: { type: Object, required: true },
  code: { type: String, required: true },
})

const { t, n, locale } = useI18n()
const certificate = computed(() => props.verification.certificate)
const result = computed(() => props.verification.result)
const seal = computed(() => CERTIFICATE_SEALS[certificate.value?.level] ?? null)
const level = computed(() => localizedText(certificate.value?.levelLabel, locale.value))
const entityName = computed(() => localizedText(certificate.value?.entityName, locale.value))
const issuedOn = computed(() => isoDate(certificate.value?.issuedAt))
const percent = computed(() =>
  certificate.value?.percent ? percentText(n, t, Number(certificate.value.percent)) : '',
)
</script>

<template>
  <section
    class="verification-result"
    :class="`verification-result--${result.replaceAll('_', '-')}`"
    aria-live="polite"
    aria-labelledby="verification-title"
  >
    <header class="verification-result__head">
      <AppIcon :name="ICONS[result]" class="verification-result__icon" />
      <div>
        <h2 id="verification-title" class="verification-result__title">
          {{ t(`verify.results.${result}.title`) }}
        </h2>
        <p class="verification-result__text">{{ t(`verify.results.${result}.text`) }}</p>
      </div>
    </header>
    <div v-if="certificate" class="verification-result__card">
      <img v-if="seal" class="verification-result__seal" :src="seal" alt="" />
      <dl class="verification-result__facts">
        <div v-if="entityName">
          <dt>{{ t('verify.fields.entity') }}</dt>
          <dd>{{ entityName }}</dd>
        </div>
        <div v-if="level">
          <dt>{{ t('verify.fields.level') }}</dt>
          <dd>{{ level }}</dd>
        </div>
        <div v-if="percent">
          <dt>{{ t('verify.fields.percent') }}</dt>
          <dd>
            <bdi dir="ltr">{{ percent }}</bdi>
          </dd>
        </div>
        <div>
          <dt>{{ t('verify.fields.number') }}</dt>
          <dd>
            <bdi dir="ltr">{{ certificate.number }}</bdi>
          </dd>
        </div>
        <div>
          <dt>{{ t('verify.fields.issuedOn') }}</dt>
          <dd>
            <bdi dir="ltr">{{ issuedOn }}</bdi>
          </dd>
        </div>
        <div class="verification-result__wide">
          <dt>{{ t('verify.fields.code') }}</dt>
          <dd>
            <bdi dir="ltr">{{ code }}</bdi>
          </dd>
        </div>
      </dl>
    </div>
  </section>
</template>

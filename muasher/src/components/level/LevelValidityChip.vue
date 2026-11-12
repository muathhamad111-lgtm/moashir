<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CERTIFICATE_VALIDITY } from '@/constants/certificates'
import { formatNumber } from '@/utils/formatNumber'

const MODIFIERS = Object.freeze({
  [CERTIFICATE_VALIDITY.VALID]: 'valid',
  [CERTIFICATE_VALIDITY.EXPIRING_SOON]: 'expiring',
  [CERTIFICATE_VALIDITY.EXPIRED]: 'expired',
})

const props = defineProps({
  validity: {
    type: String,
    required: true,
    validator: (value) => Object.values(CERTIFICATE_VALIDITY).includes(value),
  },
  validUntil: { type: String, default: null },
  daysLeft: { type: Number, default: null },
})

const { n } = useI18n()

const keypath = computed(() => {
  if (props.validity === CERTIFICATE_VALIDITY.EXPIRING_SOON)
    return 'level.hero.validity.expiringSoon'
  if (props.validity === CERTIFICATE_VALIDITY.EXPIRED) return 'level.hero.validity.expired'
  return props.validUntil ? 'level.hero.validity.valid' : 'level.hero.validity.validOpen'
})
const count = computed(() => props.daysLeft ?? 0)
</script>

<template>
  <span
    class="level-hero__chip"
    :class="`level-hero__chip--${MODIFIERS[validity]}`"
    data-testid="level-validity"
    :data-validity="validity"
  >
    <span class="level-hero__chip-dot" aria-hidden="true"></span>
    <i18n-t :keypath="keypath" :plural="count" scope="global" tag="span">
      <template #date>
        <bdi dir="ltr">{{ validUntil }}</bdi>
      </template>
      <template #count>{{ formatNumber(n, count) }}</template>
    </i18n-t>
  </span>
</template>

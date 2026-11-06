<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import { formatDate } from '@/utils/datetime'
import { CHECK_DETAILS, CONNECTION_CHECKS, CONNECTION_INFO_KEYS } from '@/constants/integrations'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  result: { type: Object, required: true },
})

const { t, te, n, locale } = useI18n()
const passed = computed(() => props.result.ok !== false)

const checks = computed(() =>
  (props.result.checks ?? []).map((item) => ({
    key: item.key,
    ok: item.ok,
    label: t(
      CONNECTION_CHECKS.includes(item.key)
        ? `integrations.checks.${item.key}`
        : 'integrations.checks.other',
    ),
    detail: CHECK_DETAILS.includes(item.detailCode)
      ? t(`integrations.checkDetails.${item.detailCode}`)
      : '',
  })),
)

function translatedValue(key, value) {
  const path = `integrations.info.values.${key}.${value}`
  return te(path) ? t(path) : String(value)
}

function infoValue(key, value) {
  if (typeof value === 'boolean') return t(value ? 'common.yes' : 'common.no')
  if (key === 'balance')
    return props.result.info?.currency
      ? t('integrations.info.balanceValue', {
          amount: value,
          currency: translatedValue('currency', props.result.info.currency),
        })
      : String(value)
  if (key === 'accountExpiryDate') return formatDate(value, locale.value, { dateStyle: 'medium' })
  if (key === 'modelsCount') return formatNumber(n, value, 'integer')
  return translatedValue(key, value)
}

const facts = computed(() =>
  CONNECTION_INFO_KEYS.filter(
    (key) => props.result.info?.[key] !== undefined && props.result.info?.[key] !== null,
  ).map((key) => ({
    key,
    label: t(`integrations.info.${key}`),
    value: infoValue(key, props.result.info[key]),
  })),
)

const latency = computed(() =>
  props.result.latencyMs
    ? t('integrations.feedback.latency', { ms: formatNumber(n, props.result.latencyMs, 'integer') })
    : '',
)
</script>

<template>
  <div
    class="test-result"
    :class="{ 'test-result--failed': !passed }"
    :role="passed ? 'status' : undefined"
  >
    <p class="test-result__summary">
      <AppIcon :name="passed ? 'check-circle' : 'x'" />
      {{ t(passed ? 'integrations.feedback.testPassed' : 'integrations.feedback.testFailed') }}
      <span v-if="latency" class="test-result__latency">{{ latency }}</span>
    </p>
    <ul v-if="checks.length" class="test-result__checks">
      <li
        v-for="item in checks"
        :key="item.key"
        class="test-result__check"
        :class="{ 'test-result__check--warn': !item.ok }"
      >
        <AppIcon :name="item.ok ? 'check' : 'warning-triangle'" />
        <span>{{ item.label }}</span>
        <span class="test-result__check-state">{{
          t(item.ok ? 'integrations.feedback.checkOk' : 'integrations.feedback.checkFailed')
        }}</span>
        <span v-if="item.detail" class="test-result__detail">{{ item.detail }}</span>
      </li>
    </ul>
    <dl v-if="facts.length" class="test-result__facts">
      <div v-for="fact in facts" :key="fact.key" class="test-result__fact">
        <dt>{{ fact.label }}</dt>
        <dd>{{ fact.value }}</dd>
      </div>
    </dl>
  </div>
</template>

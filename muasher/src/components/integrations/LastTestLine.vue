<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDate } from '@/utils/datetime'
import { knownErrorKind } from '@/utils/integrations/providerError'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  lastTest: { type: Object, default: null },
})

const { t, locale } = useI18n()

const summary = computed(() => {
  if (!props.lastTest) return t('integrations.lastTest.never')
  const at = formatDate(props.lastTest.at, locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
  const by = props.lastTest.by?.name
  return by ? t('integrations.lastTest.atBy', { at, by }) : t('integrations.lastTest.at', { at })
})

const outcome = computed(() => {
  if (!props.lastTest) return ''
  if (props.lastTest.ok) return t('integrations.lastTest.passed')
  return t('integrations.lastTest.failed', {
    reason: t(`integrations.errorKinds.${knownErrorKind(props.lastTest.errorKind)}`),
  })
})
</script>

<template>
  <p class="last-test" :class="{ 'last-test--failed': lastTest && !lastTest.ok }">
    <AppIcon name="refresh-dots" />
    <span>{{ summary }}</span>
    <span v-if="outcome" class="last-test__outcome">{{ outcome }}</span>
  </p>
</template>

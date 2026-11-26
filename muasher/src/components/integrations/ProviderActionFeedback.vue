<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { TEST_FAILURES, integrationErrorKey } from '@/utils/integrations/providerError'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  failure: { type: Object, default: null },
})

const { t } = useI18n()

const message = computed(() => {
  const failure = props.failure
  if (!failure) return ''
  if (failure.type === TEST_FAILURES.PROVIDER) return t(`integrations.errorKinds.${failure.kind}`)
  if (failure.type === TEST_FAILURES.RATE_LIMITED) {
    return failure.retryAfter
      ? t('integrations.feedback.rateLimitedIn', { seconds: failure.retryAfter })
      : t('integrations.feedback.rateLimited')
  }
  if (failure.type === TEST_FAILURES.FORBIDDEN) return t('integrations.feedback.forbidden')
  if (failure.type === TEST_FAILURES.REJECTED) return t(integrationErrorKey(failure.code))
  if (failure.type === TEST_FAILURES.INVALID)
    return failure.fieldMessage || failure.message || t('integrations.feedback.invalid')
  return t('integrations.feedback.unexpected')
})

const icon = computed(() =>
  props.failure?.type === TEST_FAILURES.RATE_LIMITED ? 'refresh-dots' : 'x',
)
</script>

<template>
  <p
    v-if="failure"
    class="provider-feedback"
    :class="`provider-feedback--${failure.type}`"
    role="alert"
  >
    <AppIcon :name="icon" />
    <span>{{ message }}</span>
  </p>
</template>

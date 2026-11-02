<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import RecordedStamp from '@/components/integrations/RecordedStamp.vue'
import { INTEGRATION_LIMITS } from '@/constants/integrations'

const props = defineProps({
  file: { type: Object, default: null },
  checkResult: { type: Object, default: null },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['upload', 'remove', 'check'])
const { t, n } = useI18n()
const inputRef = ref(null)
const inputId = useId()
const sizeError = ref('')
const stored = computed(() => props.file?.set === true)

const sizeLine = computed(() =>
  stored.value ? t('integrations.payment.applePay.fileSize', { size: formatNumber(n, props.file.sizeBytes, 'integer') }) : t('integrations.payment.applePay.fileMissing'),
)

const checkState = computed(() => {
  const result = props.checkResult
  if (!result) return null
  if (result.reachable && result.matches) return { variant: 'success', label: t('integrations.payment.applePay.reachable') }
  if (result.reachable) return { variant: 'warning', label: t('integrations.payment.applePay.contentDiffers') }
  const status = result.status ? t('integrations.payment.applePay.httpStatus', { status: result.status }) : ''
  return { variant: 'danger', label: `${t('integrations.payment.applePay.unreachable')} ${status}`.trim() }
})

function onPick(event) {
  const [picked] = Array.from(event.target.files ?? [])
  event.target.value = ''
  if (!picked) return
  if (picked.size > INTEGRATION_LIMITS.APPLE_PAY_FILE_MAX_BYTES) {
    sizeError.value = t('integrations.payment.applePay.fileTooLarge')
    return
  }
  sizeError.value = ''
  emit('upload', picked)
}
</script>

<template>
  <div class="association-file">
    <span :id="inputId" class="association-file__label">{{ t('integrations.payment.applePay.fileLabel') }}</span>
    <p class="association-file__state">
      <i :class="stored ? 'fa-solid fa-file-circle-check' : 'fa-solid fa-file-circle-question'" aria-hidden="true"></i>
      {{ sizeLine }}
    </p>
    <RecordedStamp v-if="stored" kind="uploaded" :at="file.uploadedAt" :by="file.uploadedBy" />
    <p v-if="stored && file.sha256" class="association-file__digest">
      <span>{{ t('integrations.payment.applePay.fingerprint') }}</span>
      <code dir="ltr">{{ file.sha256 }}</code>
    </p>
    <input ref="inputRef" type="file" hidden accept="text/plain,.txt" :aria-labelledby="inputId" :disabled="busy" @change="onPick" />
    <div class="association-file__actions">
      <BaseButton variant="secondary" size="sm" icon="fa-solid fa-upload" :disabled="busy" @click="inputRef.click()">
        {{ t(stored ? 'integrations.payment.applePay.replaceFile' : 'integrations.payment.applePay.uploadFile') }}
      </BaseButton>
      <BaseButton v-if="stored" variant="ghost" size="sm" icon="fa-solid fa-trash-can" :disabled="busy" @click="emit('remove')">
        {{ t('integrations.payment.applePay.removeFile') }}
      </BaseButton>
      <BaseButton v-if="stored" variant="ghost" size="sm" icon="fa-solid fa-globe" :disabled="busy" @click="emit('check')">
        {{ t('integrations.payment.applePay.checkReachable') }}
      </BaseButton>
      <Badge v-if="checkState" :variant="checkState.variant">{{ checkState.label }}</Badge>
    </div>
    <p class="field__hint">{{ t('integrations.payment.applePay.fileHint') }}</p>
    <p v-if="sizeError" class="field__error" role="alert">{{ sizeError }}</p>
  </div>
</template>

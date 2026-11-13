<script setup>
import { computed, onBeforeUnmount, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from './BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useSuccess } from '@/composables/useSuccess'
import { DECISION_ICONS, MODAL_TONES, SUCCESS_AUTO_CLOSE_MS } from '@/constants/modal'

const props = defineProps({
  autoCloseMs: { type: Number, default: SUCCESS_AUTO_CLOSE_MS },
})

const { t } = useI18n()
const { successState, closeSuccess } = useSuccess()
const messageId = useId()
let timer = null

const isOpen = computed({
  get: () => successState.isOpen,
  set: (value) => {
    if (!value) closeSuccess()
  },
})

function stopTimer() {
  clearTimeout(timer)
  timer = null
}

function startTimer() {
  stopTimer()
  if (props.autoCloseMs > 0) timer = setTimeout(closeSuccess, props.autoCloseMs)
}

watch(
  () => [successState.isOpen, successState.sequence],
  ([open]) => (open ? startTimer() : stopTimer()),
)

onBeforeUnmount(stopTimer)
</script>

<template>
  <BaseModal
    v-model="isOpen"
    size="sm"
    :title="successState.title || t('components.success.title')"
    :icon="DECISION_ICONS[MODAL_TONES.SUCCESS]"
    :tone="MODAL_TONES.SUCCESS"
    :described-by="successState.message ? messageId : ''"
    @interact="stopTimer"
  >
    <template v-if="successState.message" #default>
      <p :id="messageId" class="modal__message">
        <bdi>{{ successState.message }}</bdi>
      </p>
    </template>
    <template #footer>
      <BaseButton variant="primary" data-autofocus @click="closeSuccess">
        {{ t('actions.ok') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>

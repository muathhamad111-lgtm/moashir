<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { FORM_STATES, useMoyasarForm } from '@/composables/useMoyasarForm'

const props = defineProps({
  payment: { type: Object, required: true },
  checkout: { type: Object, required: true },
})

const emit = defineEmits(['verified', 'verify-failed'])

const { t } = useI18n()
const host = ref(null)
const { state, verifying, mount } = useMoyasarForm({
  onVerified: (payment, verification) => emit('verified', payment, verification),
  onVerifyFailed: (error) => emit('verify-failed', error),
})

function mountForm() {
  return mount(host.value, { payment: props.payment, checkout: props.checkout })
}

onMounted(mountForm)
</script>

<template>
  <div class="moyasar-host" :aria-busy="state === FORM_STATES.LOADING || verifying || undefined">
    <div v-if="state === FORM_STATES.LOADING" class="moyasar-host__loading" role="status">
      <Skeleton variant="text" :count="4" />
      <span class="moyasar-host__loading-label">{{ t('finance.checkout.formLoading') }}</span>
    </div>
    <ErrorState
      v-if="state === FORM_STATES.ERROR"
      :message="t('finance.checkout.formUnavailable')"
      @retry="mountForm"
    />
    <p v-if="verifying" class="moyasar-host__verifying" role="status">
      <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
      {{ t('finance.checkout.verifying') }}
    </p>
    <div v-show="state === FORM_STATES.READY" ref="host" class="mysr-form moyasar-host__form"></div>
  </div>
</template>

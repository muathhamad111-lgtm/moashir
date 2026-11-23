<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import NdaAgreementText from './NdaAgreementText.vue'
import { useNdaGateStore } from '@/stores/ndaGate'

const { t, locale } = useI18n()
const gate = useNdaGateStore()
const reachedEnd = ref(false)

const title = computed(() => gate.version?.title?.[locale.value] || t('nda.modal.title'))

watch(
  () => gate.version?.id,
  () => {
    reachedEnd.value = false
  },
)
</script>

<template>
  <BaseModal
    :model-value="gate.open"
    :title="title"
    :subtitle="t('nda.modal.subtitle')"
    :dismissible="false"
    size="lg"
  >
    <div v-if="gate.status === 'loading'" aria-busy="true">
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState
      v-else-if="gate.status === 'error'"
      :message="gate.errorMessage || t('nda.modal.loadFailed')"
      @retry="gate.load"
    />
    <NdaAgreementText
      v-else-if="gate.version"
      :agreement="gate.version"
      show-changes
      @reached-end="reachedEnd = true"
    />
    <template #footer>
      <p v-if="gate.status === 'ready' && !reachedEnd" class="nda-dialog__hint" aria-live="polite">
        {{ t('nda.modal.scrollHint') }}
      </p>
      <p v-if="gate.errorMessage && gate.status === 'ready'" class="nda-dialog__error" role="alert">
        {{ gate.errorMessage }}
      </p>
      <BaseButton
        variant="primary"
        data-testid="nda-accept"
        :disabled="gate.status !== 'ready' || !reachedEnd"
        :loading="gate.accepting"
        @click="gate.accept"
      >
        {{ t('nda.modal.accept') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import NdaAgreementText from '@/components/identity/nda/NdaAgreementText.vue'

defineProps({
  agreement: { type: Object, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['accept', 'back'])

const { t, locale } = useI18n()
const reachedEnd = ref(false)
</script>

<template>
  <section
    class="auth-form__body"
    data-testid="invitation-nda"
    aria-labelledby="invitation-nda-title"
  >
    <h2 id="invitation-nda-title" class="auth-form__legend">
      {{ agreement.title?.[locale] || t('nda.modal.title') }}
    </h2>
    <NdaAgreementText :agreement="agreement" @reached-end="reachedEnd = true" />
    <p v-if="!reachedEnd" class="auth-form__hint" aria-live="polite">
      {{ t('nda.modal.scrollHint') }}
    </p>
    <div class="auth-form__actions">
      <BaseButton
        variant="primary"
        data-testid="invitation-nda-accept"
        :disabled="!reachedEnd"
        :loading="busy"
        @click="emit('accept')"
      >
        {{ t('nda.invitation.accept') }}
      </BaseButton>
      <BaseButton variant="ghost" :disabled="busy" @click="emit('back')">
        {{ t('nda.invitation.back') }}
      </BaseButton>
    </div>
  </section>
</template>

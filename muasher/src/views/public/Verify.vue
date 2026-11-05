<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import VerificationResult from '@/components/verify/VerificationResult.vue'
import { useCertificateVerification } from '@/composables/useCertificateVerification'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { VERIFICATION_CODE_PATTERN } from '@/constants/certificates'
import { normalizeVerificationCode } from '@/utils/certificates/verificationCode'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const code = computed(() => (route.params.code ? String(route.params.code) : ''))
const draft = ref(code.value)
const inputError = ref('')
const { verification, status, load } = useCertificateVerification(code)

useHead({ title: () => t('verify.title') })

watch(code, (value) => {
  draft.value = value
})

function submit() {
  const value = normalizeVerificationCode(draft.value)
  inputError.value = VERIFICATION_CODE_PATTERN.test(value) ? '' : t('verify.form.invalid')
  if (inputError.value) return
  if (value === code.value) load()
  else router.push({ name: ROUTE_NAMES.VERIFY_CERTIFICATE, params: { code: value } })
}
</script>

<template>
  <section class="verify-page">
    <header class="verify-page__header">
      <p class="verify-page__eyebrow">{{ t('verify.eyebrow') }}</p>
      <h1 class="verify-page__title">{{ t('verify.title') }}</h1>
      <p class="verify-page__subtitle">{{ t('verify.subtitle') }}</p>
    </header>

    <form class="verify-page__form" novalidate @submit.prevent="submit">
      <BaseInput
        v-model="draft"
        name="verification-code"
        :label="t('verify.form.label')"
        :hint="t('verify.form.hint')"
        :error="inputError"
        input-dir="ltr"
        autocomplete="off"
        required
      />
      <BaseButton type="submit" variant="primary" icon="search" :loading="status === 'loading'">
        {{ t('verify.form.submit') }}
      </BaseButton>
    </form>

    <Skeleton v-if="status === 'loading'" variant="block" />
    <p v-else-if="status === 'rate-limited'" class="verify-page__alert" role="alert">
      {{ t('verify.rateLimited') }}
    </p>
    <ErrorState v-else-if="status === 'error'" :message="t('verify.failed')" @retry="load" />
    <VerificationResult
      v-else-if="status === 'success' && verification"
      :verification="verification"
      :code="code"
    />
  </section>
</template>

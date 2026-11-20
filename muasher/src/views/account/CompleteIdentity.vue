<script setup>
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import NationalIdField from '@/components/identity/NationalIdField.vue'
import { useNationalIdEntry } from '@/composables/useNationalIdEntry'

const { t } = useI18n()
const { form, signOut } = useNationalIdEntry()

useHead({ title: () => t('nationalIdentity.complete.title') })
</script>

<template>
  <section class="auth-form" data-testid="complete-identity">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('nationalIdentity.complete.title') }}</h1>
      <p class="auth-form__subtitle">{{ t('nationalIdentity.complete.subtitle') }}</p>
    </header>

    <form class="auth-form__body" novalidate @submit.prevent="form.onSubmit">
      <fieldset class="auth-form__group" :disabled="form.submitting.value">
        <NationalIdField />
      </fieldset>
      <div class="auth-form__actions">
        <BaseButton type="submit" variant="primary" :loading="form.submitting.value">
          {{ t('nationalIdentity.complete.submit') }}
        </BaseButton>
      </div>
    </form>

    <p class="auth-form__switch">
      <button type="button" class="auth-form__link" @click="signOut">
        {{ t('nationalIdentity.complete.signOut') }}
      </button>
    </p>
  </section>
</template>

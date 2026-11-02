<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AccountCredentialsFields from '@/components/entities/form/AccountCredentialsFields.vue'
import EntityIdentityFields from '@/components/entities/form/EntityIdentityFields.vue'
import EntityClassificationFields from '@/components/entities/form/EntityClassificationFields.vue'
import EntityLocationFields from '@/components/entities/form/EntityLocationFields.vue'
import EntityMetricsFields from '@/components/entities/form/EntityMetricsFields.vue'
import TermsConsentFields from '@/components/entities/form/TermsConsentFields.vue'
import { useEntityRegistration } from '@/composables/useEntityRegistration'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const { form, terms, status, load, regions } = useEntityRegistration()

onMounted(load)
</script>

<template>
  <section class="auth-form entity-register">
    <header class="auth-form__header">
      <h1 class="auth-form__title">{{ t('entities.registration.title') }}</h1>
      <p class="auth-form__subtitle">{{ t('entities.registration.subtitle') }}</p>
    </header>

    <div v-if="status === 'loading'" class="entity-register__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="t('entities.registration.loadFailed')" @retry="load" />

    <EmptyState
      v-else-if="status === 'unavailable'"
      icon="fa-regular fa-clock"
      :title="t('entities.registration.unavailableTitle')"
      :message="t('entities.registration.unavailableMessage')"
    />

    <form v-else class="auth-form__body entity-form" novalidate @submit.prevent="form.onSubmit">
      <fieldset class="entity-form__sections" :disabled="form.submitting.value">
        <AccountCredentialsFields />
        <EntityIdentityFields />
        <EntityClassificationFields />
        <EntityLocationFields :region-options="regions.regionOptions.value" :city-options-for="regions.cityOptionsFor" />
        <EntityMetricsFields :hint="t('entities.hints.metricsLater')" />
        <TermsConsentFields :terms="terms" />
      </fieldset>

      <div class="auth-form__actions">
        <BaseButton type="submit" variant="primary" :loading="form.submitting.value">
          {{ t('entities.registration.submit') }}
        </BaseButton>
      </div>

      <p class="auth-form__switch">
        {{ t('entities.registration.haveAccount') }}
        <router-link class="auth-form__link" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }">
          {{ t('auth.login.title') }}
        </router-link>
      </p>
    </form>
  </section>
</template>

<script setup>
import { nextTick, onMounted, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import WizardStepper from '@/components/wizard/WizardStepper.vue'
import RegistrationEntityStep from '@/components/entities/registration/RegistrationEntityStep.vue'
import RegistrationAccountStep from '@/components/entities/registration/RegistrationAccountStep.vue'
import { useEntityRegistration } from '@/composables/useEntityRegistration'
import { useRegistrationSteps } from '@/composables/useRegistrationSteps'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const { form, status, load, regions, underReview, nationalIdRequired } = useEntityRegistration()
const wizard = useRegistrationSteps(form)
const stepPanel = useTemplateRef('stepPanel')

const FIRST_INVALID = '[aria-invalid="true"]'

async function nextStep() {
  if (await wizard.next()) return
  await nextTick()
  stepPanel.value?.querySelector(FIRST_INVALID)?.focus()
}

function submitStep() {
  return wizard.isLast.value ? form.onSubmit() : nextStep()
}

watch(wizard.currentIndex, async () => {
  await nextTick()
  stepPanel.value?.focus()
})

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

    <ErrorState
      v-else-if="status === 'error'"
      :message="t('entities.registration.loadFailed')"
      @retry="load"
    />

    <div
      v-else-if="underReview"
      class="auth-status auth-status--success"
      role="status"
      data-testid="registration-under-review"
    >
      <AppIcon name="check-circle" class="auth-status__icon" />
      <p class="auth-status__title">{{ t('entities.registration.underReview.title') }}</p>
      <p class="auth-status__text">{{ t('auth.failures.signIn.pendingReview') }}</p>
      <router-link class="btn btn--secondary" :to="{ name: ROUTE_NAMES.PUBLIC_HOME }">
        {{ t('entities.registration.underReview.home') }}
      </router-link>
    </div>

    <EmptyState
      v-else-if="status === 'unavailable'"
      icon="clock"
      :title="t('entities.registration.unavailableTitle')"
      :message="t('entities.registration.unavailableMessage')"
    />

    <form v-else class="auth-form__body entity-form" novalidate @submit.prevent="submitStep">
      <WizardStepper
        :steps="wizard.steps.value"
        :current-index="wizard.currentIndex.value"
        @select="wizard.goTo"
      />

      <fieldset
        ref="stepPanel"
        class="entity-form__sections entity-register__step"
        tabindex="-1"
        :aria-label="wizard.currentStep.value.label"
        :disabled="form.submitting.value"
      >
        <RegistrationEntityStep
          v-if="wizard.currentStep.value.id === 'entity'"
          :region-options="regions.regionOptions.value"
          :city-options-for="regions.cityOptionsFor"
        />
        <RegistrationAccountStep v-else :national-id-required="nationalIdRequired" />
      </fieldset>

      <div class="auth-form__actions entity-register__actions">
        <BaseButton
          v-if="wizard.currentIndex.value > 0"
          variant="secondary"
          :disabled="form.submitting.value"
          @click="wizard.previous"
        >
          {{ t('entities.registration.back') }}
        </BaseButton>
        <BaseButton v-if="!wizard.isLast.value" type="submit" variant="primary">
          {{ t('entities.registration.next') }}
        </BaseButton>
        <BaseButton v-else type="submit" variant="primary" :loading="form.submitting.value">
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

<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAccountCompletionStore } from '@/stores/accountCompletion'

const { t } = useI18n()
const completion = useAccountCompletionStore()
const titleId = useId()

const message = computed(() => {
  if (completion.needsNationalId && completion.needsGender) return t('nationalIdentity.banner.both')
  return t(
    completion.needsNationalId
      ? 'nationalIdentity.banner.nationalId'
      : 'nationalIdentity.banner.gender',
  )
})
</script>

<template>
  <section
    v-if="completion.showsBanner"
    class="profile-banner account-completion-banner"
    :aria-labelledby="titleId"
    data-testid="account-completion-banner"
  >
    <div class="profile-banner__inner">
      <AppIcon name="warning-triangle" class="profile-banner__icon" />
      <p class="profile-banner__text">
        <span :id="titleId" class="profile-banner__title">{{
          t('nationalIdentity.banner.title')
        }}</span>
        <span>{{ message }}</span>
      </p>
      <button
        type="button"
        class="btn btn--primary btn--sm profile-banner__action"
        data-testid="account-completion-action"
        @click="completion.open()"
      >
        {{ t('nationalIdentity.banner.action') }}
      </button>
    </div>
  </section>
</template>

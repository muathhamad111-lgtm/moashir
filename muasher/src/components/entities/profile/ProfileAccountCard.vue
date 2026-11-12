<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePortalIdentity } from '@/composables/usePortalIdentity'

const { t } = useI18n()
const { userName, email } = usePortalIdentity()
</script>

<template>
  <section class="profile-account-card" data-testid="profile-account-card">
    <h2 class="profile-account-card__title">
      <AppIcon name="user" />
      <span>{{ t('account.identity.title') }}</span>
    </h2>
    <dl class="profile-account-card__facts">
      <div class="profile-account-card__fact">
        <dt>{{ t('account.identity.name') }}</dt>
        <dd>{{ userName }}</dd>
      </div>
      <div v-if="email" class="profile-account-card__fact">
        <dt>{{ t('account.identity.email') }}</dt>
        <dd>
          <bdi dir="ltr">{{ email }}</bdi>
        </dd>
      </div>
    </dl>
  </section>
</template>

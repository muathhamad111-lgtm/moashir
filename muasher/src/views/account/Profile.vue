<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useMyRoles } from '@/composables/useMyRoles'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import CommitteeSeatsList from '@/components/account/CommitteeSeatsList.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ProfilePhotoCard from '@/components/account/ProfilePhotoCard.vue'
import NationalIdentityCard from '@/components/account/NationalIdentityCard.vue'
import ChangePasswordCard from '@/components/account/ChangePasswordCard.vue'
import MyAgreementsCard from '@/components/account/MyAgreementsCard.vue'

const { t, locale } = useI18n()
const auth = useAuthStore()
const { roles, status, load, localized } = useMyRoles()

const displayName = computed(() => {
  const name = auth.user?.name
  if (!name) return t('nav.userFallback')
  return typeof name === 'object' ? (name[locale.value] ?? name.ar ?? name.en ?? '') : name
})

const email = computed(() => auth.user?.email ?? auth.user?.officialEmail ?? '')
const isEntityAccount = computed(() => auth.isEntity || Boolean(auth.user?.entityId))
const committeeSeats = computed(() => auth.committeeAppointments)
</script>

<template>
  <section class="account-page">
    <header class="page-header">
      <h1 class="page-header__title">{{ t('account.title') }}</h1>
      <p class="page-header__subtitle">{{ t('account.subtitle') }}</p>
    </header>

    <ProfilePhotoCard v-if="!isEntityAccount" />
    <p v-else class="account-page__note" data-testid="profile-photo-entity-hint">
      <AppIcon name="info-circle" />
      {{ t('account.avatar.entityHint') }}
    </p>

    <div class="account-page__grid">
      <Card :title="t('account.identity.title')" class="account-page__identity">
        <dl class="account-page__facts">
          <div class="account-page__fact">
            <dt>{{ t('account.identity.name') }}</dt>
            <dd>{{ displayName }}</dd>
          </div>
          <div v-if="email" class="account-page__fact">
            <dt>{{ t('account.identity.email') }}</dt>
            <dd class="u-ltr">{{ email }}</dd>
          </div>
        </dl>
        <p class="account-page__note">
          <AppIcon name="info-circle" />
          {{ t('account.workspace') }}
        </p>
      </Card>

      <Card :title="t('account.roles.title')" class="account-page__roles">
        <div v-if="status === 'loading'" class="account-page__loading">
          <Skeleton variant="text" :count="2" />
        </div>
        <ErrorState
          v-else-if="status === 'error'"
          :message="t('account.loadError')"
          @retry="load"
        />
        <ul v-else-if="roles.length" class="account-page__role-list">
          <li v-for="role in roles" :key="role.key">
            <Badge variant="info">{{ localized(role.label) }}</Badge>
          </li>
        </ul>
        <p v-else class="account-page__muted">{{ t('account.roles.empty') }}</p>
        <CommitteeSeatsList v-if="committeeSeats.length" :appointments="committeeSeats" />
      </Card>

      <NationalIdentityCard class="account-page__national-identity" />
    </div>

    <ChangePasswordCard />
    <MyAgreementsCard v-if="!isEntityAccount" />
  </section>
</template>

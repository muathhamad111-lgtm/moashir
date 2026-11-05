<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useMyPermissions } from '@/composables/useMyPermissions'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import PermissionModuleCard from '@/components/account/PermissionModuleCard.vue'

const { t, locale } = useI18n()
const auth = useAuthStore()
const { roles, modules, status, load, localized } = useMyPermissions()

const displayName = computed(() => {
  const name = auth.user?.name
  if (!name) return t('nav.userFallback')
  return typeof name === 'object' ? (name[locale.value] ?? name.ar ?? name.en ?? '') : name
})

const email = computed(() => auth.user?.email ?? auth.user?.officialEmail ?? '')
</script>

<template>
  <section class="account-page">
    <header class="page-header">
      <h1 class="page-header__title">{{ t('account.title') }}</h1>
      <p class="page-header__subtitle">{{ t('account.subtitle') }}</p>
    </header>

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
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
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
      </Card>
    </div>

    <section class="account-page__permissions" aria-labelledby="account-permissions-title">
      <header class="account-page__section-head">
        <h2 id="account-permissions-title" class="account-page__section-title">
          {{ t('account.permissions.title') }}
        </h2>
        <p class="account-page__muted">{{ t('account.permissions.subtitle') }}</p>
      </header>

      <div v-if="status === 'loading'" class="account-page__module-grid">
        <Skeleton v-for="i in 3" :key="i" variant="block" />
      </div>
      <ErrorState v-else-if="status === 'error'" :message="t('account.loadError')" @retry="load" />
      <EmptyState
        v-else-if="modules.length === 0"
        icon="fa-solid fa-shield-halved"
        :title="t('account.permissions.empty')"
      />
      <div v-else class="account-page__module-grid">
        <PermissionModuleCard
          v-for="group in modules"
          :key="group.module"
          :module="group.module"
          :items="group.items"
          :localized="localized"
        />
      </div>
    </section>
  </section>
</template>

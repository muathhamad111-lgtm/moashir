<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import IdentityChangeDialog from './IdentityChangeDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { useOwnIdentityChanges } from '@/composables/useOwnIdentityChanges'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const auth = useAuthStore()
const { dateTime } = useEntityDisplay()
const { pending, decided, load, submit, cancel } = useOwnIdentityChanges()
const dialogOpen = ref(false)

onMounted(load)
</script>

<template>
  <Card :title="t('nationalIdentity.card.title')" class="national-identity-card">
    <dl class="account-page__facts">
      <div class="account-page__fact">
        <dt>{{ t('nationalIdentity.field.label') }}</dt>
        <dd
          v-if="auth.user?.hasNationalId"
          class="u-ltr national-identity-card__value"
          data-testid="national-id-masked"
        >
          {{ auth.user.nationalIdMasked }}
        </dd>
        <dd v-else class="account-page__muted">{{ t('nationalIdentity.card.missing') }}</dd>
      </div>
    </dl>
    <p class="account-page__note">
      <AppIcon name="lock" />
      {{ t('nationalIdentity.card.locked') }}
    </p>

    <div v-if="pending" class="national-identity-card__pending" role="status">
      <AppIcon name="clock" />
      <span>{{ t('nationalIdentity.card.pending', { date: dateTime(pending.createdAt) }) }}</span>
      <BaseButton variant="secondary" size="sm" @click="cancel(pending)">
        {{ t('nationalIdentity.card.cancel') }}
      </BaseButton>
    </div>

    <div class="national-identity-card__actions">
      <BaseButton
        v-if="!auth.user?.hasNationalId"
        variant="primary"
        :to="{ name: ROUTE_NAMES.ACCOUNT_COMPLETE_IDENTITY, query: { redirect: '/account' } }"
      >
        {{ t('nationalIdentity.card.add') }}
      </BaseButton>
      <BaseButton v-else-if="!pending" variant="secondary" icon="pen" @click="dialogOpen = true">
        {{ t('nationalIdentity.card.requestChange') }}
      </BaseButton>
    </div>

    <section v-if="decided.length" class="national-identity-card__history">
      <h3 class="national-identity-card__history-title">
        {{ t('nationalIdentity.card.history') }}
      </h3>
      <ul class="national-identity-card__list">
        <li v-for="item in decided" :key="item.id" class="national-identity-card__item">
          <StatusBadge :status="item.status" />
          <span>{{ dateTime(item.decidedAt ?? item.createdAt) }}</span>
          <span v-if="item.reviewNote" class="account-page__muted">{{ item.reviewNote }}</span>
        </li>
      </ul>
    </section>

    <IdentityChangeDialog v-model="dialogOpen" :action="submit" />
  </Card>
</template>

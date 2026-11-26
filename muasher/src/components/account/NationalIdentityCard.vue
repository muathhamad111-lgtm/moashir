<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import IdentityChangeDialog from './IdentityChangeDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { useAccountCompletionStore } from '@/stores/accountCompletion'
import { useOwnIdentityChanges } from '@/composables/useOwnIdentityChanges'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { IDENTITY_CHANGE_KINDS, NATIONAL_ID_STATES } from '@/constants/identityChanges'

const { t } = useI18n()
const auth = useAuthStore()
const completion = useAccountCompletionStore()
const { dateTime } = useEntityDisplay()
const { pending, decided, load, submit, cancel } = useOwnIdentityChanges()
const dialogOpen = ref(false)

const awaitsApproval = computed(() => auth.nationalIdStatus === NATIONAL_ID_STATES.PENDING)
const pendingMessageKey = computed(() =>
  pending.value?.kind === IDENTITY_CHANGE_KINDS.FIRST_ENTRY
    ? 'nationalIdentity.card.pendingFirstEntry'
    : 'nationalIdentity.card.pending',
)

async function cancelPending() {
  const firstEntry = pending.value?.kind === IDENTITY_CHANGE_KINDS.FIRST_ENTRY
  await cancel(pending.value)
  if (firstEntry) auth.clearPendingNationalId()
}

onMounted(load)
watch(() => auth.nationalIdStatus, load)
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
        <dd
          v-else-if="awaitsApproval"
          class="account-page__muted"
          data-testid="national-id-pending"
        >
          {{ t('nationalIdentity.card.awaitingApproval') }}
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
      <span>{{ t(pendingMessageKey, { date: dateTime(pending.createdAt) }) }}</span>
      <BaseButton variant="secondary" size="sm" @click="cancelPending">
        {{ t('nationalIdentity.card.cancel') }}
      </BaseButton>
    </div>

    <div class="national-identity-card__actions">
      <BaseButton
        v-if="!auth.user?.hasNationalId && !pending"
        variant="primary"
        data-testid="national-id-add"
        @click="completion.open({ nationalId: true })"
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

<script setup>
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { IDENTITY_CHANGE_STATUSES } from '@/constants/identityChanges'

defineProps({
  modelValue: { type: Boolean, required: true },
  status: { type: String, required: true },
  change: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'approve', 'reject'])
const { t } = useI18n()
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('nationalIdentity.review.detailTitle')"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <Skeleton v-if="status === 'loading'" variant="text" :count="5" />
    <ErrorState v-else-if="status === 'error'" :message="t('nationalIdentity.review.loadFailed')" />
    <div v-else-if="change" class="identity-change-detail" data-testid="identity-change-detail">
      <dl class="identity-change-detail__facts">
        <div class="identity-change-detail__fact">
          <dt>{{ t('nationalIdentity.review.columns.user') }}</dt>
          <dd>{{ change.user?.name }}</dd>
        </div>
        <div v-if="change.onBehalf" class="identity-change-detail__fact">
          <dt>{{ t('nationalIdentity.review.submittedBy') }}</dt>
          <dd>{{ change.submittedBy?.name }}</dd>
        </div>
        <template v-if="change.requestedName">
          <div class="identity-change-detail__fact">
            <dt>{{ t('nationalIdentity.review.currentName') }}</dt>
            <dd>{{ change.currentName }}</dd>
          </div>
          <div class="identity-change-detail__fact">
            <dt>{{ t('nationalIdentity.review.requestedName') }}</dt>
            <dd>{{ change.requestedName }}</dd>
          </div>
        </template>
        <template v-if="change.requestedNationalIdMasked">
          <div class="identity-change-detail__fact">
            <dt>{{ t('nationalIdentity.review.currentNationalId') }}</dt>
            <dd class="u-ltr">{{ change.currentNationalIdMasked ?? '—' }}</dd>
          </div>
          <div class="identity-change-detail__fact">
            <dt>{{ t('nationalIdentity.review.requestedNationalId') }}</dt>
            <dd class="u-ltr" data-testid="requested-national-id">
              {{ change.requestedNationalId ?? change.requestedNationalIdMasked }}
            </dd>
          </div>
        </template>
        <div class="identity-change-detail__fact">
          <dt>{{ t('nationalIdentity.change.reason') }}</dt>
          <dd>{{ change.reason }}</dd>
        </div>
      </dl>
      <p class="identity-change-detail__note">{{ t('nationalIdentity.review.viewAudited') }}</p>
      <footer v-if="change.status === IDENTITY_CHANGE_STATUSES.PENDING" class="modal__footer">
        <BaseButton variant="primary" icon="check" @click="emit('approve', change)">
          {{ t('nationalIdentity.review.approve') }}
        </BaseButton>
        <BaseButton variant="danger" icon="x" @click="emit('reject', change)">
          {{ t('nationalIdentity.review.reject') }}
        </BaseButton>
      </footer>
    </div>
  </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ProfileChangeReviewRow from './ProfileChangeReviewRow.vue'
import { useProfileChangeDecision } from '@/composables/useProfileChangeDecision'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  requestId: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'decided'])
const { t, locale } = useI18n()
const request = ref(null)
const status = ref('idle')
const errorMessage = ref('')

const decision = useProfileChangeDecision(request, {
  onDecided(data) {
    request.value = data
    emit('decided', data)
  },
})

const entityName = computed(() => localizedText(request.value?.entity?.name, locale.value))

async function load() {
  if (!props.requestId) return
  status.value = 'loading'
  errorMessage.value = ''
  try {
    const { data } = await profileChangeRequestsService.show(props.requestId)
    request.value = data
    status.value = 'success'
  } catch (error) {
    errorMessage.value = error?.normalized?.message ?? ''
    status.value = 'error'
  }
}

watch(
  () => [props.modelValue, props.requestId],
  ([open]) => {
    if (open) load()
  },
  { immediate: true },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('entities.profileChanges.review.title')"
    :subtitle="
      request
        ? t('entities.profileChanges.review.subtitle', {
            name: entityName,
            revision: request.revision,
          })
        : ''
    "
    layout="drawer"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="change-review" data-testid="profile-change-review">
      <Skeleton v-if="status === 'loading'" variant="text" :count="5" />
      <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
      <template v-else-if="request">
        <div class="change-review__meta">
          <StatusBadge :status="request.status" />
          <BaseButton
            v-if="decision.isPending.value"
            variant="ghost"
            size="sm"
            @click="decision.setAll(true)"
          >
            {{ t('entities.profileChanges.review.approveAll') }}
          </BaseButton>
          <BaseButton
            v-if="decision.isPending.value"
            variant="ghost"
            size="sm"
            @click="decision.setAll(false)"
          >
            {{ t('entities.profileChanges.review.rejectAll') }}
          </BaseButton>
        </div>
        <p v-if="decision.isPending.value" class="change-review__hint">
          {{ t('entities.profileChanges.review.coupled') }}
        </p>
        <ul class="change-review__list">
          <ProfileChangeReviewRow
            v-for="row in decision.rows.value"
            :key="row.item"
            :row="row"
            :editable="decision.isPending.value"
            :approved="Boolean(decision.approved[row.item])"
            @update:approved="decision.setApproved(row.item, $event)"
          />
        </ul>
        <p v-if="request.reviewNote" class="change-review__note">
          {{ t('portal.profile.history.requestReason', { reason: request.reviewNote }) }}
        </p>
        <template v-if="decision.isPending.value">
          <p class="change-review__summary" aria-live="polite">
            {{
              t('entities.profileChanges.review.summary', {
                approved: decision.approvedItems.value.length,
                rejected: decision.rejectedCount.value,
              })
            }}
          </p>
          <BaseTextarea
            v-if="decision.needsReason.value"
            v-model="decision.reason.value"
            name="reason"
            required
            :label="t('entities.profileChanges.review.reason')"
            :hint="t('entities.profileChanges.review.reasonHint')"
            :error="decision.reasonError.value"
          />
        </template>
      </template>
    </div>
    <template v-if="request && decision.isPending.value" #footer>
      <BaseButton variant="ghost" @click="emit('update:modelValue', false)">
        {{ t('actions.cancel') }}
      </BaseButton>
      <BaseButton
        variant="primary"
        icon="check"
        :loading="decision.saving.value"
        @click="decision.submit"
      >
        {{ t('entities.profileChanges.review.submit') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>

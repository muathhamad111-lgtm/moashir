<script setup>
import { toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import OpenRequestsList from './OpenRequestsList.vue'
import { useEntityDeletion } from '@/composables/useEntityDeletion'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { REASON_LENGTH } from '@/constants/entityEnums'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  entity: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'deleted'])

const { t } = useI18n()
const { localized } = useEntityDisplay()
const { form, impactStatus, openRequests, stale, start, loadImpact } = useEntityDeletion({
  entity: toRef(props, 'entity'),
  onDeleted: () => {
    emit('update:modelValue', false)
    emit('deleted')
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) start()
  },
  { immediate: true },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="t('entities.delete.title', { name: localized(entity.name) })"
    :close-on-backdrop="!form.submitting.value"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <form class="entity-delete" novalidate @submit.prevent="form.onSubmit">
      <p class="entity-delete__intro">{{ t('entities.delete.intro') }}</p>

      <div v-if="impactStatus === 'loading'" class="entity-delete__loading" aria-busy="true">
        <Skeleton variant="text" :count="3" />
      </div>
      <ErrorState v-else-if="impactStatus === 'error'" :message="t('entities.delete.impactFailed')" @retry="loadImpact" />

      <template v-else-if="impactStatus === 'success'">
        <section v-if="openRequests.length" class="entity-delete__requests" aria-labelledby="entity-delete-requests">
          <p v-if="stale" class="entity-delete__stale" role="alert">{{ t('entities.delete.staleNotice') }}</p>
          <h3 id="entity-delete-requests" class="entity-delete__heading">
            {{ t('entities.delete.openRequestsTitle', { count: openRequests.length }) }}
          </h3>
          <p class="entity-delete__hint">{{ t('entities.delete.openRequestsHint') }}</p>
          <OpenRequestsList :requests="openRequests" />
          <FieldCheckbox name="confirmed" :label="t('entities.delete.confirmLabel', { count: openRequests.length })" />
        </section>
        <p v-else class="entity-delete__none">{{ t('entities.delete.noOpenRequests') }}</p>

        <FieldTextarea
          name="reason"
          :label="t('entities.reason.label')"
          :hint="t('entities.reason.hint', { min: REASON_LENGTH.MIN })"
          required
        />
      </template>

      <footer class="modal__footer">
        <BaseButton variant="secondary" :disabled="form.submitting.value" @click="emit('update:modelValue', false)">
          {{ t('actions.cancel') }}
        </BaseButton>
        <BaseButton
          type="submit"
          variant="danger"
          :loading="form.submitting.value"
          :disabled="impactStatus !== 'success'"
        >
          {{ t('entities.delete.submit') }}
        </BaseButton>
      </footer>
    </form>
  </BaseModal>
</template>

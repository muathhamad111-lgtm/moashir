<script setup>
import { toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { usePermanentDeletion } from '@/composables/usePermanentDeletion'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { ERASURE_KEPT_RECORDS, REASON_LENGTH } from '@/constants/entityEnums'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  entity: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'erased'])

const { t } = useI18n()
const { localized } = useEntityDisplay()
const { form, start } = usePermanentDeletion({
  entity: toRef(props, 'entity'),
  onErased: (outcome) => {
    emit('update:modelValue', false)
    emit('erased', outcome)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) start()
  },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="t('entities.permanent.title')"
    :close-on-backdrop="false"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <form class="permanent-delete" novalidate @submit.prevent="form.onSubmit">
      <div class="permanent-delete__warning" role="alert">
        <AppIcon name="warning-triangle" />
        <p class="permanent-delete__warning-text">
          {{
            t('entities.permanent.disclaimer', {
              name: localized(entity.name),
              reference: entity.reference,
            })
          }}
        </p>
      </div>
      <section class="permanent-delete__kept" aria-labelledby="permanent-delete-kept">
        <h3 id="permanent-delete-kept" class="permanent-delete__heading">
          {{ t('entities.permanent.keptTitle') }}
        </h3>
        <ul class="permanent-delete__list">
          <li v-for="record in ERASURE_KEPT_RECORDS" :key="record">
            {{ t(`entities.enums.kept.${record}`) }}
          </li>
        </ul>
      </section>
      <FieldCheckbox
        name="disclaimerAcknowledged"
        :label="t('entities.permanent.disclaimerLabel')"
      />
      <FieldInput
        name="confirmation"
        input-dir="ltr"
        autocomplete="off"
        :label="t('entities.permanent.confirmationLabel', { reference: entity.reference })"
        required
      />
      <FieldTextarea
        name="reason"
        :label="t('entities.reason.label')"
        :hint="t('entities.reason.hint', { min: REASON_LENGTH.PERMANENT_MIN })"
        required
      />
      <footer class="modal__footer">
        <BaseButton
          variant="secondary"
          :disabled="form.submitting.value"
          @click="emit('update:modelValue', false)"
        >
          {{ t('actions.cancel') }}
        </BaseButton>
        <BaseButton type="submit" variant="danger" icon="trash" :loading="form.submitting.value">
          {{ t('entities.permanent.submit') }}
        </BaseButton>
      </footer>
    </form>
  </BaseModal>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import NationalIdField from '@/components/identity/NationalIdField.vue'
import GenderField from '@/components/identity/GenderField.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { useAccountCompletionStore } from '@/stores/accountCompletion'
import { useAccountCompletionForm } from '@/composables/useAccountCompletionForm'

const { t } = useI18n()
const auth = useAuthStore()
const completion = useAccountCompletionStore()
const { form, asksNationalId, asksGender } = useAccountCompletionForm()

const approvalNote = computed(() =>
  t(
    auth.isSuperAdmin
      ? 'nationalIdentity.complete.savedDirectly'
      : 'nationalIdentity.complete.approval',
  ),
)

function toggle(open) {
  if (!open) completion.defer()
}
</script>

<template>
  <FormModal
    :model-value="completion.dialogOpen"
    :title="t('nationalIdentity.complete.title')"
    :subtitle="t('nationalIdentity.complete.subtitle')"
    :busy="form.submitting.value"
    :submit-label="t('nationalIdentity.complete.submit')"
    :cancel-label="t('nationalIdentity.complete.later')"
    @update:model-value="toggle"
    @submit="form.onSubmit"
  >
    <div class="account-completion" data-testid="account-completion">
      <template v-if="asksNationalId">
        <NationalIdField />
        <p class="account-completion__note">
          <AppIcon name="lock" />
          <span>{{ approvalNote }}</span>
        </p>
      </template>
      <GenderField v-if="asksGender" />
    </div>
  </FormModal>
</template>

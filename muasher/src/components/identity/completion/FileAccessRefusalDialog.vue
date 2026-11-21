<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAccountCompletionStore } from '@/stores/accountCompletion'
import { NATIONAL_ID_STATES } from '@/constants/identityChanges'

const { t } = useI18n()
const completion = useAccountCompletionStore()

// Never on top of the completion dialog, which already asks for the number.
const isOpen = computed(() => completion.refusedFileAccess !== null && !completion.dialogOpen)
const isPending = computed(() => completion.refusedFileAccess === NATIONAL_ID_STATES.PENDING)

function toggle(open) {
  if (!open) completion.dismissFileRefusal()
}
</script>

<template>
  <BaseModal
    :model-value="isOpen"
    :title="t('nationalIdentity.files.title')"
    size="sm"
    @update:model-value="toggle"
  >
    <p class="account-completion__note" data-testid="file-access-refusal">
      <AppIcon :name="isPending ? 'clock' : 'lock'" />
      <span>{{
        t(isPending ? 'nationalIdentity.files.pending' : 'nationalIdentity.files.missing')
      }}</span>
    </p>
    <footer class="modal__footer">
      <BaseButton
        v-if="!isPending"
        variant="primary"
        data-testid="file-access-enter-id"
        @click="completion.enterNationalIdForFiles()"
      >
        {{ t('nationalIdentity.files.enter') }}
      </BaseButton>
      <BaseButton variant="secondary" @click="completion.dismissFileRefusal()">
        {{ t('actions.close') }}
      </BaseButton>
    </footer>
  </BaseModal>
</template>

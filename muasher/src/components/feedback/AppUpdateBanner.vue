<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useAppUpdateCheck } from '@/composables/useAppUpdateCheck'

const { t } = useI18n()
const { updateAvailable } = useAppUpdateCheck()
const dismissed = ref(false)

function reload() {
  window.location.reload()
}
</script>

<template>
  <div class="app-update-region" role="status" aria-live="polite">
    <div v-if="updateAvailable && !dismissed" class="app-update" data-testid="app-update">
      <p class="app-update__message">{{ t('shell.update.message') }}</p>
      <div class="app-update__actions">
        <BaseButton variant="primary" size="sm" icon="refresh" @click="reload">
          {{ t('shell.update.reload') }}
        </BaseButton>
        <BaseButton variant="ghost" size="sm" @click="dismissed = true">
          {{ t('shell.update.later') }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>

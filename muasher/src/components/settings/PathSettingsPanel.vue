<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import PathLimitsFields from '@/components/settings/PathLimitsFields.vue'
import PathPriceFields from '@/components/settings/PathPriceFields.vue'
import PathEntityTextFields from '@/components/settings/PathEntityTextFields.vue'
import { usePathSettings } from '@/composables/usePathSettings'

const props = defineProps({
  path: { type: String, required: true },
})

const { t } = useI18n()
const {
  status,
  errorMessage,
  settings,
  saving,
  reasonOpen,
  load,
  submit,
  confirmReason,
  priceEditable,
} = usePathSettings(props.path)
const formId = `path-settings-${props.path}`

onMounted(load)
</script>

<template>
  <div class="path-settings">
    <div v-if="status === 'loading'" class="path-settings__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status !== 'success'" :message="errorMessage" @retry="load" />
    <form
      v-else-if="settings"
      :id="formId"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <p class="path-settings__intro">
        <PathBadge :path="path" :label="settings.pathLabel" />
        <span>{{ t('settings.paths.intro') }}</span>
      </p>
      <PathLimitsFields :settings="settings" />
      <PathPriceFields v-if="settings.isPaid" :settings="settings" :editable="priceEditable" />
      <PathEntityTextFields />
      <div class="path-settings__actions">
        <BaseButton type="submit" variant="primary" :loading="saving" icon="save">{{
          t('actions.save')
        }}</BaseButton>
      </div>
    </form>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('settings.assessment.reasonTitle')"
      :message="t('settings.assessment.reasonMessage')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      :action-name="`settings.assessment.paths.${path}.update`"
    />
  </div>
</template>

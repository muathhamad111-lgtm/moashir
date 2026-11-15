<script setup>
import { useI18n } from 'vue-i18n'
import { LOGO_SURFACES } from '@/constants/branding'
import { useBrandingEditor } from '@/composables/useBrandingEditor'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LogoVariantField from '@/components/branding/LogoVariantField.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AccentSwatchPicker from '@/components/branding/AccentSwatchPicker.vue'
import ContrastFailNotice from '@/components/branding/ContrastFailNotice.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const {
  palette,
  draftToken,
  rejectedPairs,
  reasonPrompt,
  canSave,
  busy,
  previewLogos,
  logos,
  status,
  saving,
  resetting,
  localized,
  load,
  save,
  reset,
} = useBrandingEditor()

async function onSave() {
  try {
    if (await save()) toast.success(t('branding.toast.saved'))
  } catch (error) {
    toast.error(error?.normalized?.message || t('branding.toast.saveError'))
  }
}

async function onReset() {
  const accepted = await confirm({
    title: t('branding.confirmReset.title'),
    message: t('branding.confirmReset.message'),
    confirmLabel: t('branding.confirmReset.confirm'),
    variant: 'warning',
  })
  if (!accepted) return
  try {
    if (await reset()) toast.success(t('branding.toast.reset'))
  } catch (error) {
    toast.error(error?.normalized?.message || t('branding.toast.saveError'))
  }
}
</script>

<template>
  <div class="branding-editor">
    <div v-if="status === 'loading'" class="branding-page__layout">
      <Skeleton variant="block" :count="3" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="t('branding.loadError')" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      icon="palette"
      :title="t('branding.emptyTitle')"
      :message="t('branding.emptyMessage')"
    />

    <div v-else class="branding-page__layout">
      <div class="branding-page__form">
        <Card :title="t('branding.accent.title')">
          <ContrastFailNotice :pairs="rejectedPairs" />
          <AccentSwatchPicker
            v-model="draftToken"
            :palette="palette"
            :localized="localized"
            :disabled="busy"
          />
        </Card>

        <Card :title="t('branding.logo.title')">
          <LogoVariantField
            v-for="surface in LOGO_SURFACES"
            :key="surface"
            :surface="surface"
            :preview-url="previewLogos[surface]"
            :file-name="logos[surface].file.value?.name ?? ''"
            :error-key="logos[surface].errorKey.value"
            :mismatch="logos[surface].mismatchedDimensions.value"
            :disabled="busy"
            @select="logos[surface].select"
            @remove="logos[surface].clear()"
          />
        </Card>

        <div class="branding-page__actions">
          <BaseButton
            variant="primary"
            icon="save"
            :loading="saving"
            :disabled="!canSave"
            @click="onSave"
          >
            {{ t('branding.actions.save') }}
          </BaseButton>
          <BaseButton
            variant="ghost"
            icon="undo"
            :loading="resetting"
            :disabled="busy"
            @click="onReset"
          >
            {{ t('branding.actions.reset') }}
          </BaseButton>
        </div>
      </div>
    </div>
    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('branding.reason.title')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="branding.superAdminReason"
    />
  </div>
</template>

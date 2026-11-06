<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { LOGO_SURFACES, SIDEBAR_LOGO_SURFACE } from '@/constants/branding'
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
import ContrastCheck from '@/components/branding/ContrastCheck.vue'
import ContrastFailNotice from '@/components/branding/ContrastFailNotice.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import BrandingPreview from '@/components/branding/BrandingPreview.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const {
  palette,
  draftToken,
  contrastPairs,
  rejectedPairs,
  reasonPrompt,
  canSave,
  busy,
  previewLogos,
  customLogos,
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
    variant: 'danger',
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
  <section class="branding-page">
    <header class="page-header">
      <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }" class="branding-page__back">
        <AppIcon name="arrow-right" />
        {{ t('settings.hub.backToHub') }}
      </RouterLink>
      <h1 class="page-header__title">{{ t('branding.title') }}</h1>
      <p class="page-header__subtitle">{{ t('branding.subtitle') }}</p>
      <p class="branding-page__lock">
        <AppIcon name="lock" />
        {{ t('branding.locked') }}
      </p>
    </header>

    <div v-if="status === 'loading'" class="branding-page__layout">
      <Skeleton variant="block" :count="3" />
      <Skeleton variant="block" />
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
          <p class="branding-page__hint">{{ t('branding.accent.hint') }}</p>
          <AccentSwatchPicker
            v-model="draftToken"
            :palette="palette"
            :localized="localized"
            :disabled="busy"
          />
        </Card>

        <Card :title="t('branding.contrast.title')">
          <ContrastFailNotice :pairs="rejectedPairs" />
          <ContrastCheck :pairs="contrastPairs" />
        </Card>

        <Card :title="t('branding.logo.title')">
          <p class="branding-page__hint">{{ t('branding.logo.hint') }}</p>
          <LogoVariantField
            v-for="surface in LOGO_SURFACES"
            :key="surface"
            :surface="surface"
            :preview-url="previewLogos[surface]"
            :file-name="logos[surface].file.value?.name ?? ''"
            :error-key="logos[surface].errorKey.value"
            :custom="customLogos[surface]"
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

      <aside class="branding-page__preview" :aria-label="t('branding.preview.title')">
        <h2 class="branding-page__preview-title">{{ t('branding.preview.title') }}</h2>
        <p class="branding-page__hint">{{ t('branding.preview.hint') }}</p>
        <BrandingPreview :accent-token="draftToken" :logo="previewLogos[SIDEBAR_LOGO_SURFACE]" />
      </aside>
    </div>
    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('branding.reason.title')"
      :message="t('branding.reason.message')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="branding.superAdminReason"
    />
  </section>
</template>

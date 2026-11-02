<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
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
import ContrastCheck from '@/components/branding/ContrastCheck.vue'
import BrandingPreview from '@/components/branding/BrandingPreview.vue'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const {
  palette,
  draftToken,
  contrastPairs,
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
    await save()
    toast.success(t('branding.toast.saved'))
  } catch {
    toast.error(t('branding.toast.saveError'))
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
    await reset()
    toast.success(t('branding.toast.reset'))
  } catch {
    toast.error(t('branding.toast.saveError'))
  }
}
</script>

<template>
  <section class="branding-page">
    <header class="page-header">
      <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }" class="branding-page__back">
        <i class="fa-solid fa-arrow-right-long u-flip-in-rtl" aria-hidden="true"></i>
        {{ t('settings.hub.backToHub') }}
      </RouterLink>
      <h1 class="page-header__title">{{ t('branding.title') }}</h1>
      <p class="page-header__subtitle">{{ t('branding.subtitle') }}</p>
      <p class="branding-page__lock">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
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
      icon="fa-solid fa-palette"
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
            icon="fa-solid fa-floppy-disk"
            :loading="saving"
            :disabled="!canSave"
            @click="onSave"
          >
            {{ t('branding.actions.save') }}
          </BaseButton>
          <BaseButton
            variant="ghost"
            icon="fa-solid fa-rotate-left"
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
        <BrandingPreview :accent-token="draftToken" :logos="previewLogos" />
      </aside>
    </div>
  </section>
</template>

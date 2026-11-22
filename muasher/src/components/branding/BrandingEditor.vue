<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { BRAND_COLOR_ROLES, DEFAULT_BRAND_COLORS, LOGO_SURFACES } from '@/constants/branding'
import { useBrandingEditor } from '@/composables/useBrandingEditor'
import { useAdoptedStyleSheet } from '@/composables/useAdoptedStyleSheet'
import { brandSchemeCss } from '@/composables/useBrandSchemeStyle'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LogoVariantField from '@/components/branding/LogoVariantField.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import BrandColorField from '@/components/branding/BrandColorField.vue'
import BrandDerivedColors from '@/components/branding/BrandDerivedColors.vue'
import BrandFixedColors from '@/components/branding/BrandFixedColors.vue'
import BrandPreview from '@/components/branding/BrandPreview.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const {
  draftColors,
  draftOverrides,
  draftScheme,
  autoColors,
  isDefault,
  setColor,
  overrideColor,
  restoreAutoColor,
  fillDefaultColors,
  reasonPrompt,
  canSave,
  busy,
  previewLogos,
  logos,
  status,
  saving,
  resetting,
  load,
  save,
  reset,
} = useBrandingEditor()

// The unsaved colours, as `--draft-*` on this editor only: its swatches and preview use them.
useAdoptedStyleSheet(
  computed(() =>
    brandSchemeCss(draftScheme.value, { selector: '.branding-editor', prefix: 'draft' }),
  ),
)

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

    <div v-else class="branding-page__layout">
      <div class="branding-page__form">
        <Card :title="t('branding.colors.title')">
          <p class="brand-colors__hint">{{ t('branding.colors.hint') }}</p>
          <div class="brand-colors__fields">
            <BrandColorField
              v-for="role in BRAND_COLOR_ROLES"
              :key="role"
              :role="role"
              :model-value="draftColors[role]"
              :fallback="DEFAULT_BRAND_COLORS[role]"
              :disabled="busy"
              @update:model-value="setColor(role, $event)"
            />
          </div>
          <BaseButton
            v-if="!isDefault"
            class="brand-colors__default"
            variant="ghost"
            size="sm"
            icon="undo"
            :disabled="busy"
            @click="fillDefaultColors"
          >
            {{ t('branding.colors.restoreDefault') }}
          </BaseButton>
        </Card>

        <BrandDerivedColors
          :scheme="draftScheme"
          :auto-colors="autoColors"
          :overrides="draftOverrides"
          :disabled="busy"
          @override="overrideColor"
          @restore-auto="restoreAutoColor"
        />

        <BrandFixedColors />

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
      <BrandPreview class="branding-page__preview" />
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

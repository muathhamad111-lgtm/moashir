<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import SocialLinksField from './SocialLinksField.vue'
import QuickLinksField from './QuickLinksField.vue'
import { useSiteSettingsEditor } from '@/composables/useSiteSettingsEditor'
import { useToast } from '@/composables/useToast'
import { SITE_SETTINGS_LIMITS } from '@/constants/cms'

const { t } = useI18n()
const toast = useToast()
const { status, draft, saving, load, save, errorOf } = useSiteSettingsEditor()

const limitOptions = computed(() =>
  Array.from(
    { length: SITE_SETTINGS_LIMITS.featuredMax - SITE_SETTINGS_LIMITS.featuredMin + 1 },
    (_, index) => {
      const value = SITE_SETTINGS_LIMITS.featuredMin + index
      return { value, label: String(value) }
    },
  ),
)

async function onSubmit() {
  try {
    await save()
    toast.success(t('settings.homepage.siteSettings.toasts.saved'))
  } catch (error) {
    toast.error(error?.normalized?.message || t('settings.homepage.siteSettings.toasts.saveFailed'))
  }
}
</script>

<template>
  <section class="site-settings" aria-labelledby="site-settings-heading">
    <header class="site-settings__header">
      <h2 id="site-settings-heading" class="site-settings__title">
        {{ t('settings.homepage.siteSettings.title') }}
      </h2>
      <p class="site-settings__desc">{{ t('settings.homepage.siteSettings.description') }}</p>
    </header>

    <div v-if="status === 'loading'" class="site-settings__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>

    <ErrorState
      v-else-if="status === 'error'"
      :message="t('settings.homepage.siteSettings.error')"
      @retry="load"
    />

    <form
      v-else
      class="site-settings__form"
      :aria-busy="saving || undefined"
      @submit.prevent="onSubmit"
    >
      <fieldset class="site-settings__group">
        <legend class="site-settings__legend">
          {{ t('settings.homepage.siteSettings.footerNote') }}
        </legend>
        <div class="site-settings__grid">
          <BaseTextarea
            v-model="draft.footerNote.ar"
            name="footerNoteAr"
            :rows="2"
            :label="t('settings.homepage.siteSettings.footerNoteAr')"
            :error="errorOf('footerNote.ar')"
          />
          <BaseTextarea
            v-model="draft.footerNote.en"
            name="footerNoteEn"
            :rows="2"
            :label="t('settings.homepage.siteSettings.footerNoteEn')"
            :error="errorOf('footerNote.en')"
          />
        </div>
      </fieldset>

      <fieldset class="site-settings__group">
        <legend class="site-settings__legend">
          {{ t('settings.homepage.siteSettings.contact') }}
        </legend>
        <div class="site-settings__grid">
          <BaseInput
            v-model="draft.contact.email"
            name="contactEmail"
            type="email"
            :label="t('settings.homepage.siteSettings.email')"
            :error="errorOf('contact.email')"
            input-dir="ltr"
          />
          <BaseInput
            v-model="draft.contact.phone"
            name="contactPhone"
            :label="t('settings.homepage.siteSettings.phone')"
            :error="errorOf('contact.phone')"
            input-dir="ltr"
          />
          <BaseInput
            v-model="draft.contact.address.ar"
            name="contactAddressAr"
            :label="t('settings.homepage.siteSettings.addressAr')"
            :error="errorOf('contact.address.ar')"
          />
          <BaseInput
            v-model="draft.contact.address.en"
            name="contactAddressEn"
            :label="t('settings.homepage.siteSettings.addressEn')"
            input-dir="ltr"
          />
        </div>
      </fieldset>

      <SocialLinksField v-model:rows="draft.social" :error-of="errorOf" :disabled="saving" />
      <QuickLinksField v-model:rows="draft.quickLinks" :error-of="errorOf" :disabled="saving" />

      <fieldset class="site-settings__group">
        <legend class="site-settings__legend">{{ t('settings.homepage.siteSettings.seo') }}</legend>
        <div class="site-settings__grid">
          <BaseInput
            v-model="draft.seo.title.ar"
            name="seoTitleAr"
            :label="t('settings.homepage.siteSettings.seoTitleAr')"
            :error="errorOf('seo.title.ar')"
          />
          <BaseInput
            v-model="draft.seo.title.en"
            name="seoTitleEn"
            :label="t('settings.homepage.siteSettings.seoTitleEn')"
            input-dir="ltr"
          />
          <BaseTextarea
            v-model="draft.seo.description.ar"
            name="seoDescriptionAr"
            :rows="2"
            :label="t('settings.homepage.siteSettings.seoDescriptionAr')"
            :error="errorOf('seo.description.ar')"
          />
          <BaseTextarea
            v-model="draft.seo.description.en"
            name="seoDescriptionEn"
            :rows="2"
            :label="t('settings.homepage.siteSettings.seoDescriptionEn')"
          />
        </div>
      </fieldset>

      <fieldset class="site-settings__group">
        <legend class="site-settings__legend">
          {{ t('settings.homepage.siteSettings.featured') }}
        </legend>
        <ToggleSwitch
          v-model="draft.showFeaturedKpis"
          name="showFeaturedKpis"
          :label="t('settings.homepage.siteSettings.showFeaturedKpis')"
          :description="t('settings.homepage.siteSettings.showFeaturedKpisHint')"
        />
        <AppSelect
          v-model="draft.featuredKpiLimit"
          name="featuredKpiLimit"
          :label="t('settings.homepage.siteSettings.featuredKpiLimit')"
          :hint="t('settings.homepage.siteSettings.featuredKpiLimitHint')"
          :options="limitOptions"
          :error="errorOf('featuredKpiLimit')"
        />
      </fieldset>

      <div class="site-settings__footer">
        <BaseButton type="submit" variant="primary" :loading="saving">
          {{ t('settings.homepage.siteSettings.save') }}
        </BaseButton>
      </div>
    </form>
  </section>
</template>

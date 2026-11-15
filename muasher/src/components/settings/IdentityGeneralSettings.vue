<script setup>
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import SiteSettingsForm from './SiteSettingsForm.vue'
import SocialLinksField from './SocialLinksField.vue'

const FIELDS = ['platformName', 'platformDescription', 'footerNote', 'contact', 'social', 'seo']

const { t } = useI18n()
</script>

<template>
  <SiteSettingsForm
    v-slot="{ draft, errorOf, saving }"
    heading-id="identity-general-heading"
    :title="t('identity.tabs.general')"
    :description="t('identity.general.description')"
    :fields="FIELDS"
  >
    <fieldset class="site-settings__group">
      <legend class="site-settings__legend">{{ t('identity.general.platform') }}</legend>
      <div class="site-settings__grid">
        <BaseInput
          v-model="draft.platformName.ar"
          name="platformNameAr"
          :label="t('identity.general.nameAr')"
          :error="errorOf('platformName.ar')"
        />
        <BaseInput
          v-model="draft.platformName.en"
          name="platformNameEn"
          :label="t('identity.general.nameEn')"
          :error="errorOf('platformName.en')"
          input-dir="ltr"
        />
        <BaseTextarea
          v-model="draft.platformDescription.ar"
          name="platformDescriptionAr"
          :rows="3"
          :label="t('identity.general.descriptionAr')"
          :error="errorOf('platformDescription.ar')"
        />
        <BaseTextarea
          v-model="draft.platformDescription.en"
          name="platformDescriptionEn"
          :rows="3"
          :label="t('identity.general.descriptionEn')"
          :error="errorOf('platformDescription.en')"
        />
      </div>
      <p class="site-settings__hint">{{ t('identity.general.fallbackHint') }}</p>
    </fieldset>

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
  </SiteSettingsForm>
</template>

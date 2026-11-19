<script setup>
import { useI18n } from 'vue-i18n'
import SiteSettingsForm from './SiteSettingsForm.vue'
import MenuEditor from './menus/MenuEditor.vue'
import { SITE_SETTINGS_LIMITS } from '@/constants/cms'

const FIELDS = ['headerLinks', 'quickLinks']

const { t } = useI18n()
</script>

<template>
  <SiteSettingsForm
    v-slot="{ draft, errorOf, saving }"
    heading-id="identity-menus-heading"
    :title="t('identity.tabs.menus')"
    :description="t('identity.menus.description')"
    :fields="FIELDS"
  >
    <MenuEditor
      v-model:rows="draft.headerLinks"
      field="headerLinks"
      :legend="t('identity.menus.header')"
      :hint="t('identity.menus.headerHint')"
      :max="SITE_SETTINGS_LIMITS.headerLinksMax"
      :max-children="SITE_SETTINGS_LIMITS.headerChildrenMax"
      :add-label="t('identity.menus.addHeaderLink')"
      :empty-text="t('identity.menus.headerEmpty')"
      :error-of="errorOf"
      :disabled="saving"
    />
    <MenuEditor
      v-model:rows="draft.quickLinks"
      field="quickLinks"
      :legend="t('identity.menus.footer')"
      :hint="t('identity.menus.footerHint')"
      :max="SITE_SETTINGS_LIMITS.quickLinksMax"
      :add-label="t('settings.homepage.siteSettings.addLink')"
      :empty-text="t('identity.menus.footerEmpty')"
      :error-of="errorOf"
      :disabled="saving"
    />
  </SiteSettingsForm>
</template>

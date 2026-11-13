<script setup>
import { onMounted, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import { useSettingsSection } from '@/composables/useSettingsSection'
import { settingsService } from '@/services/settingsService'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()

const form = reactive({
  platformName: { ar: '', en: '' },
  tagline: { ar: '', en: '' },
  content: { ar: '', en: '' },
  contactEmail: '',
  contactPhone: '',
  contactAddress: { ar: '', en: '' },
})

const { data, status, errorMessage, saving, load, save } = useSettingsSection({
  fetcher: () => settingsService.getGeneral(),
  saver: (payload) => settingsService.updateGeneral(payload),
})

watch(data, (value) => {
  if (!value) return
  form.platformName = { ...value.platformName }
  form.tagline = { ...value.tagline }
  form.content = { ...value.content }
  form.contactEmail = value.contactEmail
  form.contactPhone = value.contactPhone
  form.contactAddress = { ...value.contactAddress }
})

onMounted(load)

function onSave() {
  save({ ...form })
}
</script>

<template>
  <SettingsSection
    :title="t('settings.general.title')"
    :description="t('settings.general.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <div class="settings-form">
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.general.identity') }}</legend>
        <div class="settings-form__grid">
          <BaseInput
            v-model="form.platformName.ar"
            name="platformName-ar"
            :label="t('settings.general.platformNameAr')"
          />
          <BaseInput
            v-model="form.platformName.en"
            name="platformName-en"
            :label="t('settings.general.platformNameEn')"
          />
          <BaseInput
            v-model="form.tagline.ar"
            name="tagline-ar"
            :label="t('settings.general.taglineAr')"
          />
          <BaseInput
            v-model="form.tagline.en"
            name="tagline-en"
            :label="t('settings.general.taglineEn')"
          />
        </div>
        <BaseFileUpload
          name="logo"
          :label="t('settings.general.logo')"
          :hint="t('settings.general.logoHint')"
          accept="image/*"
        />
      </fieldset>

      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.general.branding') }}</legend>
        <p class="settings-form__note">
          <AppIcon name="palette" />
          {{ t('settings.general.brandingMoved') }}
          <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_BRANDING }">{{
            t('nav.branding')
          }}</RouterLink>
        </p>
      </fieldset>

      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.general.contentTitle') }}</legend>
        <BaseTextarea
          v-model="form.content.ar"
          name="content-ar"
          :rows="4"
          :label="t('settings.general.contentAr')"
        />
        <BaseTextarea
          v-model="form.content.en"
          name="content-en"
          :rows="4"
          :label="t('settings.general.contentEn')"
        />
      </fieldset>

      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.general.contact') }}</legend>
        <div class="settings-form__grid">
          <BaseInput
            v-model="form.contactEmail"
            name="contactEmail"
            type="email"
            :label="t('settings.general.contactEmail')"
          />
          <BaseInput
            v-model="form.contactPhone"
            name="contactPhone"
            :label="t('settings.general.contactPhone')"
          />
          <BaseInput
            v-model="form.contactAddress.ar"
            name="contactAddress-ar"
            :label="t('settings.general.contactAddressAr')"
          />
          <BaseInput
            v-model="form.contactAddress.en"
            name="contactAddress-en"
            :label="t('settings.general.contactAddressEn')"
          />
        </div>
      </fieldset>
    </div>

    <template #actions>
      <BaseButton variant="primary" :loading="saving" icon="save" @click="onSave">
        {{ t('actions.save') }}
      </BaseButton>
    </template>
  </SettingsSection>
</template>

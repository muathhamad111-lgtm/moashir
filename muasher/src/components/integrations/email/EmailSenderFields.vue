<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import { INTEGRATION_MODES } from '@/constants/integrations'

const { t } = useI18n()
const modeOptions = computed(() => Object.values(INTEGRATION_MODES).map((value) => ({ value, label: t(`integrations.mode.${value}`) })))
</script>

<template>
  <div class="email-sender">
    <div class="integration-form__grid">
      <FieldInput name="fromAddress" type="email" :label="t('integrations.email.fields.fromAddress')" input-dir="ltr" autocomplete="off" :hint="t('integrations.email.fields.fromAddressHint')" />
      <FieldInput name="replyTo" type="email" :label="t('integrations.email.fields.replyTo')" input-dir="ltr" autocomplete="off" />
      <FieldInput name="fromName.ar" :label="t('integrations.email.fields.fromNameAr')" input-dir="rtl" />
      <FieldInput name="fromName.en" :label="t('integrations.email.fields.fromNameEn')" input-dir="ltr" />
    </div>
    <FieldRadio name="mode" :label="t('integrations.email.fields.mode')" :options="modeOptions" />
    <p class="field__hint">{{ t('integrations.email.modeHint') }}</p>
    <FieldInput name="testAddress" type="email" :label="t('integrations.email.fields.testAddress')" input-dir="ltr" autocomplete="off" :hint="t('integrations.email.fields.testAddressHint')" />
    <FieldToggle name="active" :label="t('integrations.email.fields.active')" :description="t('integrations.email.fields.activeHint')" />
  </div>
</template>

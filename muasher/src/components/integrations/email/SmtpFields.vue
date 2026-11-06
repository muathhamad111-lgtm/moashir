<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldSecret from '@/components/form/FieldSecret.vue'
import { INTEGRATION_LIMITS, SMTP_ENCRYPTIONS, SMTP_PORTS } from '@/constants/integrations'

defineProps({
  passwordMeta: { type: Object, default: null },
  passwordHint: { type: String, default: '' },
})

const { t } = useI18n()
const portOptions = computed(() => SMTP_PORTS.map((port) => ({ value: port, label: String(port) })))
const encryptionOptions = computed(() =>
  SMTP_ENCRYPTIONS.map((value) => ({ value, label: t(`integrations.email.encryptions.${value}`) })),
)
</script>

<template>
  <div class="integration-form__grid">
    <FieldInput
      name="host"
      :label="t('integrations.email.fields.host')"
      input-dir="ltr"
      autocomplete="off"
      :hint="t('integrations.email.fields.hostHint')"
    />
    <FieldSelect name="port" :label="t('integrations.email.fields.port')" :options="portOptions" />
    <FieldSelect
      name="encryption"
      :label="t('integrations.email.fields.encryption')"
      :options="encryptionOptions"
      :hint="t('integrations.email.fields.encryptionHint')"
    />
    <FieldInput
      name="username"
      :label="t('integrations.email.fields.username')"
      input-dir="ltr"
      autocomplete="off"
    />
    <FieldSecret
      name="password"
      :meta="passwordMeta"
      :label="t('integrations.email.fields.password')"
      :hint="passwordHint"
    />
    <FieldInput
      name="timeoutSeconds"
      type="number"
      inputmode="numeric"
      :label="t('integrations.email.fields.timeout')"
      :hint="
        t('integrations.validation.integerBetween', {
          min: INTEGRATION_LIMITS.EMAIL_TIMEOUT.MIN,
          max: INTEGRATION_LIMITS.EMAIL_TIMEOUT.MAX,
        })
      "
    />
    <FieldInput
      name="ehloDomain"
      :label="t('integrations.email.fields.ehloDomain')"
      input-dir="ltr"
      autocomplete="off"
      :hint="t('integrations.email.fields.ehloHint')"
    />
  </div>
</template>

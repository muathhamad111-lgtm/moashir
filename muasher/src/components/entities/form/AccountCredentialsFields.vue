<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import { PASSWORD_LENGTH } from '@/constants/entityEnums'

const { t } = useI18n()
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.account') }}</legend>
    <FieldInput
      name="officialEmail"
      type="email"
      input-dir="ltr"
      autocomplete="email"
      :label="t('entities.fields.officialEmail')"
      :hint="t('entities.hints.officialEmail')"
      required
    />
    <div class="entity-form__grid">
      <FieldInput
        name="password"
        type="password"
        autocomplete="new-password"
        :label="t('entities.fields.password')"
        :hint="t('entities.hints.password', { min: PASSWORD_LENGTH.MIN })"
        required
      />
      <FieldInput
        name="passwordConfirmation"
        type="password"
        autocomplete="new-password"
        :label="t('entities.fields.passwordConfirmation')"
        required
      />
    </div>
  </fieldset>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'

defineProps({
  accountEmail: { type: String, default: '' },
})

const { t } = useI18n()
</script>

<template>
  <div class="profile-fields">
    <fieldset class="entity-form__group">
      <legend class="entity-form__legend">{{ t('portal.profile.sections.owner') }}</legend>
      <p class="entity-form__hint">{{ t('portal.profile.hints.owner') }}</p>
      <div class="entity-form__grid">
        <FieldInput
          name="authorizedPersonName"
          :label="t('entities.fields.authorizedPersonName')"
          autocomplete="name"
          required
        />
      </div>
      <dl class="profile-fields__readonly">
        <dt>{{ t('portal.profile.fields.accountEmail') }}</dt>
        <dd>
          <bdi dir="ltr">{{ accountEmail }}</bdi>
        </dd>
      </dl>
      <p class="entity-form__hint">{{ t('portal.profile.hints.accountEmail') }}</p>
    </fieldset>
  </div>
</template>

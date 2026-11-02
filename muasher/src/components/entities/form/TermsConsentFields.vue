<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Accordion from '@/components/ui/Accordion.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  terms: { type: Object, required: true },
})

const { t, locale } = useI18n()

const documents = computed(() =>
  ['terms', 'privacy']
    .filter((kind) => props.terms[kind])
    .map((kind) => ({
      id: kind,
      title: t(`entities.registration.documents.${kind}`, { version: props.terms[kind].version }),
      content: localizedText(props.terms[kind].body, locale.value),
    })),
)
</script>

<template>
  <fieldset class="entity-form__group">
    <legend class="entity-form__legend">{{ t('entities.sections.consent') }}</legend>
    <Accordion :items="documents">
      <template #item="{ item }">
        <p class="entity-form__terms">{{ item.content }}</p>
      </template>
    </Accordion>
    <FieldCheckbox name="termsAccepted" :label="t('entities.registration.termsAccepted')" />
    <FieldCheckbox name="marketingConsent" :label="t('entities.consent.marketing')" />
    <p class="entity-form__hint">{{ t('entities.hints.marketingConsent') }}</p>
  </fieldset>
</template>

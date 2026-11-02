<script setup>
import { computed } from 'vue'
import { useFieldArray } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import SmsTemplateField from './SmsTemplateField.vue'

const props = defineProps({
  definitions: { type: Array, required: true },
  approvedBodies: { type: Array, default: null },
  templateRestricted: { type: Boolean, default: false },
  templatesLoaded: { type: Boolean, default: false },
})

const { t } = useI18n()
const { fields } = useFieldArray('templates')
const definitionOf = (key) => props.definitions.find((definition) => definition.key === key)
const notice = computed(() => {
  if (!props.templateRestricted) return ''
  return t(props.templatesLoaded ? 'integrations.sms.templateCheckActive' : 'integrations.sms.templateCheckNeedsTemplates')
})
</script>

<template>
  <IntegrationCard :title="t('integrations.sms.templatesTitle')" :description="t('integrations.sms.templatesDesc')">
    <p v-if="notice" class="sms-templates__check" :class="{ 'is-pending': !templatesLoaded }" role="note">
      <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
      {{ notice }}
    </p>
    <div class="sms-templates">
      <SmsTemplateField
        v-for="(field, index) in fields"
        :key="field.value.key"
        :index="index"
        :definition="definitionOf(field.value.key)"
        :approved-bodies="approvedBodies"
      />
    </div>
  </IntegrationCard>
</template>

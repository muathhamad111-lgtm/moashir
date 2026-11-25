<script setup>
import { computed, toRef } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import { ENTITY_DOCUMENT_RULES } from '@/constants/entityDocuments'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
})

const { t } = useI18n()
const { value, errorMessage, handleChange } = useField(toRef(props, 'name'))
const files = computed(() =>
  value.value ? [{ id: value.value.name, name: value.value.name, status: 'done' }] : [],
)
</script>

<template>
  <BaseFileUpload
    class="registration-document"
    :files="files"
    :name="name"
    :label="label"
    :accept="ENTITY_DOCUMENT_RULES.accept"
    :hint="t('entities.documents.hint', { max: ENTITY_DOCUMENT_RULES.maxMb })"
    :error="errorMessage"
    required
    @select="handleChange($event[0])"
    @remove="handleChange(null)"
  />
</template>

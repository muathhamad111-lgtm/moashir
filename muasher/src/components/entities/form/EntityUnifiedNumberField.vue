<script setup>
import { useI18n } from 'vue-i18n'
import FieldDigits from '@/components/form/FieldDigits.vue'
import { UNIFIED_NUMBER_LENGTH } from '@/constants/entityEnums'

defineProps({
  required: { type: Boolean, default: true },
})

const { t } = useI18n()
</script>

<template>
  <FieldDigits
    name="unifiedNumber"
    :label="t('entities.fields.unifiedNumber')"
    :hint="t('entities.hints.unifiedNumber')"
    :maxlength="UNIFIED_NUMBER_LENGTH"
    :required="required"
  />
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldRadio from '@/components/form/FieldRadio.vue'
import { GENDER_VALUES } from '@/constants/identityChanges'

defineProps({
  name: { type: String, default: 'gender' },
})

const { t } = useI18n()
const options = computed(() =>
  GENDER_VALUES.map((value) => ({ value, label: t(`nationalIdentity.gender.${value}`) })),
)
</script>

<template>
  <FieldRadio
    :name="name"
    :label="t('nationalIdentity.gender.label')"
    :options="options"
    required
  />
</template>

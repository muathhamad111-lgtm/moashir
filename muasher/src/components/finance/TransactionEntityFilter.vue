<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { useEntityPicker } from '@/composables/useEntityPicker'
import { entitiesService } from '@/services/entitiesService'

defineProps({
  modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { options, loading, search } = useEntityPicker({
  lookup: (term, size) => entitiesService.list({ search: term, perPage: size }),
})

onMounted(() => search())
</script>

<template>
  <AppCombobox
    :model-value="modelValue || null"
    remote
    name="transactions-entity"
    :options="options"
    :loading="loading"
    :label="t('finance.filters.entity')"
    :placeholder="t('finance.filters.allEntities')"
    data-testid="transactions-entity-filter"
    @search="search"
    @update:model-value="emit('update:modelValue', $event ?? '')"
  />
</template>

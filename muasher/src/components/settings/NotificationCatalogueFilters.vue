<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DropdownList from '@/components/ui/DropdownList.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import {
  NOTIFICATION_AUDIENCES,
  NOTIFICATION_CATEGORIES,
  NOTIFICATION_MODULES,
} from '@/constants/notificationCatalogue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'reset'])
const { t } = useI18n()

function optionsOf(allKey, values, labelOf) {
  return [
    { value: '', label: t(allKey) },
    ...values.map((value) => ({ value, label: labelOf(value) })),
  ]
}

const moduleOptions = computed(() =>
  optionsOf('settings.notifications.catalogue.allModules', NOTIFICATION_MODULES, (value) =>
    t(`notifications.modules.${value}`),
  ),
)
const categoryOptions = computed(() =>
  optionsOf('settings.notifications.catalogue.allCategories', NOTIFICATION_CATEGORIES, (value) =>
    t(`settings.notifications.catalogue.categories.${value}`),
  ),
)
const audienceOptions = computed(() =>
  optionsOf('settings.notifications.catalogue.allAudiences', NOTIFICATION_AUDIENCES, (value) =>
    t(`settings.notifications.catalogue.audiences.${value}`),
  ),
)
const filtered = computed(() => Object.values(props.modelValue).some(Boolean))

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>

<template>
  <div
    class="notification-catalogue__filters"
    role="group"
    :aria-label="t('settings.notifications.catalogue.filtersLabel')"
  >
    <DropdownList
      :model-value="modelValue.module"
      :options="moduleOptions"
      @update:model-value="update('module', $event)"
    />
    <DropdownList
      :model-value="modelValue.category"
      :options="categoryOptions"
      @update:model-value="update('category', $event)"
    />
    <DropdownList
      :model-value="modelValue.audience"
      :options="audienceOptions"
      @update:model-value="update('audience', $event)"
    />
    <BaseButton v-if="filtered" variant="ghost" size="sm" icon="x" @click="emit('reset')">
      {{ t('settings.notifications.catalogue.reset') }}
    </BaseButton>
  </div>
</template>

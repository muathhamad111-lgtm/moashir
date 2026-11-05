<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { KPI_ADMIN_SORTS } from '@/constants/kpis'

const props = defineProps({
  search: { type: String, default: '' },
  values: { type: Object, required: true },
  sort: { type: String, default: '' },
  categories: { type: Array, default: () => [] },
  localized: { type: Function, required: true },
})

const emit = defineEmits(['update:search', 'filter', 'sort', 'create', 'reorder'])
const { t } = useI18n()

const flagOptions = (key) => [
  { value: '', label: t('kpis.admin.filters.all') },
  { value: 'true', label: t(`kpis.admin.filters.${key}.yes`) },
  { value: 'false', label: t(`kpis.admin.filters.${key}.no`) },
]
const categoryOptions = computed(() => [
  { value: '', label: t('kpis.admin.filters.allCategories') },
  ...props.categories.map((category) => ({
    value: category.id,
    label: props.localized(category.name),
  })),
])
const sortOptions = computed(() =>
  KPI_ADMIN_SORTS.map((entry) => ({
    value: entry.value,
    label: t(`kpis.admin.sort.${entry.labelKey}`),
  })),
)
</script>

<template>
  <ListToolbar
    :search="search"
    :search-placeholder="t('kpis.admin.index.searchPlaceholder')"
    @update:search="emit('update:search', $event)"
  >
    <template #filters>
      <DropdownList
        :model-value="values.published"
        :options="flagOptions('published')"
        :placeholder="t('kpis.admin.filters.published.label')"
        @update:model-value="emit('filter', 'published', $event)"
      />
      <DropdownList
        :model-value="values.featured"
        :options="flagOptions('featured')"
        :placeholder="t('kpis.admin.filters.featured.label')"
        @update:model-value="emit('filter', 'featured', $event)"
      />
      <DropdownList
        :model-value="values.categoryId"
        :options="categoryOptions"
        :placeholder="t('kpis.admin.filters.allCategories')"
        @update:model-value="emit('filter', 'categoryId', $event)"
      />
      <DropdownList
        :model-value="sort || 'order'"
        :options="sortOptions"
        :placeholder="t('kpis.admin.sort.label')"
        @update:model-value="emit('sort', $event)"
      />
    </template>
    <template #actions>
      <BaseButton variant="secondary" size="sm" icon="dots-vertical" @click="emit('reorder')">{{
        t('kpis.admin.reorder.open')
      }}</BaseButton>
      <BaseButton variant="primary" size="sm" icon="plus" @click="emit('create')">{{
        t('kpis.admin.index.add')
      }}</BaseButton>
    </template>
  </ListToolbar>
</template>

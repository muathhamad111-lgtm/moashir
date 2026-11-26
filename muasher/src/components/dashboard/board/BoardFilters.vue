<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import FilterDialog from '@/components/data-list/FilterDialog.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import OverviewPeriodPicker from '../overview/OverviewPeriodPicker.vue'
import { useRegions } from '@/composables/useRegions'
import { BOARD_FILTER_FIELDS } from '@/composables/useBoardFilters'
import { OVERVIEW_PATHS } from '@/constants/dashboardOverview'
import { DEVELOPMENT_SECTORS, ENTITY_TYPES, ORG_SIZES } from '@/constants/entityEnums'

// «فلترة»: the period and the board filters in one dialog. The period applies as it is chosen; the
// other filters apply together with «تطبيق».
const props = defineProps({
  state: { type: Object, required: true },
})

const { t } = useI18n()
const { regions, regionOptions, load: loadRegions } = useRegions()
const draft = reactive(Object.fromEntries(BOARD_FILTER_FIELDS.map((field) => [field, null])))
const filters = props.state.filters

const all = computed(() => ({ value: null, label: t('dataList.filter.all') }))
const enumOptions = (values, prefix) => [
  all.value,
  ...values.map((value) => ({ value, label: t(`${prefix}.${value}`) })),
]
const options = computed(() => ({
  region: [
    all.value,
    ...regionOptions.value.map((option, index) => ({
      ...option,
      value: regions.value[index].code,
    })),
  ],
  path: enumOptions(OVERVIEW_PATHS, 'dashboard.overview.pathShort'),
  type: enumOptions(ENTITY_TYPES, 'entities.enums.entityType'),
  sector: enumOptions(DEVELOPMENT_SECTORS, 'entities.enums.developmentSector'),
  size: enumOptions(ORG_SIZES, 'entities.enums.orgSize'),
}))

function load() {
  Object.assign(draft, filters.values.value)
}

function reset() {
  for (const field of BOARD_FILTER_FIELDS) draft[field] = null
}

onMounted(() => loadRegions())
</script>

<template>
  <FilterDialog
    class="board-filters__dialog"
    :active-count="filters.activeCount.value"
    @open="load"
    @apply="filters.apply({ ...draft })"
    @reset="reset"
    @clear="filters.clear"
  >
    <div class="board-filters">
      <fieldset class="board-filters__period">
        <legend class="board-filters__legend">{{ t('dashboard.board.filters.period') }}</legend>
        <OverviewPeriodPicker
          :period="state.period.value"
          :selection="state.selection.value"
          :from="state.customFrom.value"
          :to="state.customTo.value"
          :errors="state.fieldErrors.value"
          @preset="state.choosePreset"
          @calendar="state.chooseCalendar"
          @custom="state.applyCustom"
        />
      </fieldset>
      <AppSelect
        v-for="field in BOARD_FILTER_FIELDS"
        :key="field"
        :model-value="draft[field]"
        :options="options[field]"
        :label="t(`dashboard.board.filters.${field}`)"
        :name="`board-${field}`"
        @update:model-value="draft[field] = $event"
      />
    </div>
  </FilterDialog>
</template>

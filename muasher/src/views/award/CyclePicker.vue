<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import { useAwardCyclePicker } from '@/composables/useAwardCyclePicker'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const route = useRoute()
const picker = computed(() => route.meta.picker)
const { status, errorMessage, cycles, load } = useAwardCyclePicker(picker.value.key)
const text = (key) => t(`award.picker.${picker.value.key}.${key}`)

useHead({ title: () => text('title') })

const columns = computed(() => [
  { key: 'name', label: t('cycles.columns.name') },
  { key: 'status', label: t('cycles.columns.status') },
  { key: 'committeeEnd', label: t('award.picker.committeeEnd') },
  { key: 'open', label: t('award.picker.open') },
])
</script>

<template>
  <section class="cycles-page">
    <header class="cycles-page__header">
      <div>
        <p class="cycles-page__eyebrow">{{ t('advanced.eyebrow') }}</p>
        <h1 class="cycles-page__title">{{ text('title') }}</h1>
        <p class="cycles-page__subtitle">{{ text('subtitle') }}</p>
      </div>
    </header>
    <Card>
      <DataTable
        :columns="columns"
        :items="cycles"
        :status="status"
        :error-message="errorMessage"
        :empty-title="t('award.picker.emptyTitle')"
        :empty-message="t('award.picker.emptyMessage')"
        @retry="load"
      >
        <template #cell-name="{ item }"
          >{{ localizedText(item.name, locale) }} <bdi class="u-ltr">{{ item.year }}</bdi></template
        >
        <template #cell-status="{ item }"><CycleStatusBadge :status="item.status" /></template>
        <template #cell-committeeEnd="{ item }"
          ><DateStamp :value="item.committeeEnd" :with-time="false"
        /></template>
        <template #cell-open="{ item }">
          <RouterLink
            class="cycle-table__link"
            :to="{ name: picker.target, params: { id: item.id } }"
            >{{ text('open') }}</RouterLink
          >
        </template>
      </DataTable>
    </Card>
  </section>
</template>

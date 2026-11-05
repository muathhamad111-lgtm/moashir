<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import KpiCatalogFormDialog from './KpiCatalogFormDialog.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  kind: { type: String, required: true },
  catalog: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { t } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()
const formOpen = ref(false)
const editing = ref(null)

const columns = computed(() => [
  { key: 'name', label: t('kpis.admin.catalog.fields.name') },
  { key: 'key', label: t('kpis.admin.catalog.fields.key') },
  { key: 'order', label: t('kpis.admin.catalog.fields.order') },
  { key: 'active', label: t('kpis.admin.catalog.fields.active') },
  { key: 'actions', label: t('components.actions.label') },
])

function open(entry = null) {
  editing.value = entry
  formOpen.value = true
}

async function save(payload) {
  await props.catalog.save(editing.value, payload)
  toast.success(t('kpis.admin.catalog.saved'))
}

async function remove(entry) {
  const accepted = await confirm({
    title: t(`kpis.admin.catalog.${props.kind}.removeTitle`),
    message: t('kpis.admin.catalog.removeMessage', { name: props.localized(entry.name) }),
    confirmLabel: t('kpis.admin.catalog.remove'),
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await props.catalog.remove(entry)
    toast.success(t('kpis.admin.catalog.removed'))
  } catch (error) {
    const count = error?.normalized?.meta?.kpiCount
    toast.error(
      count
        ? t('kpis.admin.catalog.inUse', { count })
        : error?.normalized?.message || t('kpis.admin.toasts.failed'),
    )
  }
}
</script>

<template>
  <section class="kpi-catalog" :aria-label="t(`kpis.admin.tabs.${kind}`)">
    <div class="kpi-catalog__toolbar">
      <p class="kpi-catalog__hint">{{ t(`kpis.admin.catalog.${kind}.hint`) }}</p>
      <BaseButton variant="primary" size="sm" icon="plus" @click="open()">{{
        t(`kpis.admin.catalog.${kind}.add`)
      }}</BaseButton>
    </div>
    <DataTable
      :columns="columns"
      :items="catalog.entries.value"
      :status="catalog.status.value"
      :empty-title="t(`kpis.admin.catalog.${kind}.emptyTitle`)"
      :error-message="t('kpis.admin.catalog.loadError')"
      @retry="catalog.load"
    >
      <template #cell-name="{ item }">
        <span class="kpi-catalog__name">
          <AppIcon v-if="item.icon" :name="item.icon" />
          {{ localized(item.name) }}
        </span>
      </template>
      <template #cell-key="{ item }"
        ><span class="u-ltr">{{ item.key }}</span></template
      >
      <template #cell-active="{ item }"
        ><StatusBadge :status="item.active ? 'active' : 'inactive'"
      /></template>
      <template #cell-actions="{ item }">
        <div class="kpi-catalog__actions">
          <BaseButton variant="secondary" size="sm" icon="pen" @click="open(item)">{{
            t('actions.edit')
          }}</BaseButton>
          <BaseButton
            variant="ghost"
            size="sm"
            icon-only
            icon="trash"
            :aria-label="t('kpis.admin.catalog.removeNamed', { name: localized(item.name) })"
            @click="remove(item)"
          />
        </div>
      </template>
    </DataTable>
    <KpiCatalogFormDialog v-model="formOpen" :kind="kind" :entry="editing" :save="save" />
  </section>
</template>

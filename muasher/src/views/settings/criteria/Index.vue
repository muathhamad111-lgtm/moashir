<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import MainCriterionForm from '@/components/criteria/MainCriterionForm.vue'
import MainCriteriaTable from '@/components/criteria/MainCriteriaTable.vue'
import CriteriaVersionPanel from '@/components/criteria/CriteriaVersionPanel.vue'
import CriteriaTextCorrectionDialog from '@/components/criteria/CriteriaTextCorrectionDialog.vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { useCriteriaVersions } from '@/composables/useCriteriaVersions'
import { useCriteriaNodeActions } from '@/composables/useCriteriaNodeActions'
import { useDraftLifecycle } from '@/composables/useDraftLifecycle'
import { criteriaService } from '@/services/criteriaService'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CRITERIA_NODE_TYPES } from '@/constants/criteria'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { localized } = useCriteria()
const versions = useCriteriaVersions()
const lifecycle = useDraftLifecycle()

const selectedId = computed(() => (route.query.version ? Number(route.query.version) : null))
const listedVersion = ref(null)
const currentVersion = computed(
  () => versions.findVersion(listedVersion.value?.id) ?? listedVersion.value,
)
const access = useCriteriaAccess(currentVersion)

const filters = useFilters({ active: '' })
const { search } = filters
const { items, status, errorMessage, load } = useTable({
  fetcher: async (params) => {
    const envelope = await criteriaService.listMains({
      ...params,
      version: selectedId.value ?? undefined,
      perPage: 100,
    })
    listedVersion.value = envelope.meta?.version ?? null
    return envelope
  },
  filters,
})

async function reloadAll() {
  await Promise.all([versions.load(), load()])
}

onMounted(() => versions.load())
watch(selectedId, () => load())

const nodeActions = useCriteriaNodeActions(CRITERIA_NODE_TYPES.MAIN, reloadAll)
const reorderable = computed(
  () => access.canEditTree.value && !search.value && !filters.filters.active,
)

const versionOptions = computed(() =>
  versions.versions.value.map((version) => ({
    value: version.id,
    label: t('criteria.version.option', {
      number: version.versionNumber,
      status: t(statusLabelKey(version.status)),
    }),
  })),
)

const activeOptions = computed(() => [
  { value: '', label: t('criteria.filters.allStates') },
  { value: 'true', label: t('status.active') },
  { value: 'false', label: t('status.inactive') },
])

const formOpen = ref(false)
const editing = ref(null)
const correcting = ref(null)

function selectVersion(versionId) {
  router.replace({
    name: ROUTE_NAMES.ADMIN_CRITERIA,
    query: versionId ? { version: versionId } : {},
  })
}

function onVersionChanged({ versionId }) {
  if (versionId === selectedId.value) return reloadAll()
  selectVersion(versionId)
  return versions.load()
}

function openSubs(row) {
  router.push({ name: ROUTE_NAMES.ADMIN_CRITERIA_MAIN, params: { mainId: row.id } })
}

function openForm(row = null) {
  editing.value = row
  formOpen.value = true
}

async function startFirstDraft() {
  const draft = await lifecycle.createDraft({ fromPublished: false })
  if (draft) onVersionChanged({ versionId: draft.id })
}

const saveMain = ({ id, payload }) => nodeActions.save(id, payload)

function onAction(key, row) {
  const handlers = {
    open: () => openSubs(row),
    edit: () => openForm(row),
    toggle: () => nodeActions.toggle(row),
    delete: () => nodeActions.remove(row, localized(row.name)),
    correct: () => (correcting.value = { type: CRITERIA_NODE_TYPES.MAIN, node: row }),
  }
  handlers[key]?.()
}
</script>

<template>
  <section class="criteria-page">
    <header class="criteria-page__header">
      <div>
        <h1 class="criteria-page__title">{{ t('criteria.index.title') }}</h1>
        <p class="criteria-page__subtitle">{{ t('criteria.index.subtitle') }}</p>
      </div>
      <div v-if="versionOptions.length > 1" class="criteria-page__version-picker">
        <span class="criteria-page__picker-label">{{ t('criteria.version.picker') }}</span>
        <DropdownList
          :model-value="currentVersion?.id ?? null"
          :options="versionOptions"
          :placeholder="t('criteria.version.picker')"
          @update:model-value="selectVersion"
        />
      </div>
    </header>

    <CriteriaVersionPanel
      v-if="currentVersion"
      :version="currentVersion"
      :draft="versions.draft.value"
      :published="versions.published.value"
      @changed="onVersionChanged"
    />

    <EmptyState
      v-if="versions.status.value === 'empty' && status !== 'loading'"
      :title="t('criteria.index.noVersionsTitle')"
      :message="t('criteria.index.noVersionsMessage')"
      icon="hierarchy"
    >
      <template v-if="access.canManage.value" #action>
        <BaseButton variant="primary" icon="plus" @click="startFirstDraft">{{
          t('criteria.version.createEmptyDraft')
        }}</BaseButton>
      </template>
    </EmptyState>

    <template v-else>
      <ListToolbar
        v-model:search="search"
        :search-placeholder="t('criteria.index.searchPlaceholder')"
      >
        <template #filters>
          <DropdownList
            :model-value="filters.filters.active"
            :options="activeOptions"
            :placeholder="t('criteria.filters.state')"
            @update:model-value="filters.setFilter('active', $event)"
          />
        </template>
        <template v-if="access.canEditTree.value" #actions>
          <BaseButton variant="primary" size="sm" icon="plus" @click="openForm()">{{
            t('criteria.index.add')
          }}</BaseButton>
        </template>
      </ListToolbar>

      <MainCriteriaTable
        :items="items"
        :status="status"
        :error-message="errorMessage"
        :access="access"
        :reorderable="reorderable"
        @action="onAction"
        @move="(index, delta) => nodeActions.move(items, index, delta)"
        @retry="load"
        @add="openForm()"
      />
    </template>

    <MainCriterionForm v-model="formOpen" :criterion="editing" :save="saveMain" />
    <CriteriaTextCorrectionDialog
      v-if="currentVersion && access.canCorrectText.value"
      :model-value="Boolean(correcting)"
      :version="currentVersion"
      :target="correcting"
      @update:model-value="correcting = $event ? correcting : null"
      @corrected="nodeActions.corrected"
    />
  </section>
</template>

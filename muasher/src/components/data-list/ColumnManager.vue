<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import SortableList from '@/components/ui/SortableList.vue'
import ColumnManagerLocked from './ColumnManagerLocked.vue'
import ColumnManagerTrigger from './ColumnManagerTrigger.vue'
import { MIN_VISIBLE_COLUMNS } from '@/constants/tableColumns'
import { useColumnDraft } from '@/composables/useColumnDraft'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { toast } from '@/composables/useToast'

// «إدارة الأعمدة»: the viewer shows, hides and orders a list's optional columns. «تطبيق» changes this
// page's view only; «حفظ كافتراضي» also keeps the choice on the account for every visit; «إغلاق»
// drops the changes. Drag is the pointer shortcut; the up/down buttons reorder by keyboard.
const props = defineProps({
  choice: { type: Object, required: true },
})

const { t } = useI18n()
const isOpen = ref(false)
const isPhone = useCompactViewport()
const searchId = useId()
const { query, filtered, shownCount, atMinimum, allShown, ...draft } = useColumnDraft(props.choice)

function open() {
  draft.load()
  isOpen.value = true
}

function applyToView() {
  props.choice.applyToView(draft.result())
  isOpen.value = false
}

function saveAsDefault() {
  props.choice.saveAsDefault(draft.result())
  isOpen.value = false
  toast.success(t('components.columns.saved'))
}
</script>

<template>
  <div class="column-manager">
    <ColumnManagerTrigger :unsaved="choice.differsFromSaved.value" @open="open" />

    <BaseModal
      v-model="isOpen"
      :title="t('components.columns.title')"
      :subtitle="t('components.columns.help', { count: MIN_VISIBLE_COLUMNS })"
      size="md"
      :layout="isPhone ? 'sheet' : 'dialog'"
    >
      <div class="column-manager__tools">
        <div class="dropdown-sheet__search column-manager__search">
          <label class="column-manager__search-label" :for="searchId">{{
            t('components.columns.search')
          }}</label>
          <AppIcon name="search" class="dropdown-sheet__search-icon" />
          <input
            :id="searchId"
            v-model="query"
            class="dropdown-sheet__search-input"
            type="search"
            name="columnSearch"
            autocomplete="off"
            :placeholder="t('components.columns.search')"
          />
        </div>
        <BaseButton variant="ghost" size="sm" icon="refresh" @click="draft.resetToDefaults">
          {{ t('components.columns.reset') }}
        </BaseButton>
      </div>

      <p class="column-manager__hint column-manager__reset-hint">
        {{ t('components.columns.resetHint') }}
      </p>
      <div class="column-manager__all">
        <BaseCheckbox
          name="columns-all"
          :label="t('components.columns.selectAll', { count: shownCount })"
          :model-value="allShown"
          @update:model-value="draft.setAll"
        />
        <p v-if="atMinimum" class="column-manager__minimum" role="status">
          {{ t('components.columns.minimum', { count: MIN_VISIBLE_COLUMNS }) }}
        </p>
      </div>

      <ColumnManagerLocked :columns="choice.leading.value" />
      <SortableList
        :items="filtered"
        item-key="key"
        :label-of="(row) => row.label"
        :disabled="Boolean(query)"
        :list-label="t('components.columns.listLabel')"
        @reorder="draft.reorder"
      >
        <template #item="{ item }">
          <BaseCheckbox
            :name="`column-${item.key}`"
            :label="item.label"
            :model-value="item.shown"
            :disabled="!draft.canHide(item)"
            @update:model-value="draft.setShown(item.key, $event)"
          />
        </template>
      </SortableList>
      <p v-if="!filtered.length" class="column-manager__none">
        {{ t('components.columns.noMatch') }}
      </p>
      <p v-else-if="query" class="column-manager__hint">
        {{ t('components.columns.searchHint') }}
      </p>
      <ColumnManagerLocked :columns="choice.trailing.value" />

      <template #footer>
        <BaseButton variant="secondary" @click="isOpen = false">
          {{ t('components.columns.close') }}
        </BaseButton>
        <BaseButton variant="secondary" @click="applyToView">
          {{ t('components.columns.apply') }}
        </BaseButton>
        <BaseButton variant="primary" icon="check" @click="saveAsDefault">
          {{ t('components.columns.saveDefault') }}
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

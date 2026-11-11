<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import ProfileHistoryEntry from './ProfileHistoryEntry.vue'
import { useProfileChanges } from '@/composables/useProfileChanges'
import { profileChangesService } from '@/services/profileChangesService'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  section: { type: String, default: '' },
  sections: { type: Array, required: true },
  entityUserName: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const filter = ref(props.section)
const { changes, status, errorMessage, pagination, goTo } = useProfileChanges((params) =>
  profileChangesService.listMine(filter.value ? { ...params, section: filter.value } : params),
)

function choose(value) {
  if (filter.value === value) return
  filter.value = value
  goTo(1)
}

watch(
  () => [props.modelValue, props.section],
  ([open]) => {
    if (!open) return
    filter.value = props.section
    goTo(1)
  },
  { immediate: true },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('portal.profile.history.title')"
    layout="drawer"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="history-drawer" data-testid="profile-history-drawer">
      <div
        class="history-drawer__filters"
        role="group"
        :aria-label="t('portal.profile.history.filterLabel')"
      >
        <button
          v-for="key in [...sections, '']"
          :key="key || 'all'"
          type="button"
          class="history-drawer__chip"
          :aria-pressed="filter === key"
          @click="choose(key)"
        >
          {{ key ? t(`portal.profile.sections.${key}`) : t('portal.profile.history.allSections') }}
        </button>
      </div>
      <Skeleton v-if="status === 'loading'" variant="text" :count="4" />
      <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="goTo(1)" />
      <p v-else-if="status !== 'success'" class="history-drawer__empty" role="status">
        {{ t('portal.profile.history.empty') }}
      </p>
      <template v-else>
        <ProfileHistoryEntry
          v-for="change in changes"
          :key="change.id"
          :change="change"
          :entity-user-name="entityUserName"
        />
        <Pagination
          v-if="pagination.lastPage > 1"
          :page="pagination.page"
          :last-page="pagination.lastPage"
          @update:page="goTo"
        />
      </template>
    </div>
  </BaseModal>
</template>

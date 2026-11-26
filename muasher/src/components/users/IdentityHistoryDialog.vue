<script setup>
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import { useEntityDisplay } from '@/composables/useEntityDisplay'

defineProps({
  modelValue: { type: Boolean, required: true },
  user: { type: Object, default: null },
  status: { type: String, required: true },
  items: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'retry'])
const { t } = useI18n()
const { dateTime } = useEntityDisplay()
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('nationalIdentity.history.title')"
    :subtitle="user?.name ?? ''"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <Skeleton v-if="status === 'loading'" variant="text" :count="4" />
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('nationalIdentity.history.loadFailed')"
      @retry="emit('retry')"
    />
    <EmptyState
      v-else-if="items.length === 0"
      icon="doc"
      :title="t('nationalIdentity.history.empty')"
    />
    <ol v-else class="national-identity-card__list" data-testid="identity-history">
      <li v-for="item in items" :key="item.id" class="identity-change-detail__fact">
        <strong>{{ t(`nationalIdentity.fields.${item.field}`) }}</strong>
        <span>{{
          t('nationalIdentity.history.change', { from: item.oldValue ?? '—', to: item.newValue })
        }}</span>
        <span class="identity-change-detail__note">
          {{
            t('nationalIdentity.history.meta', {
              date: dateTime(item.createdAt),
              approver: item.approvedBy?.name ?? '—',
            })
          }}
        </span>
        <span class="identity-change-detail__note" data-testid="identity-history-source">{{
          t(`nationalIdentity.history.sources.${item.source}`)
        }}</span>
        <span v-if="item.reason" class="identity-change-detail__note">{{ item.reason }}</span>
      </li>
    </ol>
  </BaseModal>
</template>

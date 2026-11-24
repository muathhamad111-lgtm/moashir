<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  activeCount: { type: Number, default: 0 },
})

const emit = defineEmits(['open', 'apply', 'reset', 'clear'])
const { t } = useI18n()
const isOpen = ref(false)

const buttonLabel = computed(() =>
  props.activeCount
    ? t('dataList.filter.buttonActive', { count: props.activeCount })
    : t('dataList.filter.button'),
)

function open() {
  emit('open')
  isOpen.value = true
}

function apply() {
  emit('apply')
  isOpen.value = false
}
</script>

<template>
  <div class="filter-dialog">
    <button
      type="button"
      class="btn btn--secondary btn--sm filter-dialog__toggle"
      :class="{ 'is-active': activeCount > 0 }"
      :aria-label="buttonLabel"
      aria-haspopup="dialog"
      @click="open"
    >
      <AppIcon name="sliders" />
      <span>{{ t('dataList.filter.button') }}</span>
      <span v-if="activeCount" class="filter-dialog__count" aria-hidden="true">{{
        activeCount
      }}</span>
    </button>
    <BaseButton v-if="activeCount" variant="ghost" size="sm" icon="x" @click="emit('clear')">
      {{ t('dataList.filter.clear') }}
    </BaseButton>

    <BaseModal v-model="isOpen" :title="t('dataList.filter.title')" size="md" layout="drawer">
      <div class="filter-dialog__fields">
        <slot />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="emit('reset')">
          {{ t('dataList.filter.reset') }}
        </BaseButton>
        <BaseButton variant="secondary" @click="isOpen = false">
          {{ t('actions.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" @click="apply">
          {{ t('dataList.filter.apply') }}
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

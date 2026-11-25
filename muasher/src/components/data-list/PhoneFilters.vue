<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompactViewport } from '@/composables/useCompactViewport'
import BaseModal from '@/components/modal/BaseModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

// A page's own filter form (one not built on ListToolbar/FilterDialog). From md up it stays where it
// is. On a phone it sits behind one «فلترة» button showing how many filters are set, which opens the
// same form in a bottom sheet; submitting the form applies it and closes the sheet.
const props = defineProps({
  activeCount: { type: Number, default: 0 },
})

const { t } = useI18n()
const isPhone = useCompactViewport()
const isOpen = ref(false)

const buttonLabel = computed(() =>
  props.activeCount
    ? t('dataList.filter.buttonActive', { count: props.activeCount })
    : t('dataList.filter.button'),
)
</script>

<template>
  <slot v-if="!isPhone" />
  <div v-else class="filter-dialog">
    <button
      type="button"
      class="btn btn--secondary btn--sm filter-dialog__toggle"
      :class="{ 'is-active': activeCount > 0 }"
      :aria-label="buttonLabel"
      aria-haspopup="dialog"
      data-testid="phone-filters-toggle"
      @click="isOpen = true"
    >
      <AppIcon name="sliders" />
      <span>{{ t('dataList.filter.button') }}</span>
      <span v-if="activeCount" class="filter-dialog__count" aria-hidden="true">{{
        activeCount
      }}</span>
    </button>
    <BaseModal v-model="isOpen" :title="t('dataList.filter.title')" layout="sheet">
      <div class="filter-dialog__fields" @submit="isOpen = false">
        <slot />
      </div>
    </BaseModal>
  </div>
</template>

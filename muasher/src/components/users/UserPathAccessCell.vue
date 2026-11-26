<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { pathAccessSummary } from '@/utils/users/pathAccessSummary'

// The paths a user works on, as one short line («الكل»، «المعتمد، المتقدم»، «غير محدد»).
const props = defineProps({
  user: { type: Object, required: true },
})

const { t } = useI18n()

const text = computed(() =>
  (pathAccessSummary(props.user) ?? [])
    .map((key) => t(`users.pathAccess.short.${key}`))
    .join(t('common.listSeparator')),
)
</script>

<template>
  <span v-if="text" class="path-access-cell">{{ text }}</span>
</template>

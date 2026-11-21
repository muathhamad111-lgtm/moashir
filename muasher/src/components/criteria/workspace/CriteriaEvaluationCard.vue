<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { EVALUATION_TYPES } from '@/constants/criteria'

const props = defineProps({
  sub: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['switch'])
const { t } = useI18n()

const OPTIONS = [
  { type: EVALUATION_TYPES.DIRECT, key: 'direct', hint: 'directHint' },
  { type: EVALUATION_TYPES.GUIDING_POINTS, key: 'guidingPoints', hint: 'guidingHint' },
]

const currentKey = computed(
  () => OPTIONS.find((option) => option.type === props.sub.evaluationType)?.key,
)
const switchLabel = computed(() =>
  currentKey.value === 'direct'
    ? t('criteria.evaluationType.switchToPoints')
    : t('criteria.evaluationType.switchToDirect'),
)
</script>

<template>
  <Card :title="t('criteria.workspace.details.evaluationTitle')">
    <div class="criteria-evaluation__options">
      <div
        v-for="option in OPTIONS"
        :key="option.key"
        class="criteria-evaluation__option"
        :class="{ 'is-current': option.key === currentKey }"
      >
        <p class="criteria-evaluation__option-title">
          {{ t(`criteria.evaluationType.${option.key}`) }}
        </p>
        <p class="criteria-evaluation__option-text">
          {{ t(`criteria.evaluationType.${option.hint}`) }}
        </p>
      </div>
    </div>
    <template v-if="canEdit" #footer>
      <BaseButton variant="secondary" @click="emit('switch')">{{ switchLabel }}</BaseButton>
    </template>
  </Card>
</template>

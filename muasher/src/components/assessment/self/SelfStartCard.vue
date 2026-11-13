<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { scorableUnits } from '@/utils/assessments/scoreSheet'
import { formatDate } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  version: { type: Object, required: true },
  starting: { type: Boolean, default: false },
})

const emit = defineEmits(['start'])
const { t, locale } = useI18n()

const itemsCount = computed(() => scorableUnits(props.version).length)
const publishedOn = computed(() =>
  props.version.publishedAt
    ? formatDate(props.version.publishedAt, locale.value, {
        dateStyle: 'medium',
        timeZone: 'Asia/Riyadh',
      })
    : '',
)
</script>

<template>
  <Card class="self-start">
    <div class="self-start__body">
      <AppIcon name="list-check" class="self-start__icon" />
      <div class="self-start__text">
        <h2 class="self-start__title">{{ t('assessment.self.start.title') }}</h2>
        <p class="self-start__message">{{ t('assessment.self.start.message') }}</p>
        <ul class="self-start__facts">
          <li>
            {{
              t('assessment.self.start.version', {
                number: version.versionNumber,
                date: publishedOn,
              })
            }}
          </li>
          <li>{{ t(`assessment.self.scaleNames.${version.scaleKind}`) }}</li>
          <li>{{ t('assessment.self.start.items', { count: itemsCount }, itemsCount) }}</li>
        </ul>
      </div>
    </div>
    <template #footer>
      <div class="self-start__actions">
        <BaseButton variant="primary" icon="play" :loading="starting" @click="emit('start')">
          {{ t('assessment.self.start.action') }}
        </BaseButton>
      </div>
    </template>
  </Card>
</template>

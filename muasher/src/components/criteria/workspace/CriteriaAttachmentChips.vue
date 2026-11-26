<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Badge from '@/components/ui/Badge.vue'
import { useCriteria } from '@/composables/useCriteria'

defineProps({
  attachments: { type: Array, required: true },
  label: { type: String, required: true },
})

const { t } = useI18n()
const { localized } = useCriteria()
</script>

<template>
  <div class="criteria-chips">
    <span class="criteria-card__label">{{ label }}</span>
    <ul class="criteria-chips__list">
      <li
        v-for="attachment in attachments"
        :key="attachment.key ?? localized(attachment.name)"
        class="criteria-chips__chip"
      >
        <AppIcon name="attachment" />
        <span>{{ localized(attachment.name) }}</span>
        <Badge :variant="attachment.required ? 'warning' : 'muted'">{{
          attachment.required
            ? t('criteria.attachments.required')
            : t('criteria.workspace.board.optional')
        }}</Badge>
        <span v-if="attachment.allowedTypes?.length" class="criteria-chips__meta" dir="ltr">{{
          attachment.allowedTypes.join(' · ')
        }}</span>
        <span v-if="attachment.maxFiles" class="criteria-chips__meta">{{
          t(
            'criteria.workspace.board.maxFiles',
            { count: attachment.maxFiles },
            attachment.maxFiles,
          )
        }}</span>
      </li>
    </ul>
  </div>
</template>

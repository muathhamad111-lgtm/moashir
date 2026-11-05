<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import RecordedStamp from '@/components/integrations/RecordedStamp.vue'
import { PROVIDER_ACTION_STATES } from '@/composables/useProviderAction'

defineProps({
  action: { type: Object, required: true },
  items: { type: Array, required: true },
  loaded: { type: Boolean, default: false },
  loadedAt: { type: String, default: null },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <section class="msegat-templates" :aria-label="t('integrations.sms.msegat.approvedTitle')">
    <div class="msegat-templates__head">
      <h3 class="msegat-templates__title">{{ t('integrations.sms.msegat.approvedTitle') }}</h3>
      <BaseButton
        variant="secondary"
        size="sm"
        icon="fa-solid fa-file-import"
        :loading="action.state.value === PROVIDER_ACTION_STATES.PENDING"
        :disabled="disabled"
        @click="action.execute()"
      >
        {{ t('integrations.sms.msegat.loadTemplates') }}
      </BaseButton>
    </div>
    <p v-if="!loaded" class="field__hint">{{ t('integrations.sms.msegat.templatesHint') }}</p>
    <template v-else>
      <RecordedStamp kind="loaded" :at="loadedAt" class="msegat-templates__loaded" />
      <p v-if="!items.length" class="msegat-templates__empty" role="status">
        {{ t('integrations.sms.msegat.noTemplates') }}
      </p>
      <ul v-else class="msegat-templates__list">
        <li v-for="template in items" :key="template.id" class="msegat-templates__item">
          <span class="msegat-templates__name" dir="auto">{{ template.title }}</span>
          <span class="msegat-templates__body" dir="auto">{{ template.body }}</span>
        </li>
      </ul>
    </template>
  </section>
</template>

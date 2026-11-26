<script setup>
import { useI18n } from 'vue-i18n'

// The last step of a create wizard: what will be added, as text, before «إضافة».
defineProps({
  rows: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <div class="criteria-wizard-review">
    <p class="criteria-form__note" role="note">{{ t('criteria.wizard.reviewHint') }}</p>
    <dl class="criteria-summary">
      <div v-for="row in rows" :key="row.label" class="criteria-summary__row">
        <dt>{{ row.label }}</dt>
        <dd>
          <bdi :dir="row.dir">{{ row.value || '—' }}</bdi>
        </dd>
      </div>
    </dl>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  changes: { type: Array, required: true },
  fieldLabel: { type: Function, required: true },
  valueLabel: { type: Function, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="change-review" aria-live="polite">
    <h2 class="change-review__title">{{ t('entities.profile.reviewTitle') }}</h2>
    <p class="change-review__hint">{{ t('entities.profile.reviewHint') }}</p>
    <table class="change-review__table">
      <thead>
        <tr>
          <th scope="col">{{ t('entities.profile.reviewField') }}</th>
          <th scope="col">{{ t('entities.profile.reviewBefore') }}</th>
          <th scope="col">{{ t('entities.profile.reviewAfter') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="change in changes" :key="change.field" class="change-review__row">
          <th scope="row">{{ fieldLabel(change.field) }}</th>
          <td class="change-review__before">
            {{ valueLabel(change.field, change.before, 'before') }}
          </td>
          <td class="change-review__after">
            {{ valueLabel(change.field, change.after, 'after') }}
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

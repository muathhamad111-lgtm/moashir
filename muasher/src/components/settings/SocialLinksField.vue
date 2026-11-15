<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { SOCIAL_PLATFORMS } from '@/constants/socialPlatforms'
import { filledSocialLinks } from '@/composables/useSiteSettingsEditor'

const rows = defineModel('rows', { type: Array, required: true })

const props = defineProps({
  errorOf: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()
const baseId = useId()

const submittedOrder = computed(() => filledSocialLinks(rows.value).map((row) => row.platform))

function errorFor(platform) {
  const index = submittedOrder.value.indexOf(platform)
  return index === -1 ? '' : props.errorOf(`social.${index}.url`)
}
</script>

<template>
  <fieldset class="site-settings__group social-links">
    <legend class="site-settings__legend">{{ t('settings.homepage.siteSettings.social') }}</legend>
    <p class="site-settings__hint">{{ t('settings.homepage.siteSettings.socialHint') }}</p>
    <ul class="social-links__list">
      <li v-for="row in rows" :key="row.platform" class="social-links__row">
        <span :id="`${baseId}-${row.platform}`" class="social-links__platform">
          <AppIcon :name="SOCIAL_PLATFORMS[row.platform].icon" />
          {{ t(SOCIAL_PLATFORMS[row.platform].labelKey) }}
        </span>
        <div class="social-links__field">
          <input
            v-model="row.url"
            class="social-links__input"
            type="url"
            dir="ltr"
            :name="`social-${row.platform}`"
            :placeholder="SOCIAL_PLATFORMS[row.platform].example"
            :disabled="disabled"
            :aria-labelledby="`${baseId}-${row.platform}`"
            :aria-invalid="errorFor(row.platform) ? 'true' : undefined"
            :aria-describedby="
              errorFor(row.platform) ? `${baseId}-${row.platform}-error` : undefined
            "
          />
          <p
            v-if="errorFor(row.platform)"
            :id="`${baseId}-${row.platform}-error`"
            class="field__error"
            role="alert"
          >
            {{ errorFor(row.platform) }}
          </p>
        </div>
      </li>
    </ul>
  </fieldset>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseSelect from '@/components/form/BaseSelect.vue'
import { SITE_SETTINGS_LIMITS, SOCIAL_PLATFORM_KEYS } from '@/constants/cms'

const rows = defineModel('rows', { type: Array, required: true })

defineProps({
  errorOf: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()

const platformOptions = computed(() =>
  SOCIAL_PLATFORM_KEYS.map((platform) => ({
    value: platform,
    label: t(`settings.homepage.siteSettings.platforms.${platform}`),
  })),
)
const unusedPlatform = computed(() =>
  SOCIAL_PLATFORM_KEYS.find((platform) => !rows.value.some((row) => row.platform === platform)),
)
const canAdd = computed(
  () => rows.value.length < SITE_SETTINGS_LIMITS.socialMax && Boolean(unusedPlatform.value),
)

function add() {
  rows.value.push({ platform: unusedPlatform.value, url: '' })
}
</script>

<template>
  <fieldset class="site-settings__group">
    <legend class="site-settings__legend">{{ t('settings.homepage.siteSettings.social') }}</legend>
    <ul v-if="rows.length" class="site-settings__rows">
      <li v-for="(row, index) in rows" :key="`social-${index}`" class="site-settings__row">
        <BaseSelect
          v-model="row.platform"
          :name="`socialPlatform-${index}`"
          :label="t('settings.homepage.siteSettings.platform')"
          :options="platformOptions"
          :error="errorOf(`social.${index}.platform`)"
        />
        <BaseInput
          v-model="row.url"
          :name="`socialUrl-${index}`"
          :label="t('settings.homepage.siteSettings.url')"
          :hint="t('settings.homepage.siteSettings.socialUrlHint')"
          :error="errorOf(`social.${index}.url`)"
          input-dir="ltr"
        />
        <BaseButton
          variant="ghost"
          size="sm"
          icon="trash"
          icon-only
          :disabled="disabled"
          :aria-label="t('settings.homepage.siteSettings.removeSocial')"
          @click="rows.splice(index, 1)"
        />
      </li>
    </ul>
    <p v-else class="site-settings__empty">{{ t('settings.homepage.siteSettings.noSocial') }}</p>
    <BaseButton
      variant="secondary"
      size="sm"
      icon="plus"
      :disabled="disabled || !canAdd"
      @click="add"
    >
      {{ t('settings.homepage.siteSettings.addSocial') }}
    </BaseButton>
  </fieldset>
</template>

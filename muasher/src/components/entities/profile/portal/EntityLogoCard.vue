<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ScanStatusBadge from '@/components/accredited/ScanStatusBadge.vue'
import { SCAN_STATUSES } from '@/constants/accredited'
import { useEntityLogo } from '@/composables/useEntityLogo'
import { ENTITY_LOGO_RULES } from '@/constants/entityProfile'
import { isoDate } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  canEdit: { type: Boolean, default: false },
})

const { t } = useI18n()
const input = ref(null)
const { logo, busy, error, upload, remove, refreshExpiredLink } = useEntityLogo()

function choose() {
  input.value?.click()
}

function onChange(event) {
  const [file] = event.target.files ?? []
  event.target.value = ''
  upload(file)
}
</script>

<template>
  <section class="entity-logo" aria-labelledby="entity-logo-title">
    <h2 id="entity-logo-title" class="entity-logo__title">{{ t('portal.profile.logo.title') }}</h2>
    <div class="entity-logo__preview">
      <img
        v-if="logo?.url"
        class="entity-logo__image"
        :src="logo.url"
        :alt="t('portal.profile.logo.alt')"
        @error="refreshExpiredLink"
      />
      <AppIcon v-else name="image" class="entity-logo__placeholder" />
    </div>
    <p v-if="logo?.uploadedAt" class="entity-logo__meta">
      <ScanStatusBadge :status="SCAN_STATUSES.CLEAN" />
      <span>{{ t('portal.profile.logo.uploadedOn', { date: isoDate(logo.uploadedAt) }) }}</span>
    </p>
    <p class="entity-logo__rules">
      {{
        t('portal.profile.logo.rules', {
          min: ENTITY_LOGO_RULES.minSide,
          max: ENTITY_LOGO_RULES.maxSide,
        })
      }}
    </p>
    <p class="entity-logo__note">
      <AppIcon name="award" />
      {{ t('portal.profile.logo.note') }}
    </p>
    <p v-if="error" class="entity-logo__error" role="alert">{{ error }}</p>
    <div v-if="canEdit" class="entity-logo__actions">
      <input
        ref="input"
        class="entity-logo__input"
        type="file"
        :accept="ENTITY_LOGO_RULES.accept"
        :aria-label="t('portal.profile.logo.choose')"
        tabindex="-1"
        @change="onChange"
      />
      <BaseButton variant="secondary" icon="upload" :loading="busy" @click="choose">
        {{ logo ? t('portal.profile.logo.replace') : t('portal.profile.logo.upload') }}
      </BaseButton>
      <BaseButton v-if="logo" variant="ghost" icon="trash" :disabled="busy" @click="remove">
        {{ t('portal.profile.logo.remove') }}
      </BaseButton>
    </div>
  </section>
</template>

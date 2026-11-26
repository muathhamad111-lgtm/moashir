<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { notificationTypeName } from '@/utils/notificationTypeName'

const props = defineProps({
  type: { type: Object, required: true },
  showAudiences: { type: Boolean, default: true },
})

const { t, te } = useI18n()
const name = computed(() => notificationTypeName(props.type.code, t, te))
</script>

<template>
  <span class="notification-type-label">
    <span class="notification-type-label__title">
      <span class="notification-type-label__name">{{ name }}</span>
      <bdi class="notification-type-label__code" dir="ltr">{{ type.code }}</bdi>
    </span>
    <span class="notification-type-label__tags">
      <span
        v-if="type.isCore"
        class="notification-type-label__core"
        :title="t('settings.notifications.catalogue.coreHint')"
      >
        <AppIcon name="lock" size="sm" />
        {{ t('settings.notifications.catalogue.core') }}
      </span>
      <span
        v-for="kind in showAudiences ? type.audienceKinds : []"
        :key="kind"
        class="notification-type-label__audience"
        :data-kind="kind"
      >
        {{ t(`settings.notifications.catalogue.audienceKinds.${kind}`) }}
      </span>
    </span>
  </span>
</template>

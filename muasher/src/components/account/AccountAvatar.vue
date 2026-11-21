<script setup>
import { useAccountAvatar } from '@/composables/useAccountAvatar'
import AppIcon from '@/components/ui/AppIcon.vue'

const { imageUrl, isLogo, fallbackIcon, altText, markFailed } = useAccountAvatar()
</script>

<template>
  <img
    v-if="imageUrl"
    :class="['account-avatar__image', { 'account-avatar__image--logo': isLogo }]"
    :src="imageUrl"
    :alt="altText"
    data-testid="account-avatar-image"
    @error="markFailed"
  />
  <AppIcon v-else :name="fallbackIcon" class="account-avatar__icon" />
</template>

import { computed } from 'vue'
import { useHead } from '@unhead/vue'

function present(getter) {
  return computed(() => (getter() || '').trim())
}

export function usePageSeo({ title, description }) {
  const pageTitle = present(title)
  const pageDescription = present(description)
  const titleTags = ['og:title', 'twitter:title']
  const descriptionTags = ['description', 'og:description', 'twitter:description']

  useHead({
    title: () => pageTitle.value || undefined,
    meta: () => [
      ...(pageTitle.value ? titleTags.map((tag) => tagOf(tag, pageTitle.value)) : []),
      ...(pageDescription.value
        ? descriptionTags.map((tag) => tagOf(tag, pageDescription.value))
        : []),
    ],
  })

  return { pageTitle, pageDescription }
}

function tagOf(tag, content) {
  return tag.startsWith('og:') ? { property: tag, content } : { name: tag, content }
}

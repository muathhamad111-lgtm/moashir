import { useI18n } from 'vue-i18n'
import { CMS_PAGE_KEYS } from '@/constants/cms'
import { useConfirm } from './useConfirm'
import { useToast } from './useToast'
import { success } from './useSuccess'
import { useSiteStatusStore } from '@/stores/siteStatus'
import { usePublicHomeStore } from '@/stores/publicHome'

export function useCmsPublication(editor, pageKey) {
  const { t } = useI18n()
  const { confirm } = useConfirm()
  const toast = useToast()
  const siteStatus = useSiteStatusStore()
  const publicHome = usePublicHomeStore()

  function syncPublicSite(page) {
    if (pageKey.value !== CMS_PAGE_KEYS.HOME) return
    siteStatus.markPublished(page.published)
    publicHome.invalidate()
  }

  async function change(action, messages) {
    const accepted = await confirm(messages.confirm)
    if (!accepted) return
    try {
      syncPublicSite(await action())
      success.show({ title: messages.success })
    } catch (error) {
      toast.error(error?.normalized?.message || t('cms.toasts.failed'))
    }
  }

  function publish() {
    return change(editor.publish, {
      confirm: {
        title: t('cms.publication.confirmPublish.title'),
        confirmLabel: t('cms.publication.publish'),
        variant: 'confirm',
      },
      success: t('cms.publication.publishedToast'),
    })
  }

  function unpublish() {
    const isHome = pageKey.value === CMS_PAGE_KEYS.HOME
    return change(editor.unpublish, {
      confirm: {
        title: t('cms.publication.confirmUnpublish.title'),
        message: isHome ? t('cms.publication.confirmUnpublish.home') : '',
        confirmLabel: t('cms.publication.unpublish'),
        variant: 'warning',
      },
      success: t('cms.publication.unpublishedToast'),
    })
  }

  return { publish, unpublish }
}

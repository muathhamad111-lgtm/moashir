import { describe, expect, it } from 'vitest'
import { useConfirm } from '@/composables/useConfirm'

describe('useConfirm', () => {
  it('opens with the given payload and resolves true on accept', async () => {
    const { confirm, confirmState, accept } = useConfirm()
    const promise = confirm({ message: 'هل أنت متأكد؟', variant: 'danger' })
    expect(confirmState.isOpen).toBe(true)
    expect(confirmState.message).toBe('هل أنت متأكد؟')
    expect(confirmState.variant).toBe('danger')
    accept()
    await expect(promise).resolves.toBe(true)
    expect(confirmState.isOpen).toBe(false)
  })

  it('resolves false on dismiss', async () => {
    const { confirm, dismiss } = useConfirm()
    const promise = confirm({ message: 'تأكيد الحذف' })
    dismiss()
    await expect(promise).resolves.toBe(false)
  })
})

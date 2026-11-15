import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAvatar } from './mappers/avatar'

const { write } = domainRequests('accountAvatar', 'account-avatar')

function withAvatar(envelope) {
  return { ...envelope, data: { avatar: toAvatar(envelope?.data?.avatar) } }
}

export const accountAvatarService = {
  upload(file) {
    const body = new FormData()
    body.append('file', file)
    return write('upload', () => apiClient.post('/me/avatar', body), { file: file.type }).then(
      withAvatar,
    )
  },
  remove() {
    return write('remove', () => apiClient.delete('/me/avatar'), {}).then(withAvatar)
  },
}

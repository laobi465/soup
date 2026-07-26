import request from '@/utils/request'

export function getCardList(params) {
  return request({
    url: '/merchant/cards',
    method: 'get',
    params
  })
}

export function getCard(id) {
  return request({
    url: `/merchant/cards/${id}`,
    method: 'get'
  })
}

export function generateCard(data) {
  return request({
    url: '/merchant/cards/generate',
    method: 'post',
    data
  })
}

export function batchGenerateCard(data) {
  return request({
    url: '/merchant/cards/batch-generate',
    method: 'post',
    data
  })
}

export function banCard(id, reason = '') {
  return request({
    url: `/merchant/cards/${id}/ban`,
    method: 'put',
    data: { reason }
  })
}

export function unbanCard(id) {
  return request({
    url: `/merchant/cards/${id}/unban`,
    method: 'put'
  })
}

export function voidCard(id) {
  return request({
    url: `/merchant/cards/${id}/void`,
    method: 'put'
  })
}

export function renewCard(id, duration) {
  return request({
    url: `/merchant/cards/${id}/renew`,
    method: 'put',
    data: { duration }
  })
}

export function unbindDevice(cardId, deviceId) {
  return request({
    url: `/merchant/cards/${cardId}/device/${deviceId}`,
    method: 'delete'
  })
}

export function exportCards(params) {
  return request({
    url: '/merchant/cards/export',
    method: 'post',
    data: params,
    responseType: 'blob'
  })
}

export function importCards(data) {
  return request({
    url: '/merchant/cards/import',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

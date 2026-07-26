import request from '@/utils/request'

export function getCardList(params) {
  return request({
    url: '/admin/cards',
    method: 'get',
    params
  })
}

export function getCard(id) {
  return request({
    url: `/admin/cards/${id}`,
    method: 'get'
  })
}

export function banCard(id, reason = '') {
  return request({
    url: `/admin/cards/${id}/ban`,
    method: 'put',
    data: { reason }
  })
}

export function unbanCard(id) {
  return request({
    url: `/admin/cards/${id}/unban`,
    method: 'put'
  })
}

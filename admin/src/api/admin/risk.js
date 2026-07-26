import request from '@/utils/request'

export function getBlacklist(params) {
  return request({
    url: '/admin/risk/blacklist',
    method: 'get',
    params
  })
}

export function addBlacklist(data) {
  return request({
    url: '/admin/risk/blacklist',
    method: 'post',
    data
  })
}

export function updateBlacklist(id, data) {
  return request({
    url: `/admin/risk/blacklist/${id}`,
    method: 'put',
    data
  })
}

export function deleteBlacklist(id) {
  return request({
    url: `/admin/risk/blacklist/${id}`,
    method: 'delete'
  })
}

export function getRiskAlerts(params) {
  return request({
    url: '/admin/risk/alerts',
    method: 'get',
    params
  })
}

export function getRiskOverview() {
  return request({
    url: '/admin/risk/overview',
    method: 'get'
  })
}

import request from '@/utils/request'

export function getSystemConfig(group) {
  return request({
    url: '/admin/config',
    method: 'get',
    params: { group }
  })
}

export function saveSystemConfig(data) {
  return request({
    url: '/admin/config',
    method: 'put',
    data
  })
}

export function clearConfigCache() {
  return request({
    url: '/admin/config/clear-cache',
    method: 'post'
  })
}

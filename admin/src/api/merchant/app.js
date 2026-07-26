import request from '@/utils/request'

export function getAppList(params) {
  return request({
    url: '/merchant/apps',
    method: 'get',
    params
  })
}

export function getApp(id) {
  return request({
    url: `/merchant/apps/${id}`,
    method: 'get'
  })
}

export function createApp(data) {
  return request({
    url: '/merchant/apps',
    method: 'post',
    data
  })
}

export function updateApp(id, data) {
  return request({
    url: `/merchant/apps/${id}`,
    method: 'put',
    data
  })
}

export function updateAppStatus(id, status) {
  return request({
    url: `/merchant/apps/${id}/status`,
    method: 'put',
    data: { status }
  })
}

export function resetAppSecret(id, password) {
  return request({
    url: `/merchant/apps/${id}/reset-secret`,
    method: 'post',
    data: { password }
  })
}

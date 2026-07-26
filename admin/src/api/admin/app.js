import request from '@/utils/request'

export function getAppList(params) {
  return request({
    url: '/admin/apps',
    method: 'get',
    params
  })
}

export function getApp(id) {
  return request({
    url: `/admin/apps/${id}`,
    method: 'get'
  })
}

export function updateAppStatus(id, status) {
  return request({
    url: `/admin/apps/${id}/status`,
    method: 'put',
    data: { status }
  })
}

export function deleteApp(id) {
  return request({
    url: `/admin/apps/${id}`,
    method: 'delete'
  })
}

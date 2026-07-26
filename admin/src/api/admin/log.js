import request from '@/utils/request'

export function getOperationLogs(params) {
  return request({
    url: '/admin/logs/operation',
    method: 'get',
    params
  })
}

export function getLoginLogs(params) {
  return request({
    url: '/admin/logs/login',
    method: 'get',
    params
  })
}

export function getApiLogs(params) {
  return request({
    url: '/admin/logs/api',
    method: 'get',
    params
  })
}

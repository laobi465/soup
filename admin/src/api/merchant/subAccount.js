import request from '@/utils/request'

export function getSubAccountList(params) {
  return request({
    url: '/merchant/sub-accounts',
    method: 'get',
    params
  })
}

export function createSubAccount(data) {
  return request({
    url: '/merchant/sub-accounts',
    method: 'post',
    data
  })
}

export function updateSubAccount(id, data) {
  return request({
    url: `/merchant/sub-accounts/${id}`,
    method: 'put',
    data
  })
}

export function updateSubAccountStatus(id, status) {
  return request({
    url: `/merchant/sub-accounts/${id}/status`,
    method: 'put',
    data: { status }
  })
}

export function resetSubAccountPassword(id, password) {
  return request({
    url: `/merchant/sub-accounts/${id}/reset-password`,
    method: 'put',
    data: { password }
  })
}

export function deleteSubAccount(id) {
  return request({
    url: `/merchant/sub-accounts/${id}`,
    method: 'delete'
  })
}

export function getSubRoleList() {
  return request({
    url: '/merchant/sub-roles',
    method: 'get'
  })
}

export function createSubRole(data) {
  return request({
    url: '/merchant/sub-roles',
    method: 'post',
    data
  })
}

export function updateSubRole(id, data) {
  return request({
    url: `/merchant/sub-roles/${id}`,
    method: 'put',
    data
  })
}

export function deleteSubRole(id) {
  return request({
    url: `/merchant/sub-roles/${id}`,
    method: 'delete'
  })
}

export function getMerchantDashboard() {
  return request({
    url: '/merchant/dashboard',
    method: 'get'
  })
}

export function getMerchantCardStats(appId) {
  return request({
    url: '/merchant/stats/card',
    method: 'get',
    params: { app_id: appId }
  })
}

export function getMerchantApiStats(appId, range) {
  return request({
    url: '/merchant/stats/api',
    method: 'get',
    params: { app_id: appId, range }
  })
}

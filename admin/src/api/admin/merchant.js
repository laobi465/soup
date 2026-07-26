import request from '@/utils/request'

export function getMerchantList(params) {
  return request({
    url: '/admin/merchants',
    method: 'get',
    params
  })
}

export function getMerchantDetail(id) {
  return request({
    url: `/admin/merchants/${id}`,
    method: 'get'
  })
}

export function updateMerchantStatus(id, status) {
  return request({
    url: `/admin/merchants/${id}/status`,
    method: 'put',
    data: { status }
  })
}

export function resetMerchantPassword(id, password) {
  return request({
    url: `/admin/merchants/${id}/reset-password`,
    method: 'put',
    data: { password }
  })
}

export function adjustMerchantQuota(id, data) {
  return request({
    url: `/admin/merchants/${id}/adjust-quota`,
    method: 'put',
    data
  })
}

export function changeMerchantPackage(id, data) {
  return request({
    url: `/admin/merchants/${id}/change-package`,
    method: 'put',
    data
  })
}

import request from '@/utils/request'

export function getProfile() {
  return request({
    url: '/merchant/profile',
    method: 'get'
  })
}

export function updateProfile(data) {
  return request({
    url: '/merchant/profile',
    method: 'put',
    data
  })
}

export function changePassword(data) {
  return request({
    url: '/merchant/change-password',
    method: 'put',
    data
  })
}

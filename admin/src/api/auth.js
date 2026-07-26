import request from '@/utils/request'

export function login(data) {
  return request({
    url: '/admin/auth/login',
    method: 'post',
    data
  })
}

export function getUserInfo() {
  return request({
    url: '/admin/auth/info',
    method: 'get'
  })
}

export function logout() {
  return request({
    url: '/admin/auth/logout',
    method: 'post'
  })
}

export function refreshToken(data) {
  return request({
    url: '/admin/auth/refresh',
    method: 'post',
    data
  })
}

export function registerMerchant(data) {
  return request({
    url: '/register/merchant',
    method: 'post',
    data
  })
}

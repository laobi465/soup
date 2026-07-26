import request from '@/utils/request'

export function getPaymentConfig() {
  return request({
    url: '/admin/payment/config',
    method: 'get'
  })
}

export function updatePaymentConfig(data) {
  return request({
    url: '/admin/payment/config',
    method: 'put',
    data
  })
}

export function getAdminOrderList(params) {
  return request({
    url: '/admin/orders',
    method: 'get',
    params
  })
}

export function getAdminOrderDetail(id) {
  return request({
    url: '/admin/orders/' + id,
    method: 'get'
  })
}

export function adminOrderRefund(data) {
  return request({
    url: '/admin/orders/refund',
    method: 'post',
    data
  })
}

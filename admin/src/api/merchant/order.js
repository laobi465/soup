import request from '@/utils/request'

export function getOrderList(params) {
  return request({
    url: '/merchant/orders',
    method: 'get',
    params
  })
}

export function getOrderDetail(id) {
  return request({
    url: '/merchant/orders/' + id,
    method: 'get'
  })
}

export function rechargeOrder(data) {
  return request({
    url: '/merchant/orders/recharge',
    method: 'post',
    data
  })
}

export function packageOrder(data) {
  return request({
    url: '/merchant/orders/package',
    method: 'post',
    data
  })
}

export function refundOrder(data) {
  return request({
    url: '/merchant/orders/refund',
    method: 'post',
    data
  })
}

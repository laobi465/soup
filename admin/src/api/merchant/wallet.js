import request from '@/utils/request'

export function getWallet() {
  return request({
    url: '/merchant/wallet',
    method: 'get'
  })
}

export function getTransactions(params) {
  return request({
    url: '/merchant/wallet/transactions',
    method: 'get',
    params
  })
}

export function recharge(data) {
  return request({
    url: '/merchant/wallet/recharge',
    method: 'post',
    data
  })
}

import request from '@/utils/request'

export function getAgentDashboard() {
  return request({
    url: '/agent/dashboard',
    method: 'get'
  })
}

export function getAgentInvite() {
  return request({
    url: '/agent/invite',
    method: 'get'
  })
}

export function getAgentTeam(params) {
  return request({
    url: '/agent/team',
    method: 'get',
    params
  })
}

export function getAgentCommission(params) {
  return request({
    url: '/agent/commission',
    method: 'get',
    params
  })
}

export function getAgentWallet() {
  return request({
    url: '/agent/wallet',
    method: 'get'
  })
}

export function applyWithdraw(data) {
  return request({
    url: '/agent/withdraw',
    method: 'post',
    data
  })
}

export function getWithdrawList(params) {
  return request({
    url: '/agent/withdraw/list',
    method: 'get',
    params
  })
}

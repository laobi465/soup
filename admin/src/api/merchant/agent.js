import request from '@/utils/request'

export function getAgentList(params) {
  return request({
    url: '/merchant/agents',
    method: 'get',
    params
  })
}

export function getAgentDetail(id) {
  return request({
    url: '/merchant/agents/' + id,
    method: 'get'
  })
}

export function getAgentTree() {
  return request({
    url: '/merchant/agents/tree',
    method: 'get'
  })
}

export function updateAgentLevel(id, data) {
  return request({
    url: '/merchant/agents/' + id + '/level',
    method: 'put',
    data
  })
}

export function updateAgentStatus(id, status) {
  return request({
    url: '/merchant/agents/' + id + '/status',
    method: 'put',
    data: { status }
  })
}

export function getAgentOrders(id, params) {
  return request({
    url: '/merchant/agents/' + id + '/orders',
    method: 'get',
    params
  })
}

export function getAgentCommissionList(params) {
  return request({
    url: '/merchant/agents/commission',
    method: 'get',
    params
  })
}

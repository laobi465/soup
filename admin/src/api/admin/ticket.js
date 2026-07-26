import request from '@/utils/request'

export function getMyTickets(params) {
  return request({
    url: '/tickets',
    method: 'get',
    params
  })
}

export function getMyTicketDetail(id) {
  return request({
    url: '/tickets/' + id,
    method: 'get'
  })
}

export function createTicket(data) {
  return request({
    url: '/tickets',
    method: 'post',
    data
  })
}

export function replyTicket(id, content) {
  return request({
    url: '/tickets/' + id + '/reply',
    method: 'post',
    data: { content }
  })
}

export function closeTicket(id) {
  return request({
    url: '/tickets/' + id + '/close',
    method: 'put'
  })
}

export function getAdminTickets(params) {
  return request({
    url: '/admin/tickets',
    method: 'get',
    params
  })
}

export function getAdminTicketDetail(id) {
  return request({
    url: '/admin/tickets/' + id,
    method: 'get'
  })
}

export function adminReplyTicket(id, content) {
  return request({
    url: '/admin/tickets/' + id + '/reply',
    method: 'post',
    data: { content }
  })
}

export function updateTicketStatus(id, status) {
  return request({
    url: '/admin/tickets/' + id + '/status',
    method: 'put',
    data: { status }
  })
}

export function assignTicket(id, handlerId) {
  return request({
    url: '/admin/tickets/' + id + '/assign',
    method: 'put',
    data: { handler_id: handlerId }
  })
}

export function getTicketStats() {
  return request({
    url: '/admin/tickets/stats',
    method: 'get'
  })
}

import request from '@/utils/request'

export function getDashboard() {
  return request({
    url: '/admin/dashboard',
    method: 'get'
  })
}

export function getStatsOverview() {
  return request({
    url: '/admin/stats/overview',
    method: 'get'
  })
}

export function getStatsTrend(range) {
  return request({
    url: '/admin/stats/trend',
    method: 'get',
    params: { range }
  })
}

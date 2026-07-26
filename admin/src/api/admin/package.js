import request from '@/utils/request'

export function getPackageList(params) {
  return request({
    url: '/admin/packages',
    method: 'get',
    params
  })
}

export function getPackage(id) {
  return request({
    url: `/admin/packages/${id}`,
    method: 'get'
  })
}

export function createPackage(data) {
  return request({
    url: '/admin/packages',
    method: 'post',
    data
  })
}

export function updatePackage(id, data) {
  return request({
    url: `/admin/packages/${id}`,
    method: 'put',
    data
  })
}

export function deletePackage(id) {
  return request({
    url: `/admin/packages/${id}`,
    method: 'delete'
  })
}

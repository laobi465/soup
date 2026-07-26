import request from '@/utils/request'

export function getCurrentPackage() {
  return request({
    url: '/merchant/package/current',
    method: 'get'
  })
}

export function getPackageList() {
  return request({
    url: '/merchant/packages',
    method: 'get'
  })
}

export function upgradePackage(data) {
  return request({
    url: '/merchant/package/upgrade',
    method: 'post',
    data
  })
}

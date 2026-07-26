import request from '@/utils/request'

export function getShopProductList(params) {
  return request({
    url: '/merchant/shop/products',
    method: 'get',
    params
  })
}

export function getShopProduct(id) {
  return request({
    url: '/merchant/shop/products/' + id,
    method: 'get'
  })
}

export function createShopProduct(data) {
  return request({
    url: '/merchant/shop/products',
    method: 'post',
    data
  })
}

export function updateShopProduct(id, data) {
  return request({
    url: '/merchant/shop/products/' + id,
    method: 'put',
    data
  })
}

export function updateShopProductStatus(id, status) {
  return request({
    url: '/merchant/shop/products/' + id + '/status',
    method: 'put',
    data: { status }
  })
}

export function deleteShopProduct(id) {
  return request({
    url: '/merchant/shop/products/' + id,
    method: 'delete'
  })
}

import request from '@/utils/request'

export function getAnnouncementList(params) {
  return request({
    url: '/admin/announcements',
    method: 'get',
    params
  })
}

export function getAnnouncementDetail(id) {
  return request({
    url: '/admin/announcements/' + id,
    method: 'get'
  })
}

export function createAnnouncement(data) {
  return request({
    url: '/admin/announcements',
    method: 'post',
    data
  })
}

export function updateAnnouncement(id, data) {
  return request({
    url: '/admin/announcements/' + id,
    method: 'put',
    data
  })
}

export function deleteAnnouncement(id) {
  return request({
    url: '/admin/announcements/' + id,
    method: 'delete'
  })
}

export function updateAnnouncementStatus(id, status) {
  return request({
    url: '/admin/announcements/' + id + '/status',
    method: 'put',
    data: { status }
  })
}

export function getPublicAnnouncements(params) {
  return request({
    url: '/announcements',
    method: 'get',
    params
  })
}

export function getPublicAnnouncementDetail(id) {
  return request({
    url: '/announcements/' + id,
    method: 'get'
  })
}

export function getAnnouncementPopup(lastId) {
  return request({
    url: '/announcements/popup',
    method: 'get',
    params: { last_id: lastId }
  })
}

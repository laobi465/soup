import request from '@/utils/request'

export function uploadImage(file) {
  const formData = new FormData()
  formData.append('file', file)
  return request({
    url: '/upload/image',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

export function uploadFile(file) {
  const formData = new FormData()
  formData.append('file', file)
  return request({
    url: '/upload/file',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

export function getUploadConfig() {
  return request({
    url: '/upload/config',
    method: 'get'
  })
}

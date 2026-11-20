import request from './request'

export const login = (data) => request.post('/passport/auth/login', data)
export const register = (data) => request.post('/passport/auth/register', data)
export const forget = (data) => request.post('/passport/auth/forget', data)
export const token2Login = (verify) => request.get('/passport/auth/token2Login', { params: { verify } })
export const sendEmailVerify = (data) => request.post('/passport/comm/sendEmailVerify', data)
export const getGuestConfig = () => request.get('/guest/comm/config')
export const recordInvitePv = (inviteCode) => request.post('/passport/comm/pv', { invite_code: inviteCode })

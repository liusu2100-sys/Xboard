import request from './request'
import { getCurrentLocale } from '@/utils/locale'

function withLanguage(params) {
  return { language: getCurrentLocale(), ...(params || {}) }
}

export const getUserInfo = () => request.get('/user/info')
export const getSubscribe = () => request.get('/user/getSubscribe')
export const getSubscribeQrCode = (params) => request.get('/user/subscribe/qrcode', { params })
export const getStat = () => request.get('/user/getStat')
export const checkLogin = () => request.get('/user/checkLogin')
export const changePassword = (data) => request.post('/user/changePassword', data)
export const updateUser = (data) => request.post('/user/update', data)
export const resetSecurity = () => request.get('/user/resetSecurity')
export const transfer = (data) => request.post('/user/transfer', data)
export const getNotices = () => request.get('/user/notice/fetch')
export const getConfig = () => request.get('/user/comm/config')
export const getPlans = (params) => request.get('/user/plan/fetch', { params })
export const getServers = () => request.get('/user/server/fetch')
export const getOrders = () => request.get('/user/order/fetch')
export const getOrderDetail = (tradeNo) => request.get('/user/order/detail', { params: { trade_no: tradeNo } })
export const saveOrder = (data) => request.post('/user/order/save', data)
export const checkout = (data) => request.post('/user/order/checkout', data)
export const cancelOrder = (data) => request.post('/user/order/cancel', data)
export const getPaymentMethod = () => request.get('/user/order/getPaymentMethod')
export const checkOrder = (tradeNo) => request.get('/user/order/check', { params: { trade_no: tradeNo } })
export const checkCoupon = (data) => request.post('/user/coupon/check', data)
export const getInviteCodes = () => request.get('/user/invite/fetch')
export const createInviteCode = () => request.get('/user/invite/save')
export const deleteInviteCode = (data) => request.post('/user/invite/delete', data)
export const getInviteDetails = (params = { current: 1, page_size: 10 }) => request.get('/user/invite/details', { params })
export const getTickets = () => request.get('/user/ticket/fetch')
export const getTicketDetail = (id) => request.get('/user/ticket/fetch', { params: { id } })
export const saveTicket = (data) => request.post('/user/ticket/save', data)
export const replyTicket = (data) => request.post('/user/ticket/reply', data)
export const closeTicket = (data) => request.post('/user/ticket/close', data)
export const withdrawTicket = (data) => request.post('/user/ticket/withdraw', data)
export const getKnowledge = (params) => request.get('/user/knowledge/fetch', { params: withLanguage(params) })
export const getKnowledgeCategories = () => request.get('/user/knowledge/getCategory')
export const getTrafficLog = () => request.get('/user/stat/getTrafficLog')

// Phase 5 Additions
export const getTelegramBotInfo = () => request.get('/user/telegram/getBotInfo')
export const getActiveSession = () => request.get('/user/getActiveSession')
export const removeActiveSession = (data) => request.post('/user/removeActiveSession', data)
export const redeemGiftCard = (data) => request.post('/user/gift-card/redeem', data)

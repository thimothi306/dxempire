import { api } from '../lib/axios';

// ─── Sales Hierarchy ──────────────────────────────────────────────────────────
export const hierarchyService = {
  list:           (params?: Record<string, string>) => api.get('/sales/hierarchy', { params }).then(r => r.data),
  tree:           () => api.get('/sales/hierarchy/tree').then(r => r.data.data),
  create:         (data: Record<string, unknown>) => api.post('/sales/hierarchy', data).then(r => r.data.data),
  update:         (id: number, data: Record<string, unknown>) => api.put(`/sales/hierarchy/${id}`, data).then(r => r.data.data),
  remove:         (id: number) => api.delete(`/sales/hierarchy/${id}`),
  show:           (id: number) => api.get(`/sales/hierarchy/${id}`).then(r => r.data.data),
  downline:       (id: number) => api.get(`/sales/hierarchy/${id}/downline`).then(r => r.data.data),
  performance:    (id: number) => api.get(`/sales/hierarchy/${id}/performance`).then(r => r.data.data),
  assignDealer:   (id: number, dealer_id: number) => api.post(`/sales/hierarchy/${id}/assign-dealer`, { dealer_id }).then(r => r.data),
};

// ─── Offers ───────────────────────────────────────────────────────────────────
export const offersService = {
  list:     (params?: Record<string, string>) => api.get('/offers', { params }).then(r => r.data),
  active:   () => api.get('/offers/active').then(r => r.data.data),
  create:   (data: Record<string, unknown>) => api.post('/offers', data).then(r => r.data.data),
  update:   (id: number, data: Record<string, unknown>) => api.put(`/offers/${id}`, data).then(r => r.data.data),
  remove:   (id: number) => api.delete(`/offers/${id}`),
  validate: (code: string, order_total: number) => api.post('/offers/validate', { code, order_total }).then(r => r.data.data),
};

// ─── Peti Transfers ───────────────────────────────────────────────────────────
export const petiService = {
  list:     (params?: Record<string, string>) => api.get('/peti-transfers', { params }).then(r => r.data),
  create:   (data: Record<string, unknown>) => api.post('/peti-transfers', data).then(r => r.data.data),
  show:     (id: number) => api.get(`/peti-transfers/${id}`).then(r => r.data.data),
  approve:  (id: number) => api.post(`/peti-transfers/${id}/approve`).then(r => r.data),
  complete: (id: number) => api.post(`/peti-transfers/${id}/complete`).then(r => r.data),
  cancel:   (id: number) => api.post(`/peti-transfers/${id}/cancel`).then(r => r.data),
};

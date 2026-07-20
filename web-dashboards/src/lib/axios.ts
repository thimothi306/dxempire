import axios from 'axios';
import { useAuthStore } from '../stores/authStore';

const BASE_URL = import.meta.env.VITE_API_URL || 'https://api.dxempire.com/api/v1';

export const api = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('dx_admin_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Prevent multiple parallel 401s from triggering logout more than once
let loggingOut = false;

api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401 && !loggingOut) {
      loggingOut = true;
      useAuthStore.getState().logout();
      // Reset flag after navigation so future sessions work correctly
      setTimeout(() => { loggingOut = false; }, 2000);
    }
    return Promise.reject(error);
  }
);

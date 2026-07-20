import { create } from 'zustand';
import type { User } from '../types';

interface AuthState {
  token: string | null;
  user: User | null;
  setAuth: (token: string, user: User) => void;
  logout: () => void;
}

const stored = localStorage.getItem('dx_admin_user');

export const useAuthStore = create<AuthState>((set) => ({
  token: localStorage.getItem('dx_admin_token'),
  user: stored ? JSON.parse(stored) : null,

  setAuth: (token, user) => {
    localStorage.setItem('dx_admin_token', token);
    localStorage.setItem('dx_admin_user', JSON.stringify(user));
    set({ token, user });
  },

  logout: () => {
    localStorage.removeItem('dx_admin_token');
    localStorage.removeItem('dx_admin_user');
    set({ token: null, user: null });
  },
}));

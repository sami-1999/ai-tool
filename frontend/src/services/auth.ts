// Authentication service
import { apiClient, API_ENDPOINTS } from '@/lib/api';
import { LoginRequest, RegisterRequest, AuthResponse } from '@/types';

export const authService = {
  async login(credentials: LoginRequest): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>(API_ENDPOINTS.LOGIN, credentials);
    if (response.success && response.data) {
      localStorage.setItem('token', response.data.token);
      localStorage.setItem('user', JSON.stringify(response.data.user));
    }
    return response.data;
  },

  async register(userData: RegisterRequest): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>(API_ENDPOINTS.REGISTER, userData);
    if (response.success && response.data) {
      localStorage.setItem('token', response.data.token);
      localStorage.setItem('user', JSON.stringify(response.data.user));
    }
    return response.data;
  },

  async logout(): Promise<void> {
    try {
      await apiClient.post(API_ENDPOINTS.LOGOUT);
    } catch (error) {
      // Even if logout fails, clear local storage
    } finally {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    }
  },

  getUser() {
    if (typeof window !== 'undefined') {
      const userStr = localStorage.getItem('user');
      return userStr ? JSON.parse(userStr) : null;
    }
    return null;
  },

  getToken() {
    if (typeof window !== 'undefined') {
      return localStorage.getItem('token');
    }
    return null;
  },

  isAuthenticated() {
    if (typeof window !== 'undefined') {
      return !!this.getToken();
    }
    return false;
  }
};

// API configuration and axios instance
import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';

const BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api';

export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data: T;
}

// API endpoints configuration
export const API_ENDPOINTS = {
  // Auth
  LOGIN: '/login',
  REGISTER: '/register',
  LOGOUT: '/logout',
  
  // Profile (v2 - current)
  PROFILE: '/profile',
  
  // Legacy Profile (deprecated)
  LEGACY_PROFILE: (id: number) => `/user/profile/${id}`,
  
  // User Skills
  USER_SKILLS: '/profile/skills',
  USER_SKILL: (id: number) => `/profile/skills/${id}`,
  
  // Skills
  SKILLS: '/skill',
  SKILL: (id: number) => `/skill/${id}`,
  
  // Projects  
  PROJECTS: '/project',
  PROJECT: (id: number) => `/project/${id}`,
  
  // Proposals
  PROPOSALS: '/proposals',
  PROPOSAL: (id: number) => `/proposals/${id}`,
  PROPOSAL_GENERATE: '/proposals/generate',
  PROPOSAL_FEEDBACK: (id: number) => `/proposals/${id}/feedback`,
  
  // AI Testing
  TEST_OPENAI: '/test/openai',
  TEST_CLAUDE: '/test/claude',
  TEST_PROPOSAL_GENERATION: '/test/proposal-generation',
  TEST_CLAUDE_PROPOSAL: '/test/claude-proposal-generation',
  TEST_COMPARE_PROVIDERS: '/test/compare-providers',
} as const;

class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: BASE_URL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    this.setupInterceptors();
  }

  private setupInterceptors() {
    // Request interceptor to add auth token
    this.client.interceptors.request.use((config) => {
      if (typeof window !== 'undefined') {
        const token = localStorage.getItem('token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
      }
      return config;
    });

    // Response interceptor to handle errors
    this.client.interceptors.response.use(
      (response) => response,
      (error) => {
        // Handle authentication errors
        if (error.response?.status === 401) {
          this.handleUnauthorized();
        }
        
        // Handle other HTTP errors
        if (error.response?.status >= 500) {
          console.error('Server error:', error.response.data);
        }
        
        return Promise.reject(error);
      }
    );
  }

  private handleUnauthorized() {
    if (typeof window !== 'undefined') {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      
      // Only redirect if we're not already on auth pages
      const currentPath = window.location.pathname;
      if (!['/login', '/register'].includes(currentPath)) {
        window.location.href = '/login';
      }
    }
  }

  async get<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.client.get(url, config);
    return response.data;
  }

  async post<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.client.post(url, data, config);
    return response.data;
  }

  async put<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.client.put(url, data, config);
    return response.data;
  }

  async delete<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.client.delete(url, config);
    return response.data;
  }
}

export const apiClient = new ApiClient();

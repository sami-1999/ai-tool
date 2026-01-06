// AI Testing service
import { apiClient, API_ENDPOINTS } from '@/lib/api';

export interface TestResult {
  success: boolean;
  message: string;
  data?: unknown;
}

export interface ProposalTestRequest {
  job_description: string;
}

export interface CompareProvidersRequest {
  prompt: string;
}

export interface ComparisonResult {
  openai: {
    success: boolean;
    response?: string;
    tokens_used?: number;
    response_time?: number;
    error?: string;
  };
  claude: {
    success: boolean;
    response?: string;
    tokens_used?: number;
    response_time?: number;
    error?: string;
  };
}

export const aiTestingService = {
  async testOpenAIConnection(): Promise<TestResult> {
    const response = await apiClient.get<TestResult>(API_ENDPOINTS.TEST_OPENAI);
    return response.data;
  },

  async testClaudeConnection(): Promise<TestResult> {
    const response = await apiClient.get<TestResult>(API_ENDPOINTS.TEST_CLAUDE);
    return response.data;
  },

  async testOpenAIProposalGeneration(data: ProposalTestRequest): Promise<TestResult> {
    const response = await apiClient.post<TestResult>(API_ENDPOINTS.TEST_PROPOSAL_GENERATION, data);
    return response.data;
  },

  async testClaudeProposalGeneration(data: ProposalTestRequest): Promise<TestResult> {
    const response = await apiClient.post<TestResult>(API_ENDPOINTS.TEST_CLAUDE_PROPOSAL, data);
    return response.data;
  },

  async compareProviders(data: CompareProvidersRequest): Promise<ComparisonResult> {
    const response = await apiClient.post<ComparisonResult>(API_ENDPOINTS.TEST_COMPARE_PROVIDERS, data);
    return response.data;
  }
};

// Proposals service
import { apiClient, API_ENDPOINTS } from '@/lib/api';
import { Proposal, ProposalGenerationRequest, ProposalFeedback, ProposalGenerationResponse } from '@/types';

// Extended proposal interface for display purposes
export interface ProposalWithJobData extends Proposal {
  job_description?: string;
  provider?: string;
  job_analysis?: import('@/types').JobAnalysis;
  matched_projects?: import('@/types').MatchedProject[];
}

export const proposalService = {
  async getProposals(): Promise<ProposalWithJobData[]> {
    const response = await apiClient.get<ProposalWithJobData[]>(API_ENDPOINTS.PROPOSALS);
    return response.data;
  },

  async getProposal(id: number): Promise<ProposalWithJobData> {
    const response = await apiClient.get<ProposalWithJobData>(API_ENDPOINTS.PROPOSAL(id));
    return response.data;
  },

  async generateProposal(proposalData: ProposalGenerationRequest): Promise<ProposalWithJobData> {
    const response = await apiClient.post<ProposalGenerationResponse>(API_ENDPOINTS.PROPOSAL_GENERATE, proposalData);
    const data = response.data;
    
    // Transform the response to include job description and provider for easier display
    return {
      ...data.proposal,
      job_description: data.job_analysis?.description || proposalData.job_description,
      provider: proposalData.provider || 'claude',
      job_analysis: data.job_analysis,
      matched_projects: data.matched_projects
    };
  },

  async submitFeedback(id: number, feedbackData: ProposalFeedback): Promise<void> {
    await apiClient.post(API_ENDPOINTS.PROPOSAL_FEEDBACK(id), feedbackData);
  }
};

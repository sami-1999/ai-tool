'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { authService } from '@/services/auth';
import { proposalService, ProposalWithJobData } from '@/services/proposals';
import { ProposalGenerationRequest } from '@/types';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import Navigation from '@/components/common/Navigation';

export default function ProposalsPage() {
  const router = useRouter();
  const [proposals, setProposals] = useState<ProposalWithJobData[]>([]);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [showGenerateForm, setShowGenerateForm] = useState(false);
  const [formData, setFormData] = useState<ProposalGenerationRequest>({
    job_description: '',
    provider: 'claude'
  });

  useEffect(() => {
    const fetchProposals = async () => {
      if (!authService.isAuthenticated()) {
        router.replace('/login');
        return;
      }

      try {
        const proposalsData = await proposalService.getProposals();
        setProposals(proposalsData);
      } catch (error: unknown) {
        console.error('Error fetching proposals:', error);
        if (error && typeof error === 'object' && 'response' in error) {
          const response = (error as { response?: { status?: number } }).response;
          if (response?.status === 401) {
            router.replace('/login');
          }
        }
      } finally {
        setLoading(false);
      }
    };

    fetchProposals();
  }, [router]);

  const handleGenerateProposal = async (e: React.FormEvent) => {
    e.preventDefault();
    setGenerating(true);

    try {
      const newProposal = await proposalService.generateProposal(formData);
      setProposals([newProposal, ...proposals]);
      setShowGenerateForm(false);
      setFormData({ job_description: '', provider: 'claude' });
    } catch (error: unknown) {
      console.error('Error generating proposal:', error);
      let errorMessage = 'Failed to generate proposal';
      
      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string } } }).response;
        errorMessage = response?.data?.message || 'Failed to generate proposal';
      }
      
      alert(errorMessage);
    } finally {
      setGenerating(false);
    }
  };

  const handleFeedback = async (proposalId: number, success: boolean) => {
    try {
      await proposalService.submitFeedback(proposalId, { success });
      // Update the local state to reflect the feedback
      setProposals(prevProposals => 
        prevProposals.map(proposal => 
          proposal.id === proposalId 
            ? { ...proposal, success_feedback: success }
            : proposal
        )
      );
    } catch (error: unknown) {
      console.error('Error submitting feedback:', error);
      alert('Failed to submit feedback');
    }
  };

  if (loading) {
    return <LoadingSpinner />;
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <Navigation />

      {/* Main Content */}
      <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Your Proposals</h1>
          <button
            onClick={() => setShowGenerateForm(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
          >
            Generate New Proposal
          </button>
        </div>

        {/* Generate Proposal Modal */}
        {showGenerateForm && (
          <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div className="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
              <div className="mt-3">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Generate New Proposal</h3>
                <form onSubmit={handleGenerateProposal}>
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Job Description
                    </label>
                    <textarea
                      value={formData.job_description}
                      onChange={(e) => setFormData({ ...formData, job_description: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                      rows={6}
                      placeholder="Paste the job description here..."
                      required
                    />
                  </div>
                  
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      AI Provider
                    </label>
                    <select
                      value={formData.provider}
                      onChange={(e) => setFormData({ ...formData, provider: e.target.value as 'claude' | 'openai' })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    >
                      <option value="claude">Claude</option>
                      <option value="openai">OpenAI</option>
                    </select>
                  </div>

                  <div className="flex justify-end space-x-3">
                    <button
                      type="button"
                      onClick={() => setShowGenerateForm(false)}
                      className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={generating}
                      className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md disabled:bg-indigo-300"
                    >
                      {generating ? 'Generating...' : 'Generate Proposal'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        )}

        {/* Proposals List */}
        <div className="space-y-4">
          {proposals.length > 0 ? (
            proposals.map((proposal) => (
              <div key={proposal.id} className="bg-white shadow rounded-lg p-6">
                <div className="flex justify-between items-start mb-4">
                  <div className="flex-1">
                    <div className="flex items-center space-x-2 mb-2">
                      <span className="text-sm text-gray-500">
                        Provider: {proposal.provider}
                      </span>
                      <span className="text-sm text-gray-400">•</span>
                      <span className="text-sm text-gray-500">
                        {new Date(proposal.created_at).toLocaleDateString()}
                      </span>
                    </div>
                  </div>
                  {proposal.success_feedback !== undefined && (
                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                      proposal.success_feedback 
                        ? 'bg-green-100 text-green-800' 
                        : 'bg-red-100 text-red-800'
                    }`}>
                      {proposal.success_feedback ? 'Success' : 'Failed'}
                    </span>
                  )}
                </div>

                <div className="mb-4">
                  <h4 className="text-sm font-medium text-gray-900 mb-2">Job Description:</h4>
                  <p className="text-sm text-gray-600 bg-gray-50 p-3 rounded">
                    {proposal.job_description}
                  </p>
                </div>

                <div className="mb-4">
                  <div className="flex justify-between items-center mb-2">
                    <h4 className="text-sm font-medium text-gray-900">Generated Proposal:</h4>
                    <button
                      onClick={() => {
                        navigator.clipboard.writeText(proposal.content);
                        alert('Proposal copied to clipboard!');
                      }}
                      className="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      Copy
                    </button>
                  </div>
                  <div className="text-sm text-gray-700 bg-blue-50 p-4 rounded whitespace-pre-wrap border">
                    {proposal.content}
                  </div>
                </div>

                {/* Additional Details */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 text-sm text-gray-600">
                  <div>
                    <span className="font-medium">Tokens Used:</span> {proposal.tokens_used}
                  </div>
                  <div>
                    <span className="font-medium">Model:</span> {proposal.model_used}
                  </div>
                  <div>
                    <span className="font-medium">Provider:</span> {proposal.provider || 'N/A'}
                  </div>
                </div>

                {/* Show matched projects if available */}
                {proposal.matched_projects && proposal.matched_projects.length > 0 && (
                  <div className="mb-4">
                    <h4 className="text-sm font-medium text-gray-900 mb-2">Matched Projects ({proposal.matched_projects.length}):</h4>
                    <div className="space-y-2">
                      {proposal.matched_projects.slice(0, 3).map((match, index) => (
                        <div key={match.project.id} className="bg-gray-50 p-2 rounded text-sm">
                          <div className="font-medium">{match.project.title}</div>
                          <div className="text-gray-600 text-xs">
                            Score: {match.score} | Industry: {match.project.industry}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {proposal.success_feedback === undefined && (
                  <div className="flex space-x-3">
                    <button
                      onClick={() => handleFeedback(proposal.id, true)}
                      className="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700"
                    >
                      Mark as Success
                    </button>
                    <button
                      onClick={() => handleFeedback(proposal.id, false)}
                      className="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700"
                    >
                      Mark as Failed
                    </button>
                  </div>
                )}
              </div>
            ))
          ) : (
            <div className="text-center py-12">
              <p className="text-gray-500 mb-4">No proposals generated yet</p>
              <button
                onClick={() => setShowGenerateForm(true)}
                className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
              >
                Generate Your First Proposal
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

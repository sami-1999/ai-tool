'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { authService } from '@/services/auth';
import { aiTestingService, TestResult, ComparisonResult } from '@/services/aiTesting';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import Navigation from '@/components/common/Navigation';

export default function AITestingPage() {
  const router = useRouter();
  const [user] = useState(authService.getUser());
  const [connectionResults, setConnectionResults] = useState<{
    openai?: TestResult;
    claude?: TestResult;
  }>({});
  const [proposalTestData, setProposalTestData] = useState('');
  const [proposalResults, setProposalResults] = useState<{
    openai?: TestResult;
    claude?: TestResult;
  }>({});
  const [comparisonPrompt, setComparisonPrompt] = useState('');
  const [comparisonResult, setComparisonResult] = useState<ComparisonResult | null>(null);
  const [loading, setLoading] = useState({
    openaiConnection: false,
    claudeConnection: false,
    openaiProposal: false,
    claudeProposal: false,
    comparison: false
  });

  if (!authService.isAuthenticated()) {
    router.replace('/login');
    return null;
  }

  const testConnection = async (provider: 'openai' | 'claude') => {
    setLoading(prev => ({ ...prev, [`${provider}Connection`]: true }));
    
    try {
      let result: TestResult;
      if (provider === 'openai') {
        result = await aiTestingService.testOpenAIConnection();
      } else {
        result = await aiTestingService.testClaudeConnection();
      }
      
      setConnectionResults(prev => ({ ...prev, [provider]: result }));
    } catch (error: unknown) {
      console.error(`Error testing ${provider} connection:`, error);
      const errorResult: TestResult = {
        success: false,
        message: error instanceof Error ? error.message : `Failed to test ${provider} connection`
      };
      setConnectionResults(prev => ({ ...prev, [provider]: errorResult }));
    } finally {
      setLoading(prev => ({ ...prev, [`${provider}Connection`]: false }));
    }
  };

  const testProposalGeneration = async (provider: 'openai' | 'claude') => {
    if (!proposalTestData.trim()) {
      alert('Please enter a job description for testing');
      return;
    }

    setLoading(prev => ({ ...prev, [`${provider}Proposal`]: true }));
    
    try {
      let result: TestResult;
      const data = { job_description: proposalTestData };
      
      if (provider === 'openai') {
        result = await aiTestingService.testOpenAIProposalGeneration(data);
      } else {
        result = await aiTestingService.testClaudeProposalGeneration(data);
      }
      
      setProposalResults(prev => ({ ...prev, [provider]: result }));
    } catch (error: unknown) {
      console.error(`Error testing ${provider} proposal generation:`, error);
      const errorResult: TestResult = {
        success: false,
        message: error instanceof Error ? error.message : `Failed to test ${provider} proposal generation`
      };
      setProposalResults(prev => ({ ...prev, [provider]: errorResult }));
    } finally {
      setLoading(prev => ({ ...prev, [`${provider}Proposal`]: false }));
    }
  };

  const compareProviders = async () => {
    if (!comparisonPrompt.trim()) {
      alert('Please enter a prompt for comparison');
      return;
    }

    setLoading(prev => ({ ...prev, comparison: true }));
    
    try {
      const result = await aiTestingService.compareProviders({ prompt: comparisonPrompt });
      setComparisonResult(result);
    } catch (error: unknown) {
      console.error('Error comparing providers:', error);
      alert(error instanceof Error ? error.message : 'Failed to compare providers');
    } finally {
      setLoading(prev => ({ ...prev, comparison: false }));
    }
  };

  const getStatusColor = (success: boolean) => {
    return success ? 'text-green-600' : 'text-red-600';
  };

  const getStatusBg = (success: boolean) => {
    return success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <Navigation user={user} />

      <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-6">AI Testing Dashboard</h1>

        {/* Connection Tests */}
        <div className="bg-white shadow rounded-lg mb-6">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-medium text-gray-900">Connection Tests</h2>
            <p className="text-sm text-gray-500">Test connectivity to AI providers</p>
          </div>
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* OpenAI Connection Test */}
              <div>
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-md font-medium text-gray-900">OpenAI</h3>
                  <button
                    onClick={() => testConnection('openai')}
                    disabled={loading.openaiConnection}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:bg-blue-300"
                  >
                    {loading.openaiConnection ? 'Testing...' : 'Test Connection'}
                  </button>
                </div>
                {connectionResults.openai && (
                  <div className={`p-4 rounded-md border ${getStatusBg(connectionResults.openai.success)}`}>
                    <p className={`text-sm font-medium ${getStatusColor(connectionResults.openai.success)}`}>
                      {connectionResults.openai.success ? '✓ Connected' : '✗ Connection Failed'}
                    </p>
                    <p className="text-sm text-gray-600 mt-1">
                      {connectionResults.openai.message}
                    </p>
                  </div>
                )}
              </div>

              {/* Claude Connection Test */}
              <div>
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-md font-medium text-gray-900">Claude</h3>
                  <button
                    onClick={() => testConnection('claude')}
                    disabled={loading.claudeConnection}
                    className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:bg-purple-300"
                  >
                    {loading.claudeConnection ? 'Testing...' : 'Test Connection'}
                  </button>
                </div>
                {connectionResults.claude && (
                  <div className={`p-4 rounded-md border ${getStatusBg(connectionResults.claude.success)}`}>
                    <p className={`text-sm font-medium ${getStatusColor(connectionResults.claude.success)}`}>
                      {connectionResults.claude.success ? '✓ Connected' : '✗ Connection Failed'}
                    </p>
                    <p className="text-sm text-gray-600 mt-1">
                      {connectionResults.claude.message}
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Proposal Generation Tests */}
        <div className="bg-white shadow rounded-lg mb-6">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-medium text-gray-900">Proposal Generation Tests</h2>
            <p className="text-sm text-gray-500">Test proposal generation capabilities</p>
          </div>
          <div className="p-6">
            <div className="mb-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Job Description for Testing
              </label>
              <textarea
                value={proposalTestData}
                onChange={(e) => setProposalTestData(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                rows={4}
                placeholder="Enter a job description to test proposal generation..."
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* OpenAI Proposal Test */}
              <div>
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-md font-medium text-gray-900">OpenAI Proposal</h3>
                  <button
                    onClick={() => testProposalGeneration('openai')}
                    disabled={loading.openaiProposal}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:bg-blue-300"
                  >
                    {loading.openaiProposal ? 'Testing...' : 'Test Proposal'}
                  </button>
                </div>
                {proposalResults.openai && (
                  <div className={`p-4 rounded-md border ${getStatusBg(proposalResults.openai.success)}`}>
                    <p className={`text-sm font-medium ${getStatusColor(proposalResults.openai.success)}`}>
                      {proposalResults.openai.success ? '✓ Generated Successfully' : '✗ Generation Failed'}
                    </p>
                    <p className="text-sm text-gray-600 mt-1">
                      {proposalResults.openai.message}
                    </p>
                  </div>
                )}
              </div>

              {/* Claude Proposal Test */}
              <div>
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-md font-medium text-gray-900">Claude Proposal</h3>
                  <button
                    onClick={() => testProposalGeneration('claude')}
                    disabled={loading.claudeProposal}
                    className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:bg-purple-300"
                  >
                    {loading.claudeProposal ? 'Testing...' : 'Test Proposal'}
                  </button>
                </div>
                {proposalResults.claude && (
                  <div className={`p-4 rounded-md border ${getStatusBg(proposalResults.claude.success)}`}>
                    <p className={`text-sm font-medium ${getStatusColor(proposalResults.claude.success)}`}>
                      {proposalResults.claude.success ? '✓ Generated Successfully' : '✗ Generation Failed'}
                    </p>
                    <p className="text-sm text-gray-600 mt-1">
                      {proposalResults.claude.message}
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Provider Comparison */}
        <div className="bg-white shadow rounded-lg">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-medium text-gray-900">Provider Comparison</h2>
            <p className="text-sm text-gray-500">Compare responses from both AI providers</p>
          </div>
          <div className="p-6">
            <div className="mb-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Comparison Prompt
              </label>
              <textarea
                value={comparisonPrompt}
                onChange={(e) => setComparisonPrompt(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                rows={3}
                placeholder="Enter a prompt to compare both providers..."
              />
              <button
                onClick={compareProviders}
                disabled={loading.comparison}
                className="mt-4 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-md text-sm font-medium disabled:bg-indigo-300"
              >
                {loading.comparison ? 'Comparing...' : 'Compare Providers'}
              </button>
            </div>

            {comparisonResult && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* OpenAI Results */}
                <div>
                  <h3 className="text-md font-medium text-gray-900 mb-3">OpenAI Results</h3>
                  <div className={`p-4 rounded-md border ${getStatusBg(comparisonResult.openai.success)}`}>
                    {comparisonResult.openai.success ? (
                      <>
                        <p className="text-sm text-gray-900 mb-2">{comparisonResult.openai.response}</p>
                        <div className="text-xs text-gray-500">
                          Tokens: {comparisonResult.openai.tokens_used} | 
                          Time: {comparisonResult.openai.response_time}ms
                        </div>
                      </>
                    ) : (
                      <p className="text-sm text-red-600">{comparisonResult.openai.error}</p>
                    )}
                  </div>
                </div>

                {/* Claude Results */}
                <div>
                  <h3 className="text-md font-medium text-gray-900 mb-3">Claude Results</h3>
                  <div className={`p-4 rounded-md border ${getStatusBg(comparisonResult.claude.success)}`}>
                    {comparisonResult.claude.success ? (
                      <>
                        <p className="text-sm text-gray-900 mb-2">{comparisonResult.claude.response}</p>
                        <div className="text-xs text-gray-500">
                          Tokens: {comparisonResult.claude.tokens_used} | 
                          Time: {comparisonResult.claude.response_time}ms
                        </div>
                      </>
                    ) : (
                      <p className="text-sm text-red-600">{comparisonResult.claude.error}</p>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

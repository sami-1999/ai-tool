'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { authService } from '@/services/auth';
import { skillService } from '@/services/skills';
import { projectService } from '@/services/projects';
import { proposalService } from '@/services/proposals';
import { User, Project } from '@/types';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import Navigation from '@/components/common/Navigation';

export default function Dashboard() {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [stats, setStats] = useState({
    skillsCount: 0,
    projectsCount: 0,
    proposalsCount: 0,
  });
  const [recentProjects, setRecentProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const initDashboard = async () => {
      // Add a small delay to ensure localStorage is properly set after login
      await new Promise(resolve => setTimeout(resolve, 100));
      
      const currentUser = authService.getUser();
      const token = authService.getToken();

      if (!currentUser || !token || !authService.isAuthenticated()) {
        router.replace('/login');
        return;
      }

      setUser(currentUser);

      try {
        // Fetch dashboard data
        const [skillsRes, projectsRes, proposalsRes] = await Promise.all([
          skillService.getSkills(),
          projectService.getProjects(),
          proposalService.getProposals(),
        ]);

        setStats({
          skillsCount: skillsRes.length || 0,
          projectsCount: projectsRes.length || 0,
          proposalsCount: proposalsRes.length || 0,
        });

        // Get recent projects (last 5)
        const sortedProjects = (projectsRes || []).sort(
          (a: Project, b: Project) => 
            new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
        );
        setRecentProjects(sortedProjects.slice(0, 5));
      } catch (error: unknown) {
        console.error('Error fetching dashboard data:', error);
        // If there's an auth error, redirect to login
        if (error && typeof error === 'object' && 'response' in error) {
          const response = (error as { response?: { status?: number } }).response;
          if (response?.status === 401) {
            alert('Session expired. Please log in again.');
            router.replace('/login');
          }
        }
      } finally {
        setLoading(false);
      }
    };

    // Only run on client side
    if (typeof window !== 'undefined') {
      initDashboard();
    }
  }, [router]);


  if (loading) {
    return <LoadingSpinner />;
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <Navigation user={user} />

      {/* Main Content */}
      <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div className="bg-white overflow-hidden shadow rounded-lg">
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                    <span className="text-white font-semibold">S</span>
                  </div>
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">
                      Skills
                    </dt>
                    <dd className="text-lg font-medium text-gray-900">
                      {stats.skillsCount}
                    </dd>
                  </dl>
                </div>
              </div>
              <div className="mt-3">
                <Link
                  href="/skills"
                  className="text-sm text-indigo-600 hover:text-indigo-500"
                >
                  Manage skills →
                </Link>
              </div>
            </div>
          </div>

          <div className="bg-white overflow-hidden shadow rounded-lg">
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                    <span className="text-white font-semibold">P</span>
                  </div>
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">
                      Projects
                    </dt>
                    <dd className="text-lg font-medium text-gray-900">
                      {stats.projectsCount}
                    </dd>
                  </dl>
                </div>
              </div>
              <div className="mt-3">
                <Link
                  href="/projects"
                  className="text-sm text-indigo-600 hover:text-indigo-500"
                >
                  Manage projects →
                </Link>
              </div>
            </div>
          </div>

          <div className="bg-white overflow-hidden shadow rounded-lg">
            <div className="p-5">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                    <span className="text-white font-semibold">R</span>
                  </div>
                </div>
                <div className="ml-5 w-0 flex-1">
                  <dl>
                    <dt className="text-sm font-medium text-gray-500 truncate">
                      Proposals
                    </dt>
                    <dd className="text-lg font-medium text-gray-900">
                      {stats.proposalsCount}
                    </dd>
                  </dl>
                </div>
              </div>
              <div className="mt-3">
                <Link
                  href="/proposals"
                  className="text-sm text-indigo-600 hover:text-indigo-500"
                >
                  View proposals →
                </Link>
              </div>
            </div>
          </div>
        </div>

        {/* Recent Projects */}
        <div className="bg-white shadow rounded-lg">
          <div className="px-4 py-5 sm:p-6">
            <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
              Recent Projects
            </h3>
            {recentProjects.length > 0 ? (
              <div className="space-y-3">
                {recentProjects.map((project) => (
                  <div
                    key={project.id}
                    className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50"
                  >
                    <div className="flex justify-between items-start">
                      <div>
                        <h4 className="text-sm font-medium text-gray-900">
                          {project.title}
                        </h4>
                        <p className="text-sm text-gray-500 mt-1">
                          {project.description.length > 100
                            ? `${project.description.substring(0, 100)}...`
                            : project.description}
                        </p>
                        <div className="flex items-center mt-2 text-xs text-gray-400">
                          <span>Industry: {project.industry || 'Not specified'}</span>
                          <span className="mx-2">•</span>
                          <span>Created: {new Date(project.created_at).toLocaleDateString()}</span>
                        </div>
                      </div>
                      <Link
                        href={`/projects/${project.id}`}
                        className="text-indigo-600 hover:text-indigo-900 text-sm"
                      >
                        View
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-4">
                <p className="text-gray-500">No projects yet</p>
                <Link
                  href="/projects/new"
                  className="mt-2 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
                >
                  Create your first project
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

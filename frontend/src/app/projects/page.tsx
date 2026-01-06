'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { authService } from '@/services/auth';
import { projectService } from '@/services/projects';
import { skillService } from '@/services/skills';
import { Project, ProjectRequest, Skill } from '@/types';

export default function ProjectsPage() {
  const router = useRouter();
  const [projects, setProjects] = useState<Project[]>([]);
  const [skills, setSkills] = useState<Skill[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingProject, setEditingProject] = useState<Project | null>(null);
  const [formData, setFormData] = useState<ProjectRequest>({
    title: '',
    description: '',
    industry: '',
    challenges: '',
    outcome: '',
    skills: []
  });

  useEffect(() => {
    const fetchData = async () => {
      if (!authService.isAuthenticated()) {
        router.replace('/login');
        return;
      }

      try {
        const [projectsData, skillsData] = await Promise.all([
          projectService.getProjects(),
          skillService.getSkills()
        ]);
        setProjects(projectsData);
        setSkills(skillsData.filter(skill => skill.status)); // Only active skills
      } catch (error: unknown) {
        console.error('Error fetching data:', error);
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

    fetchData();
  }, [router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      if (editingProject) {
        // Update existing project
        const updatedProject = await projectService.updateProject(editingProject.id, formData);
        setProjects(projects.map(project => 
          project.id === editingProject.id ? updatedProject : project
        ));
        setEditingProject(null);
      } else {
        // Create new project
        const newProject = await projectService.createProject(formData);
        setProjects([newProject, ...projects]);
      }
      
      setShowAddForm(false);
      setFormData({
        title: '',
        description: '',
        industry: '',
        challenges: '',
        outcome: '',
        skills: []
      });
    } catch (error: unknown) {
      console.error('Error saving project:', error);
      let errorMessage = 'Failed to save project';
      
      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string } } }).response;
        errorMessage = response?.data?.message || 'Failed to save project';
      }
      
      alert(errorMessage);
    }
  };

  const handleEdit = (project: Project) => {
    setEditingProject(project);
    setFormData({
      title: project.title,
      description: project.description,
      industry: project.industry || '',
      challenges: project.challenges || '',
      outcome: project.outcome || '',
      skills: project.skills?.map(skill => skill.id) || []
    });
    setShowAddForm(true);
  };

  const handleDelete = async (projectId: number) => {
    if (!confirm('Are you sure you want to delete this project?')) {
      return;
    }

    try {
      await projectService.deleteProject(projectId);
      setProjects(projects.filter(project => project.id !== projectId));
    } catch (error: unknown) {
      console.error('Error deleting project:', error);
      alert('Failed to delete project');
    }
  };

  const handleSkillToggle = (skillId: number) => {
    const currentSkills = formData.skills || [];
    if (currentSkills.includes(skillId)) {
      setFormData({
        ...formData,
        skills: currentSkills.filter(id => id !== skillId)
      });
    } else {
      setFormData({
        ...formData,
        skills: [...currentSkills, skillId]
      });
    }
  };

  const cancelEdit = () => {
    setEditingProject(null);
    setShowAddForm(false);
    setFormData({
      title: '',
      description: '',
      industry: '',
      challenges: '',
      outcome: '',
      skills: []
    });
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-indigo-600"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Navigation */}
      <nav className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center space-x-8">
              <Link href="/dashboard" className="text-xl font-semibold text-gray-900">
                AI Tool
              </Link>
              <Link href="/dashboard" className="text-gray-700 hover:text-gray-900">
                Dashboard
              </Link>
              <Link href="/skills" className="text-gray-700 hover:text-gray-900">
                Skills
              </Link>
              <Link href="/projects" className="text-indigo-600 font-medium">
                Projects
              </Link>
              <Link href="/proposals" className="text-gray-700 hover:text-gray-900">
                Proposals
              </Link>
            </div>
          </div>
        </div>
      </nav>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Your Projects</h1>
          <button
            onClick={() => setShowAddForm(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
          >
            Add New Project
          </button>
        </div>

        {/* Add/Edit Project Form */}
        {showAddForm && (
          <div className="bg-white shadow rounded-lg p-6 mb-6">
            <h3 className="text-lg font-medium text-gray-900 mb-4">
              {editingProject ? 'Edit Project' : 'Add New Project'}
            </h3>
            <form onSubmit={handleSubmit}>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Project Title *
                  </label>
                  <input
                    type="text"
                    value={formData.title}
                    onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="e.g., E-commerce Platform"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Industry
                  </label>
                  <input
                    type="text"
                    value={formData.industry || ''}
                    onChange={(e) => setFormData({ ...formData, industry: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="e.g., ecommerce, healthcare, fintech"
                  />
                </div>
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Description *
                </label>
                <textarea
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                  rows={4}
                  placeholder="Describe your project..."
                  required
                />
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Challenges
                </label>
                <textarea
                  value={formData.challenges || ''}
                  onChange={(e) => setFormData({ ...formData, challenges: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                  rows={3}
                  placeholder="What challenges did you face?"
                />
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Outcome
                </label>
                <textarea
                  value={formData.outcome || ''}
                  onChange={(e) => setFormData({ ...formData, outcome: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                  rows={3}
                  placeholder="What was the outcome/result?"
                />
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Skills Used
                </label>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                  {skills.map((skill) => (
                    <label key={skill.id} className="flex items-center">
                      <input
                        type="checkbox"
                        checked={formData.skills?.includes(skill.id) || false}
                        onChange={() => handleSkillToggle(skill.id)}
                        className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                      />
                      <span className="ml-2 text-sm text-gray-700">{skill.name}</span>
                    </label>
                  ))}
                </div>
                {skills.length === 0 && (
                  <p className="text-sm text-gray-500">
                    No skills available. <Link href="/skills" className="text-indigo-600 hover:text-indigo-500">Add some skills first</Link>.
                  </p>
                )}
              </div>

              <div className="flex justify-end space-x-3">
                <button
                  type="button"
                  onClick={cancelEdit}
                  className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md"
                >
                  {editingProject ? 'Update Project' : 'Add Project'}
                </button>
              </div>
            </form>
          </div>
        )}

        {/* Projects List */}
        <div className="space-y-4">
          {projects.length > 0 ? (
            projects.map((project) => (
              <div key={project.id} className="bg-white shadow rounded-lg p-6">
                <div className="flex justify-between items-start mb-4">
                  <div className="flex-1">
                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                      {project.title}
                    </h3>
                    <div className="flex items-center space-x-4 text-sm text-gray-500 mb-3">
                      {project.industry && (
                        <>
                          <span>Industry: {project.industry}</span>
                          <span>•</span>
                        </>
                      )}
                      <span>Created: {new Date(project.created_at).toLocaleDateString()}</span>
                    </div>
                  </div>
                  <div className="flex space-x-2">
                    <button
                      onClick={() => handleEdit(project)}
                      className="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
                    >
                      Edit
                    </button>
                    <button
                      onClick={() => handleDelete(project.id)}
                      className="text-red-600 hover:text-red-900 text-sm font-medium"
                    >
                      Delete
                    </button>
                  </div>
                </div>

                <div className="mb-4">
                  <p className="text-gray-700">{project.description}</p>
                </div>

                {project.challenges && (
                  <div className="mb-3">
                    <h4 className="text-sm font-medium text-gray-900 mb-1">Challenges:</h4>
                    <p className="text-sm text-gray-600">{project.challenges}</p>
                  </div>
                )}

                {project.outcome && (
                  <div className="mb-3">
                    <h4 className="text-sm font-medium text-gray-900 mb-1">Outcome:</h4>
                    <p className="text-sm text-gray-600">{project.outcome}</p>
                  </div>
                )}

                {project.skills && project.skills.length > 0 && (
                  <div className="flex flex-wrap gap-2">
                    {project.skills.map((skill) => (
                      <span
                        key={skill.id}
                        className="inline-flex px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"
                      >
                        {skill.name}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            ))
          ) : (
            <div className="bg-white shadow rounded-lg">
              <div className="text-center py-12">
                <div className="w-12 h-12 mx-auto mb-4 text-gray-400">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <h3 className="text-sm font-medium text-gray-900 mb-2">No projects yet</h3>
                <p className="text-gray-500 mb-4">Get started by adding your first project</p>
                <button
                  onClick={() => setShowAddForm(true)}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                >
                  Add Your First Project
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

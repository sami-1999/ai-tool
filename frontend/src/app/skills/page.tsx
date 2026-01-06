'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { authService } from '@/services/auth';
import { skillService } from '@/services/skills';
import { Skill, SkillRequest } from '@/types';

export default function SkillsPage() {
  const router = useRouter();
  const [skills, setSkills] = useState<Skill[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingSkill, setEditingSkill] = useState<Skill | null>(null);
  const [formData, setFormData] = useState<SkillRequest>({
    name: '',
    status: true
  });

  useEffect(() => {
    const fetchSkills = async () => {
      if (!authService.isAuthenticated()) {
        router.replace('/login');
        return;
      }

      try {
        const skillsData = await skillService.getSkills();
        setSkills(skillsData);
      } catch (error: unknown) {
        console.error('Error fetching skills:', error);
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

    fetchSkills();
  }, [router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      if (editingSkill) {
        // Update existing skill
        const updatedSkill = await skillService.updateSkill(editingSkill.id, formData);
        setSkills(skills.map(skill => 
          skill.id === editingSkill.id ? updatedSkill : skill
        ));
        setEditingSkill(null);
      } else {
        // Create new skill
        const newSkill = await skillService.createSkill(formData);
        setSkills([newSkill, ...skills]);
      }
      
      setShowAddForm(false);
      setFormData({ name: '', status: true });
    } catch (error: unknown) {
      console.error('Error saving skill:', error);
      let errorMessage = 'Failed to save skill';
      
      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string } } }).response;
        errorMessage = response?.data?.message || 'Failed to save skill';
      }
      
      alert(errorMessage);
    }
  };

  const handleEdit = (skill: Skill) => {
    setEditingSkill(skill);
    setFormData({
      name: skill.name,
      status: skill.status
    });
    setShowAddForm(true);
  };

  const handleDelete = async (skillId: number) => {
    if (!confirm('Are you sure you want to delete this skill?')) {
      return;
    }

    try {
      await skillService.deleteSkill(skillId);
      setSkills(skills.filter(skill => skill.id !== skillId));
    } catch (error: unknown) {
      console.error('Error deleting skill:', error);
      alert('Failed to delete skill');
    }
  };

  const toggleSkillStatus = async (skill: Skill) => {
    try {
      const updatedSkill = await skillService.updateSkill(skill.id, {
        name: skill.name,
        status: !skill.status
      });
      setSkills(skills.map(s => s.id === skill.id ? updatedSkill : s));
    } catch (error: unknown) {
      console.error('Error updating skill status:', error);
      alert('Failed to update skill status');
    }
  };

  const cancelEdit = () => {
    setEditingSkill(null);
    setShowAddForm(false);
    setFormData({ name: '', status: true });
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
              <Link href="/skills" className="text-indigo-600 font-medium">
                Skills
              </Link>
              <Link href="/projects" className="text-gray-700 hover:text-gray-900">
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
          <h1 className="text-2xl font-bold text-gray-900">Your Skills</h1>
          <button
            onClick={() => setShowAddForm(true)}
            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
          >
            Add New Skill
          </button>
        </div>

        {/* Add/Edit Skill Form */}
        {showAddForm && (
          <div className="bg-white shadow rounded-lg p-6 mb-6">
            <h3 className="text-lg font-medium text-gray-900 mb-4">
              {editingSkill ? 'Edit Skill' : 'Add New Skill'}
            </h3>
            <form onSubmit={handleSubmit}>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Skill Name
                  </label>
                  <input
                    type="text"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="e.g., Laravel, React, Python"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Status
                  </label>
                  <select
                    value={formData.status.toString()}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value === 'true' })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                  >
                    <option value="true">Active</option>
                    <option value="false">Inactive</option>
                  </select>
                </div>
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
                  {editingSkill ? 'Update Skill' : 'Add Skill'}
                </button>
              </div>
            </form>
          </div>
        )}

        {/* Skills List */}
        <div className="bg-white shadow rounded-lg">
          {skills.length > 0 ? (
            <div className="overflow-hidden">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Skill Name
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Created
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {skills.map((skill) => (
                    <tr key={skill.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm font-medium text-gray-900">
                          {skill.name}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <button
                          onClick={() => toggleSkillStatus(skill)}
                          className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full cursor-pointer ${
                            skill.status
                              ? 'bg-green-100 text-green-800 hover:bg-green-200'
                              : 'bg-red-100 text-red-800 hover:bg-red-200'
                          }`}
                        >
                          {skill.status ? 'Active' : 'Inactive'}
                        </button>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {new Date(skill.created_at).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button
                          onClick={() => handleEdit(skill)}
                          className="text-indigo-600 hover:text-indigo-900 mr-4"
                        >
                          Edit
                        </button>
                        <button
                          onClick={() => handleDelete(skill.id)}
                          className="text-red-600 hover:text-red-900"
                        >
                          Delete
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center py-12">
              <div className="w-12 h-12 mx-auto mb-4 text-gray-400">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <h3 className="text-sm font-medium text-gray-900 mb-2">No skills yet</h3>
              <p className="text-gray-500 mb-4">Get started by adding your first skill</p>
              <button
                onClick={() => setShowAddForm(true)}
                className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
              >
                Add Your First Skill
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

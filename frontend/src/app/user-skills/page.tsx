'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { authService } from '@/services/auth';
import { userSkillsService } from '@/services/userProfile';
import { skillService } from '@/services/skills';
import { UserSkill, Skill, UserSkillRequest } from '@/types';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import Navigation from '@/components/common/Navigation';

export default function UserSkillsPage() {
  const router = useRouter();
  const [user, setUser] = useState<{ name: string } | null>(null);
  const [userSkills, setUserSkills] = useState<UserSkill[]>([]);
  const [availableSkills, setAvailableSkills] = useState<Skill[]>([]);
  const [loading, setLoading] = useState(true);
  const [adding, setAdding] = useState(false);
  const [showAddForm, setShowAddForm] = useState(false);
  const [selectedSkillId, setSelectedSkillId] = useState<number>(0);
  const [selectedProficiency, setSelectedProficiency] = useState<'beginner' | 'intermediate' | 'expert'>('beginner');

  useEffect(() => {
    const fetchData = async () => {
      if (!authService.isAuthenticated()) {
        router.replace('/login');
        return;
      }

      const currentUser = authService.getUser();
      setUser(currentUser);

      try {
        const [userSkillsData, skillsData] = await Promise.all([
          userSkillsService.getUserSkills(),
          skillService.getSkills()
        ]);

        setUserSkills(userSkillsData);
        setAvailableSkills(skillsData.filter(skill => skill.status));
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

  const handleAddSkill = async (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedSkillId === 0) return;

    setAdding(true);
    try {
      const skillData: UserSkillRequest = {
        skill_id: selectedSkillId,
        proficiency_level: selectedProficiency
      };

      const newUserSkill = await userSkillsService.addUserSkill(skillData);
      setUserSkills([...userSkills, newUserSkill]);
      setShowAddForm(false);
      setSelectedSkillId(0);
      setSelectedProficiency('beginner');
    } catch (error: unknown) {
      console.error('Error adding skill:', error);
      let errorMessage = 'Failed to add skill';
      
      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string } } }).response;
        errorMessage = response?.data?.message || 'Failed to add skill';
      }
      
      alert(errorMessage);
    } finally {
      setAdding(false);
    }
  };

  const handleRemoveSkill = async (userSkillId: number, skillName: string) => {
    if (!confirm(`Are you sure you want to remove "${skillName}" from your skills?`)) {
      return;
    }

    try {
      await userSkillsService.removeUserSkill(userSkillId);
      setUserSkills(userSkills.filter(us => us.id !== userSkillId));
    } catch (error: unknown) {
      console.error('Error removing skill:', error);
      alert('Failed to remove skill');
    }
  };

  const getAvailableSkillsForAdd = () => {
    const userSkillIds = userSkills.map(us => us.skill_id);
    return availableSkills.filter(skill => !userSkillIds.includes(skill.id));
  };

  const getProficiencyColor = (level: string) => {
    switch (level) {
      case 'expert': return 'bg-green-100 text-green-800';
      case 'intermediate': return 'bg-yellow-100 text-yellow-800';
      case 'beginner': return 'bg-blue-100 text-blue-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  if (loading) {
    return <LoadingSpinner />;
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <Navigation user={user} />

      {/* Main Content */}
      <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold text-gray-900">My Skills</h1>
          <button
            onClick={() => setShowAddForm(true)}
            disabled={getAvailableSkillsForAdd().length === 0}
            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:bg-gray-400 disabled:cursor-not-allowed"
          >
            Add Skill
          </button>
        </div>

        {/* Add Skill Modal */}
        {showAddForm && (
          <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
              <div className="mt-3">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Add New Skill</h3>
                <form onSubmit={handleAddSkill}>
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Skill
                    </label>
                    <select
                      value={selectedSkillId}
                      onChange={(e) => setSelectedSkillId(parseInt(e.target.value))}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                      required
                    >
                      <option value={0}>Select a skill...</option>
                      {getAvailableSkillsForAdd().map((skill) => (
                        <option key={skill.id} value={skill.id}>
                          {skill.name}
                        </option>
                      ))}
                    </select>
                  </div>
                  
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Proficiency Level
                    </label>
                    <select
                      value={selectedProficiency}
                      onChange={(e) => setSelectedProficiency(e.target.value as 'beginner' | 'intermediate' | 'expert')}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    >
                      <option value="beginner">Beginner</option>
                      <option value="intermediate">Intermediate</option>
                      <option value="expert">Expert</option>
                    </select>
                  </div>

                  <div className="flex justify-end space-x-3">
                    <button
                      type="button"
                      onClick={() => {
                        setShowAddForm(false);
                        setSelectedSkillId(0);
                        setSelectedProficiency('beginner');
                      }}
                      className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={adding || selectedSkillId === 0}
                      className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md disabled:bg-indigo-300"
                    >
                      {adding ? 'Adding...' : 'Add Skill'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        )}

        {/* Skills List */}
        <div className="bg-white shadow rounded-lg">
          {userSkills.length > 0 ? (
            <div className="divide-y divide-gray-200">
              {userSkills.map((userSkill) => (
                <div key={userSkill.id} className="p-6 flex justify-between items-center">
                  <div className="flex items-center space-x-4">
                    <div>
                      <h3 className="text-lg font-medium text-gray-900">
                        {userSkill.skill?.name || 'Unknown Skill'}
                      </h3>
                      <p className="text-sm text-gray-500">
                        Added: {new Date(userSkill.created_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                  
                  <div className="flex items-center space-x-4">
                    <span className={`px-3 py-1 text-xs font-medium rounded-full ${getProficiencyColor(userSkill.proficiency_level)}`}>
                      {userSkill.proficiency_level.charAt(0).toUpperCase() + userSkill.proficiency_level.slice(1)}
                    </span>
                    <button
                      onClick={() => handleRemoveSkill(userSkill.id, userSkill.skill?.name || 'Unknown Skill')}
                      className="text-red-600 hover:text-red-800 text-sm font-medium"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <div className="text-gray-400 mb-4">
                <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
              </div>
              <h3 className="text-sm font-medium text-gray-900">No skills added yet</h3>
              <p className="text-sm text-gray-500 mb-4">
                Start by adding your professional skills and proficiency levels
              </p>
              {getAvailableSkillsForAdd().length > 0 ? (
                <button
                  onClick={() => setShowAddForm(true)}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                >
                  Add Your First Skill
                </button>
              ) : (
                <p className="text-sm text-gray-400">
                  No available skills to add. Contact admin to add more skills.
                </p>
              )}
            </div>
          )}
        </div>

        {/* Help Text */}
        <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 className="text-sm font-medium text-blue-900 mb-2">About Skill Levels:</h4>
          <ul className="text-sm text-blue-800 space-y-1">
            <li><strong>Beginner:</strong> You have basic knowledge and limited experience</li>
            <li><strong>Intermediate:</strong> You have solid understanding and practical experience</li>
            <li><strong>Expert:</strong> You have extensive experience and can mentor others</li>
          </ul>
        </div>
      </div>
    </div>
  );
}

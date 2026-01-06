'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { authService } from '@/services/auth';
import { userProfileService, userSkillsService } from '@/services/userProfile';
import { skillService } from '@/services/skills';
import { UserProfile, UserProfileRequest, User, UserSkill, Skill, UserSkillRequest } from '@/types';

export default function ProfilePage() {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editing, setEditing] = useState(false);
  const [formData, setFormData] = useState<UserProfileRequest>({
    title: '',
    years_experience: 0,
    default_tone: '',
    writing_style_notes: ''
  });

  // User Skills state
  const [userSkills, setUserSkills] = useState<UserSkill[]>([]);
  const [availableSkills, setAvailableSkills] = useState<Skill[]>([]);
  const [showAddSkillForm, setShowAddSkillForm] = useState(false);
  const [selectedSkillId, setSelectedSkillId] = useState<number>(0);
  const [selectedProficiency, setSelectedProficiency] = useState<'beginner' | 'intermediate' | 'expert'>('beginner');
  const [addingSkill, setAddingSkill] = useState(false);

  useEffect(() => {
    const fetchProfile = async () => {
      const currentUser = authService.getUser();
      if (!currentUser || !authService.isAuthenticated()) {
        router.replace('/login');
        return;
      }

      setUser(currentUser);

      try {
        const [profileData, userSkillsData, skillsData] = await Promise.all([
          userProfileService.getCurrentProfile(),
          userSkillsService.getUserSkills(),
          skillService.getSkills()
        ]);

        setProfile(profileData);
        setFormData({
          title: profileData.title || '',
          years_experience: profileData.years_experience || 0,
          default_tone: profileData.default_tone || '',
          writing_style_notes: profileData.writing_style_notes || ''
        });

        setUserSkills(userSkillsData);
        setAvailableSkills(skillsData.filter(skill => skill.status));
      } catch (error: unknown) {
        console.error('Error fetching profile:', error);
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

    fetchProfile();
  }, [router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user) return;
    
    setSaving(true);
    try {
      const updatedProfile = await userProfileService.updateCurrentProfile(formData);
      setProfile(updatedProfile);
      setEditing(false);
      alert('Profile updated successfully!');
    } catch (error: unknown) {
      console.error('Error updating profile:', error);
      let errorMessage = 'Failed to update profile';
      
      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string } } }).response;
        errorMessage = response?.data?.message || 'Failed to update profile';
      }
      
      alert(errorMessage);
    } finally {
      setSaving(false);
    }
  };

  const handleCancel = () => {
    if (profile) {
      setFormData({
        title: profile.title || '',
        years_experience: profile.years_experience || 0,
        default_tone: profile.default_tone || '',
        writing_style_notes: profile.writing_style_notes || ''
      });
    }
    setEditing(false);
  };

  // User Skills functions
  const handleAddSkill = async (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedSkillId === 0) return;

    setAddingSkill(true);
    try {
      const skillData: UserSkillRequest = {
        skill_id: selectedSkillId,
        proficiency_level: selectedProficiency
      };

      const newUserSkill = await userSkillsService.addUserSkill(skillData);
      setUserSkills([...userSkills, newUserSkill]);
      setShowAddSkillForm(false);
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
      setAddingSkill(false);
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
              <Link href="/projects" className="text-gray-700 hover:text-gray-900">
                Projects
              </Link>
              <Link href="/proposals" className="text-gray-700 hover:text-gray-900">
                Proposals
              </Link>
              <Link href="/profile" className="text-indigo-600 font-medium">
                Profile
              </Link>
            </div>
            <div className="flex items-center">
              <span className="text-gray-700">{user?.name}</span>
            </div>
          </div>
        </div>
      </nav>

      {/* Main Content */}
      <div className="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="bg-white shadow rounded-lg">
          <div className="px-6 py-4 border-b border-gray-200">
            <div className="flex justify-between items-center">
              <h1 className="text-2xl font-bold text-gray-900">User Profile</h1>
              {!editing ? (
                <button
                  onClick={() => setEditing(true)}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                >
                  Edit Profile
                </button>
              ) : (
                <div className="space-x-2">
                  <button
                    onClick={handleCancel}
                    className="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium"
                  >
                    Cancel
                  </button>
                </div>
              )}
            </div>
          </div>

          <div className="p-6">
            {/* Basic User Info */}
            <div className="mb-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700">Name</label>
                  <div className="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded">
                    {user?.name}
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Email</label>
                  <div className="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded">
                    {user?.email}
                  </div>
                </div>
              </div>
            </div>

            {/* Profile Form */}
            <form onSubmit={handleSubmit}>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Professional Profile</h3>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Professional Title
                  </label>
                  {editing ? (
                    <input
                      type="text"
                      value={formData.title || ''}
                      onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="e.g., Full Stack Laravel Developer"
                    />
                  ) : (
                    <div className="text-sm text-gray-900 bg-gray-50 p-3 rounded">
                      {profile?.title || 'Not specified'}
                    </div>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Years of Experience
                  </label>
                  {editing ? (
                    <input
                      type="number"
                      min="0"
                      max="50"
                      value={formData.years_experience || 0}
                      onChange={(e) => setFormData({ ...formData, years_experience: parseInt(e.target.value) || 0 })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    />
                  ) : (
                    <div className="text-sm text-gray-900 bg-gray-50 p-3 rounded">
                      {profile?.years_experience || 0} years
                    </div>
                  )}
                </div>
              </div>

              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Default Writing Tone
                </label>
                {editing ? (
                  <select
                    value={formData.default_tone || ''}
                    onChange={(e) => setFormData({ ...formData, default_tone: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                  >
                    <option value="">Select tone...</option>
                    <option value="professional">Professional</option>
                    <option value="friendly">Friendly</option>
                    <option value="casual">Casual</option>
                    <option value="formal">Formal</option>
                    <option value="enthusiastic">Enthusiastic</option>
                  </select>
                ) : (
                  <div className="text-sm text-gray-900 bg-gray-50 p-3 rounded">
                    {profile?.default_tone || 'Not specified'}
                  </div>
                )}
              </div>

              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Writing Style Notes
                </label>
                {editing ? (
                  <textarea
                    value={formData.writing_style_notes || ''}
                    onChange={(e) => setFormData({ ...formData, writing_style_notes: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    rows={4}
                    placeholder="e.g., Concise, human-like, always ask 1 relevant question"
                  />
                ) : (
                  <div className="text-sm text-gray-900 bg-gray-50 p-3 rounded min-h-[100px]">
                    {profile?.writing_style_notes || 'Not specified'}
                  </div>
                )}
              </div>

              {editing && (
                <div className="flex justify-end space-x-3">
                  <button
                    type="button"
                    onClick={handleCancel}
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={saving}
                    className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md disabled:bg-indigo-300"
                  >
                    {saving ? 'Saving...' : 'Save Changes'}
                  </button>
                </div>
              )}
            </form>

            {/* Account Info */}
            <div className="mt-8 pt-6 border-t border-gray-200">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                <div>
                  <span className="font-medium">Account Created:</span> {' '}
                  {user?.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}
                </div>
                <div>
                  <span className="font-medium">Profile Updated:</span> {' '}
                  {profile?.updated_at ? new Date(profile.updated_at).toLocaleDateString() : 'N/A'}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

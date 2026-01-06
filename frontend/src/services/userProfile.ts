// User Profile service (v2 - current API)
import { apiClient, API_ENDPOINTS } from '@/lib/api';
import { UserProfile, UserProfileRequest, UserSkill, UserSkillRequest } from '@/types';

export const userProfileService = {
  // Profile management (v2 - current)
  async getCurrentProfile(): Promise<UserProfile> {
    const response = await apiClient.get<UserProfile>(API_ENDPOINTS.PROFILE);
    return response.data;
  },

  async updateCurrentProfile(profileData: UserProfileRequest): Promise<UserProfile> {
    const response = await apiClient.post<UserProfile>(API_ENDPOINTS.PROFILE, profileData);
    return response.data;
  },

  // Legacy profile methods (deprecated but kept for backward compatibility)
  async getProfile(id: number): Promise<UserProfile> {
    const response = await apiClient.get<UserProfile>(API_ENDPOINTS.LEGACY_PROFILE(id));
    return response.data;
  },

  async updateProfile(id: number, profileData: UserProfileRequest): Promise<UserProfile> {
    const response = await apiClient.put<UserProfile>(API_ENDPOINTS.LEGACY_PROFILE(id), profileData);
    return response.data;
  }
};

// User Skills service
export const userSkillsService = {
  async getUserSkills(): Promise<UserSkill[]> {
    const response = await apiClient.get<UserSkill[]>(API_ENDPOINTS.USER_SKILLS);
    return response.data;
  },

  async addUserSkill(skillData: UserSkillRequest): Promise<UserSkill> {
    const response = await apiClient.post<UserSkill>(API_ENDPOINTS.USER_SKILLS, skillData);
    return response.data;
  },

  async addMultipleUserSkills(skillsData: { skills: UserSkillRequest[] }): Promise<UserSkill[]> {
    const response = await apiClient.post<UserSkill[]>(API_ENDPOINTS.USER_SKILLS, skillsData);
    return response.data;
  },

  async removeUserSkill(skillId: number): Promise<void> {
    await apiClient.delete(API_ENDPOINTS.USER_SKILL(skillId));
  }
};

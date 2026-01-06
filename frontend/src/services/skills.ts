// Skills service
import { BaseService } from './base';
import { Skill, SkillRequest } from '@/types';
import { API_ENDPOINTS } from '@/lib/api';

class SkillService extends BaseService<Skill, SkillRequest> {
  constructor() {
    super(API_ENDPOINTS.SKILLS, API_ENDPOINTS.SKILL);
  }

  // Alias methods to maintain backward compatibility
  async getSkills(): Promise<Skill[]> {
    return this.getAll();
  }

  async getSkill(id: number): Promise<Skill> {
    return this.getById(id);
  }

  async createSkill(skillData: SkillRequest): Promise<Skill> {
    return this.create(skillData);
  }

  async updateSkill(id: number, skillData: SkillRequest): Promise<Skill> {
    return this.update(id, skillData);
  }

  async deleteSkill(id: number): Promise<void> {
    return this.delete(id);
  }
}

export const skillService = new SkillService();

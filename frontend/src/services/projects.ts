// Projects service
import { BaseService } from './base';
import { Project, ProjectRequest } from '@/types';
import { API_ENDPOINTS } from '@/lib/api';

class ProjectService extends BaseService<Project, ProjectRequest> {
  constructor() {
    super(API_ENDPOINTS.PROJECTS, API_ENDPOINTS.PROJECT);
  }

  // Alias methods to maintain backward compatibility
  async getProjects(): Promise<Project[]> {
    return this.getAll();
  }

  async getProject(id: number): Promise<Project> {
    return this.getById(id);
  }

  async createProject(projectData: ProjectRequest): Promise<Project> {
    return this.create(projectData);
  }

  async updateProject(id: number, projectData: ProjectRequest): Promise<Project> {
    return this.update(id, projectData);
  }

  async deleteProject(id: number): Promise<void> {
    return this.delete(id);
  }
}

export const projectService = new ProjectService();

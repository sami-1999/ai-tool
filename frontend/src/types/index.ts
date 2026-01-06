// Type definitions for the application

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string;
  created_at: string;
  updated_at: string;
}

export interface UserProfile {
  id: number;
  user_id: number;
  title?: string;
  years_experience?: number;
  default_tone?: string;
  writing_style_notes?: string;
  created_at: string;
  updated_at: string;
  user?: User;
}

export interface Skill {
  id: number;
  user_id: number;
  name: string;
  status: boolean;
  created_at: string;
  updated_at: string;
}

export interface Project {
  id: number;
  user_id: number;
  title: string;
  description: string;
  industry?: string;
  challenges?: string;
  outcome?: string;
  created_at: string;
  updated_at: string;
  skills?: Skill[];
}

export interface Proposal {
  id: number;
  proposal_request_id: number;
  content: string;
  tokens_used: number;
  model_used: string;
  created_at: string;
  updated_at: string;
  user_id?: number;
  user?: User;
  success_feedback?: boolean;
}

export interface JobAnalysis {
  job_type: string;
  skills: string[];
  industry: string;
  description: string;
}

export interface MatchedProject {
  project: Project;
  score: number;
  skill_matches: number;
}

export interface ProposalGenerationResponse {
  proposal: Proposal;
  job_analysis: JobAnalysis;
  matched_projects: MatchedProject[];
  tokens_used: number;
  provider_used: string;
}

export interface ProposalFeedback {
  success: boolean;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface AuthResponse {
  user: User;
  token: string;
}

export interface SkillRequest {
  name: string;
  status: boolean;
}

export interface ProjectRequest {
  title: string;
  description: string;
  industry?: string;
  challenges?: string;
  outcome?: string;
  skills?: number[];
}

export interface ProposalGenerationRequest {
  job_description: string;
  provider?: 'claude' | 'openai';
}

export interface UserProfileRequest {
  title?: string;
  years_experience?: number;
  default_tone?: string;
  writing_style_notes?: string;
}

export interface UserSkill {
  id: number;
  user_id: number;
  skill_id: number;
  proficiency_level: 'beginner' | 'intermediate' | 'expert';
  created_at: string;
  updated_at: string;
  skill?: Skill;
}

export interface UserSkillRequest {
  skill_id: number;
  proficiency_level: 'beginner' | 'intermediate' | 'expert';
}

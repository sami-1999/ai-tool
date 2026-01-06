// Generic CRUD service base
import { apiClient, API_ENDPOINTS } from '@/lib/api';

export class BaseService<T, TRequest = Partial<T>> {
  constructor(
    private listEndpoint: string,
    private itemEndpoint: (id: number) => string
  ) {}

  async getAll(): Promise<T[]> {
    const response = await apiClient.get<T[]>(this.listEndpoint);
    return response.data;
  }

  async getById(id: number): Promise<T> {
    const response = await apiClient.get<T>(this.itemEndpoint(id));
    return response.data;
  }

  async create(data: TRequest): Promise<T> {
    const response = await apiClient.post<T>(this.listEndpoint, data);
    return response.data;
  }

  async update(id: number, data: TRequest): Promise<T> {
    const response = await apiClient.put<T>(this.itemEndpoint(id), data);
    return response.data;
  }

  async delete(id: number): Promise<void> {
    await apiClient.delete(this.itemEndpoint(id));
  }
}

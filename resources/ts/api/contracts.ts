import api from "./axios";

export interface Contract {
  id: number;
  contract_number: string;
  contract: string;
  status: "Active" | "Expired" | "Pending";
  start_date: string;
  counterparty: string;
  is_cancelled: boolean;
  template_id: number;
  contract_template?: {
    id: number;
    name: string;
    type: "Employment" | "Service" | "Partnership" | "Lease" | "NDA";
    cover_text: string | null;
    content: any;
    created_at: string;
    updated_at: string;
  };
}

export interface ContractTemplate {
  id: number;
  name: string;
  type: "Employment" | "Service" | "Partnership" | "Lease" | "NDA";
  cover_text: string | null;
  content: any;
  created_at: string;
  updated_at: string;
}

export interface ContractCreateData {
  template_id: number;
  status: "Active" | "Expired" | "Pending";
  start_date: string;
  counterparty: string;
}

export interface ContractUpdateData {
  template_id?: number;
  status?: "Active" | "Expired" | "Pending";
  start_date?: string;
  counterparty?: string;
  is_cancelled?: boolean;
}

const ContractApi = {
  // Get all contracts with optional filter
  getAll: async (onlyCancelled?: boolean) => {
    try {
      const params = onlyCancelled ? { 'only-cancelled': 'true' } : {};
      const response = await api.get<{ success: boolean; data: Contract[] }>("/contracts", { params });
      return response.data;
    } catch (error) {
      console.error('API getAll error:', error);
      throw error;
    }
  },

  // Get single contract
  getById: async (id: number) => {
    try {
      const response = await api.get<{ success: boolean; data: Contract }>(`/contracts/${id}`);
      return response.data;
    } catch (error) {
      console.error('API getById error:', error);
      throw error;
    }
  },

  // Create contract
  create: async (data: ContractCreateData) => {
    try {
      const response = await api.post<{ success: boolean; data: Contract }>("/contracts", data);
      return response.data;
    } catch (error) {
      console.error('API create error:', error);
      throw error;
    }
  },

  // Update contract
  update: async (id: number, data: ContractUpdateData) => {
    try {
      const response = await api.put<{ success: boolean; data: Contract }>(`/contracts/${id}`, data);
      return response.data;
    } catch (error) {
      console.error('API update error:', error);
      throw error;
    }
  },

  // Cancel contract
  cancel: async (id: number) => {
    try {
      const response = await api.put<{ success: boolean; data: Contract }>(`/contracts/${id}/cancel`, {
        is_cancelled: true
      });
      return response.data;
    } catch (error) {
      console.error('API cancel error:', error);
      throw error;
    }
  },

  // Delete contract
  delete: async (id: number) => {
    try {
      const response = await api.delete<{ success: boolean; message: string }>(`/contracts/${id}`);
      return response.data;
    } catch (error) {
      console.error('API delete error:', error);
      throw error;
    }
  },

  // Get all contract templates
  getTemplates: async () => {
    try {
      const response = await api.get<{ success: boolean; data: ContractTemplate[] }>("/contract-templates");
      return response.data;
    } catch (error) {
      console.error('API getTemplates error:', error);
      throw error;
    }
  },

  // Get single contract template
  getTemplateById: async (id: number) => {
    try {
      const response = await api.get<{ success: boolean; data: ContractTemplate }>(`/contract-templates/${id}`);
      return response.data;
    } catch (error) {
      console.error('API getTemplateById error:', error);
      throw error;
    }
  },
};

export default ContractApi;

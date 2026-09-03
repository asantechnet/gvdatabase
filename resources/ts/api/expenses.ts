import api from "./axios";
import type { Currency } from "./currency";
import type { Company } from './company';
import type { Project } from './project';

export interface Expense {
  id: number;
  customer_id: number | null;
  company_id: number | null;
  company?: Pick<Company, 'id' | 'legal_name' | 'trade_name'> | { id: number; name: string } | null;
  project_id: number | null;
  project?: Pick<Project, 'id' | 'name'> | null;
  currency_id: number;
  type: string;
  description: string;
  amount: number;
  quantity: number;
  currency: Pick<Currency, "id" | "code" | "symbol" | "rate" | "decimalPlaces"> | null;
  cnv_curr_shrt: string; base_curr_shrt: string; rate: number; base_amount: number;
  currentBaseAmount: number | null;
  currentBaseCurrency: Pick<Currency, "id" | "code" | "symbol" | "decimalPlaces"> | null;
  date: string;
  recorded_by?: number | null;
  recordedBy?: { id: number; name: string } | null;
  created_at?: string;
  updated_at?: string;
}

export interface ExpenseCreateData {
  customer_id?: number | null;
  company_id?: number | null;
  project_id?: number | null;
  currency_id: number;
  type: string;
  description: string;
  amount: number;
  quantity: number;
  rate: number;
  date: string;
}

export interface ExpenseUpdateData {
  customer_id?: number | null;
  company_id?: number | null;
  project_id?: number | null;
  currency_id?: number;
  type?: string;
  description?: string;
  amount?: number;
  quantity?: number;
  rate?: number;
  date?: string;
}

export interface ExpenseParams {
  search?: string;
  type?: string;
  currency_id?: number;
  company_id?: number;
  project_id?: number;
}

export const getExpenses = async (
  params?: ExpenseParams,
): Promise<Expense[]> => {
  try {
    const response = await api.get("/expenses", { params });
    // Handle nested data structure where actual data is in response.data.data
    return Array.isArray(response.data.data) ? response.data.data : [];
  } catch (error) {
    console.error("Error fetching expenses:", error);
    throw error;
  }
};

export const getExpense = async (id: number): Promise<Expense> => {
  try {
    const response = await api.get(`/expenses/${id}`);
    // Handle nested data structure
    return response.data.data;
  } catch (error) {
    console.error(`Error fetching expense ${id}:`, error);
    throw error;
  }
};

export const createExpense = async (
  data: ExpenseCreateData,
): Promise<Expense> => {
  try {
    const response = await api.post("/expenses", data);
    // Handle nested data structure
    return response.data.data;
  } catch (error) {
    console.error("Error creating expense:", error);
    throw error;
  }
};

export const updateExpense = async (
  id: number,
  data: ExpenseUpdateData,
): Promise<Expense> => {
  try {
    const response = await api.put(`/expenses/${id}`, data);
    // Handle nested data structure
    return response.data.data;
  } catch (error) {
    console.error(`Error updating expense ${id}:`, error);
    throw error;
  }
};

export const deleteExpense = async (id: number): Promise<void> => {
  try {
    await api.delete(`/expenses/${id}`);
  } catch (error) {
    console.error(`Error deleting expense ${id}:`, error);
    throw error;
  }
};

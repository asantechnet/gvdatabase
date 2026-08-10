import api from "./axios";

export interface Currency {
  id: number;
  name: string;
  code: string;
  symbol: string;
  rateToBase: number;
  isBase: boolean;
  isActive: boolean;
  decimalPlaces: number;
  updatedAt: string;
}

export type CurrencyForm = Omit<Currency, "id" | "updatedAt" | "isBase"> & {
  isBase?: boolean;
};

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
}

const CurrencyApi = {
  // Get all currencies
  getAll: async (params?: {
    page?: number;
    perPage?: number;
  }): Promise<ApiResponse<Currency[]>> => {
    const response = await api.get("/currencies", { params });
    return {
      success: true,
      data: response.data.data,
    };
  },

  // Get single currency
  getById: async (id: number): Promise<ApiResponse<Currency>> => {
    const response = await api.get(`/currencies/${id}`);
    return {
      success: true,
      data: response.data.data,
    };
  },

  // Create currency
  create: async (
    data: CurrencyForm,
  ): Promise<ApiResponse<Currency>> => {
    // Ensure rateToBase is always included and is a number
    const rateToBase = data.isBase ? 1.0 : Number(data.rateToBase || 1.0);
    const payload = {
      name: data.name,
      code: data.code.toUpperCase(),
      symbol: data.symbol,
      rate_to_base: rateToBase,
      is_active: data.isActive,
      decimal_places: data.decimalPlaces,
      is_base: data.isBase,
    };

    const response = await api.post("/currencies", payload);
    return {
      success: true,
      data: response.data.data,
    };
  },

  // Update currency
  update: async (
    id: number,
    data: Partial<CurrencyForm>,
  ): Promise<ApiResponse<Currency>> => {
    const payload: Record<string, any> = {};
    if (data.name !== undefined) payload.name = data.name;
    if (data.code !== undefined) payload.code = data.code.toUpperCase();
    if (data.symbol !== undefined) payload.symbol = data.symbol;
    if (data.rateToBase !== undefined) {
      payload.rate_to_base = data.isBase ? 1.0 : Number(data.rateToBase);
    }
    if (data.isActive !== undefined) payload.is_active = data.isActive;
    if (data.decimalPlaces !== undefined)
      payload.decimal_places = data.decimalPlaces;
    if (data.isBase !== undefined) payload.is_base = data.isBase;

    const response = await api.put(`/currencies/${id}`, payload);
    return {
      success: true,
      data: response.data.data,
    };
  },

  // Delete currency
  delete: async (id: number): Promise<ApiResponse<null>> => {
    const response = await api.delete(`/currencies/${id}`);
    return {
      success: response.data.success,
      data: null,
      message: response.data.message,
    };
  },
};

export default CurrencyApi;
export type { CurrencyForm, Currency, ApiResponse };

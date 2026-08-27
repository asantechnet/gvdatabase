import api from "./axios";

// Types matching the frontend requirements
export type EligibilityReason = 'Top Performer' | 'Project Milestone' | 'Client Praise' | 'Work Anniversary' | 'Manager Nomination';
export type BonusType = 'Performance' | 'Spot Bonus' | 'Project Completion' | 'Retention';

export interface SalaryConversionSnapshot {
    sourceAmount: number;
    sourceCurrencyCode: string;
    sourceRateToBase: number;
    baseAmount: number;
    salaryCurrencyCode: string;
    salaryRateToBase: number;
    convertedAmount: number;
    formula: string;
    snapshotSource: string;
}

export interface WorthyEmployee {
    id: string;
    employeeId: string;
    employeeName: string;
    employeeEmail: string;
    department: string | null;
    performanceScore: number;
    eligibilityReason: EligibilityReason;
    achievementDetails: string;
    suggestedAmount: number;
    selectedAmount: number;
    selectedType: BonusType;
    isGranted: boolean;
    grantedAt: string | null;
    grantedBy: number | null;
    grantedAmount: number | null;
    isAutomatic: boolean;
    calculationKey: string | null;
    calculationScore: number | null;
    calculationInputs: { tasks?: Array<Record<string, unknown>>; formula?: string } | null;
    ruleSnapshot: { id: number; version: number; rules: Record<string, unknown> } | null;
    calculatedAt: string | null;
    salaryConversion?: SalaryConversionSnapshot;
    bonusMonth: string; // Format: YYYY-MM
}

export interface CreateNominationData {
    employeeId: number;
    performanceScore: number;
    eligibilityReason: EligibilityReason;
    achievementDetails: string;
    suggestedAmount: number;
    selectedType: BonusType;
    selectedAmount?: number;
    bonusMonth?: string; // Format: YYYY-MM
}

export interface UpdateEmployeeData {
    selectedAmount?: number;
    selectedType?: BonusType;
    isGranted?: boolean;
}

// Bonus Eligibility API endpoints
const BonusListApi = {
    // Get all worthy employees
    getAll: async () => {
        try {
            const response = await api.get<{ data: WorthyEmployee[] }>("/worthy-employees");
            return response;
        } catch (error) {
            console.error('API getAll error:', error);
            throw error;
        }
    },

    // Create a new nomination
    create: async (data: CreateNominationData) => {
        try {
            const response = await api.post<{ data: WorthyEmployee }>("/worthy-employees", data);
            return response;
        } catch (error) {
            console.error('API create error:', error);
            throw error;
        }
    },

    // Update an employee's record
    update: async (id: string, data: UpdateEmployeeData) => {
        try {
            const response = await api.patch<{ data: WorthyEmployee }>(`/worthy-employees/${id}`, data);
            return response;
        } catch (error) {
            console.error('API update error:', error);
            throw error;
        }
    },

    // Delete (dismiss) an employee
    delete: async (id: string) => {
        try {
            const response = await api.delete<{ message: string }>(`/worthy-employees/${id}`);
            return response;
        } catch (error) {
            console.error('API delete error:', error);
            throw error;
        }
    },
};

export default BonusListApi;

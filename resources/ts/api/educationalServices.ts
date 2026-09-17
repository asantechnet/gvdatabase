import api from './axios'
export type EducationalServiceType = 'scholarships' | 'test-offers'
export interface EducationalService { id:number; customer_id:number; applicant_id?:number|null; employee_id:number; currency_id:number; package_type_id?:number|null; date:string; fee:number; status:string }
export default { list: async(type:EducationalServiceType)=>(await api.get(`/educational-services/${type}`)).data.data as EducationalService[], create: async(type:EducationalServiceType,data:Partial<EducationalService>)=>(await api.post(`/educational-services/${type}`,data)).data.data as EducationalService }

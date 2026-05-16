export interface ApiSuccess<T> {
  success: true;
  data: T;
}

export interface ApiErrorBody {
  success: false;
  error: string;
}

export interface HealthData {
  status: string;
  time: string;
  app: string;
  vendor: string;
  vendor_url: string;
  database: string;
}

export interface LoginData {
  access_token: string;
  role: string;
}

export interface UserProfile {
  id: number;
  email: string;
  display_name: string | null;
  role: string;
  created_at: string;
  first_name: string | null;
  last_name: string | null;
  is_email_verified: number;
}

export interface Product {
  id: number;
  name: string;
  type: string | null;
  description: string | null;
  size: string | null;
  cost: number | null;
  image_url: string | null;
  state: string | null;
  created_at: string;
  updated_at: string;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next: boolean;
  has_prev: boolean;
  next_page: number | null;
  prev_page: number | null;
}

export interface PaginatedProducts {
  data: Product[];
  pagination: PaginationMeta;
}

export interface Store {
  id: number;
  name: string;
  description: string | null;
  currency_symbol: string | null;
  created_at: string;
  updated_at: string;
}

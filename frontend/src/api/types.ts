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

export interface AdminUser {
  id: number;
  email: string;
  display_name: string | null;
  role: string;
  created_at: string;
  last_login: string | null;
  first_name: string | null;
  last_name: string | null;
}

export interface InventoryRow {
  id: number;
  product_id: number;
  store_id: number;
  quantity: number;
  min_quantity: number | null;
  product_name: string | null;
  store_name: string | null;
  product_cost: number | null;
  last_restocked: string | null;
}

export interface PaginatedInventory {
  data: InventoryRow[];
  pagination: PaginationMeta;
}

export interface OrderSummary {
  id: number;
  order_number: string | null;
  order_date: string | null;
  fulfillment_status: string | null;
  total: number | null;
  user_email?: string | null;
  customer_name_display?: string | null;
}

export interface PaginatedOrders {
  data: OrderSummary[];
  pagination: PaginationMeta;
}

export interface BookkeepingSummary {
  total_income: number;
  total_expenses: number;
  profit: number;
  expenses_by_category: { category: string | null; total: number }[];
}

export interface BookkeepingIncome {
  id: number;
  amount: number;
  category: string | null;
  payment_date: string | null;
  description: string | null;
  order_number?: string | null;
}

export interface BookkeepingExpense {
  id: number;
  amount: number;
  category: string | null;
  vendor: string | null;
  expense_date: string | null;
  description: string | null;
}

export interface AdminClient {
  id: number;
  email: string | null;
  display_name: string | null;
  first_name: string | null;
  last_name: string | null;
  created_at: string;
  order_count: number;
  total_spent: number;
  last_order_date: string | null;
  referral_code?: string | null;
}

export interface AdminReturn {
  id: number;
  return_number: string | null;
  status: string | null;
  order_id: number;
  order_number?: string | null;
  user_email?: string | null;
  refund_amount: number | null;
  created_at: string;
}

export interface AdminCoupon {
  id: number;
  code: string;
  discount_type: string;
  discount_value: number;
  min_purchase_amount: number | null;
  max_uses: number | null;
  times_used: number | null;
  valid_from: string | null;
  valid_until: string | null;
  is_active: number;
  description: string | null;
}

export interface AdminSetting {
  id: number;
  key: string;
  value: string | null;
  type: string;
  group_name: string | null;
  is_public: number;
  label: string | null;
}

export type SettingsGrouped = Record<string, AdminSetting[]>;

export interface AdminEmployee {
  id: number;
  email: string;
  display_name: string | null;
  role: string;
  created_at: string;
}

export interface AdminTestimonial {
  id: number;
  customer_name: string | null;
  customer_email: string | null;
  content: string | null;
  rating: number | null;
  published: number;
  created_at: string;
}

export interface PaginatedTestimonials {
  data: AdminTestimonial[];
  pagination: PaginationMeta;
}

export interface AdminReview {
  id: number;
  product_id: number;
  product_name: string | null;
  user_email: string | null;
  rating: number;
  review_text: string | null;
  published: number;
  created_at: string;
}

export interface AdminOrderDetail extends OrderSummary {
  subtotal: number | null;
  shipping_cost: number | null;
  tracking_number: string | null;
  shipping_carrier: string | null;
  items?: { product_name?: string; quantity?: number; price?: number }[];
}

export type FulfillmentStatus = 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';

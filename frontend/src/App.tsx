import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { AdminLayout } from './components/AdminLayout';
import { Layout } from './components/Layout';
import { RequireRole } from './components/RequireRole';
import { HomePage } from './pages/HomePage';
import { ProductsPage } from './pages/ProductsPage';
import { StoresPage } from './pages/StoresPage';
import { AccountPage } from './pages/AccountPage';
import { AdminBookkeepingPage } from './pages/admin/AdminBookkeepingPage';
import { AdminClientsPage } from './pages/admin/AdminClientsPage';
import { AdminCouponsPage } from './pages/admin/AdminCouponsPage';
import { AdminDashboardPage } from './pages/admin/AdminDashboardPage';
import { AdminEmployeesPage } from './pages/admin/AdminEmployeesPage';
import { AdminInventoryPage } from './pages/admin/AdminInventoryPage';
import { AdminOrdersPage } from './pages/admin/AdminOrdersPage';
import { AdminProductsPage } from './pages/admin/AdminProductsPage';
import { AdminReturnsPage } from './pages/admin/AdminReturnsPage';
import { AdminReviewsPage } from './pages/admin/AdminReviewsPage';
import { AdminSettingsPage } from './pages/admin/AdminSettingsPage';
import { AdminStoresPage } from './pages/admin/AdminStoresPage';
import { AdminTestimonialsPage } from './pages/admin/AdminTestimonialsPage';
import { AdminUsersPage } from './pages/admin/AdminUsersPage';

function AdminOnly({ children }: { children: React.ReactNode }) {
  return <RequireRole adminOnly>{children}</RequireRole>;
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route element={<Layout />}>
            <Route index element={<HomePage />} />
            <Route path="products" element={<ProductsPage />} />
            <Route path="stores" element={<StoresPage />} />
            <Route path="account" element={<AccountPage />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Route>

          <Route
            path="/admin"
            element={
              <RequireRole>
                <AdminLayout />
              </RequireRole>
            }
          >
            <Route index element={<AdminDashboardPage />} />
            <Route path="products" element={<AdminProductsPage />} />
            <Route path="stores" element={<AdminStoresPage />} />
            <Route path="orders" element={<AdminOrdersPage />} />
            <Route path="inventory" element={<AdminInventoryPage />} />
            <Route path="clients" element={<AdminClientsPage />} />
            <Route path="returns" element={<AdminReturnsPage />} />
            <Route path="reviews" element={<AdminReviewsPage />} />
            <Route path="testimonials" element={<AdminTestimonialsPage />} />
            <Route path="users" element={<AdminUsersPage />} />
            <Route
              path="coupons"
              element={
                <AdminOnly>
                  <AdminCouponsPage />
                </AdminOnly>
              }
            />
            <Route
              path="bookkeeping"
              element={
                <AdminOnly>
                  <AdminBookkeepingPage />
                </AdminOnly>
              }
            />
            <Route
              path="employees"
              element={
                <AdminOnly>
                  <AdminEmployeesPage />
                </AdminOnly>
              }
            />
            <Route
              path="settings"
              element={
                <AdminOnly>
                  <AdminSettingsPage />
                </AdminOnly>
              }
            />
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

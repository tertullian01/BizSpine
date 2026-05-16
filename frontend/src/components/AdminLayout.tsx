import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { isAdminRole } from '../utils/roles';

const staffNav = [
  { to: '/admin', label: 'Overview', end: true },
  { to: '/admin/products', label: 'Products' },
  { to: '/admin/stores', label: 'Stores' },
  { to: '/admin/orders', label: 'Orders' },
  { to: '/admin/inventory', label: 'Inventory' },
  { to: '/admin/clients', label: 'Clients' },
  { to: '/admin/returns', label: 'Returns' },
  { to: '/admin/reviews', label: 'Reviews' },
  { to: '/admin/testimonials', label: 'Testimonials' },
  { to: '/admin/users', label: 'Users' },
] as const;

const adminNav = [
  { to: '/admin/coupons', label: 'Coupons' },
  { to: '/admin/bookkeeping', label: 'Bookkeeping' },
  { to: '/admin/employees', label: 'Employees' },
  { to: '/admin/settings', label: 'Settings' },
] as const;

export function AdminLayout() {
  const { user, role, logout } = useAuth();
  const admin = isAdminRole(role);

  return (
    <div className="admin-shell">
      <header className="admin-header">
        <div className="admin-header-inner">
          <div className="admin-brand">
            <NavLink to="/admin" className="admin-brand-link">
              Administration
            </NavLink>
            <span className="muted admin-brand-sub">BizSpine example dashboard</span>
          </div>
          <div className="admin-header-actions">
            {user ? (
              <span className="user-chip" title={user.email}>
                {user.email}
                {role ? <em>{role}</em> : null}
              </span>
            ) : null}
            <NavLink to="/" className="btn btn-ghost btn-sm">
              Storefront
            </NavLink>
            <button type="button" className="btn btn-ghost btn-sm" onClick={() => void logout()}>
              Sign out
            </button>
          </div>
        </div>
      </header>

      <div className="admin-body">
        <nav className="admin-sidebar" aria-label="Administration">
          <p className="admin-nav-heading">Operations</p>
          <ul className="admin-nav">
            {staffNav.map((item) => (
              <li key={item.to}>
                <NavLink to={item.to} end={'end' in item ? item.end : false}>
                  {item.label}
                </NavLink>
              </li>
            ))}
          </ul>
          {admin ? (
            <>
              <p className="admin-nav-heading">Admin</p>
              <ul className="admin-nav">
                {adminNav.map((item) => (
                  <li key={item.to}>
                    <NavLink to={item.to}>{item.label}</NavLink>
                  </li>
                ))}
              </ul>
            </>
          ) : null}
        </nav>

        <main className="admin-main">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

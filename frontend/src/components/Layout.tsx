import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getApiBaseUrl } from '../api/client';

export function Layout() {
  const { isAuthenticated, user, role, logout, isLoading } = useAuth();
  const apiBase = getApiBaseUrl() || '(same origin)';

  return (
    <div className="app-shell">
      <header className="site-header">
        <div className="header-inner">
          <NavLink to="/" className="brand">
            <span className="brand-mark">B</span>
            <span>
              <strong>BizSpine</strong>
              <small>Example storefront</small>
            </span>
          </NavLink>

          <nav className="main-nav" aria-label="Main">
            <NavLink to="/" end>
              Home
            </NavLink>
            <NavLink to="/products">Products</NavLink>
            <NavLink to="/stores">Stores</NavLink>
            <NavLink to="/account">Account</NavLink>
          </nav>

          <div className="header-actions">
            {isLoading ? (
              <span className="muted">Loading…</span>
            ) : isAuthenticated ? (
              <>
                <span className="user-chip" title={user?.email ?? ''}>
                  {user?.email}
                  {role ? <em>{role}</em> : null}
                </span>
                <button type="button" className="btn btn-ghost" onClick={() => void logout()}>
                  Sign out
                </button>
              </>
            ) : (
              <NavLink to="/account" className="btn btn-primary">
                Sign in
              </NavLink>
            )}
          </div>
        </div>
      </header>

      <main className="site-main">
        <Outlet />
      </main>

      <footer className="site-footer">
        <p>
          Example frontend for the BizSpine REST API. API base: <code>{apiBase}</code>
        </p>
      </footer>
    </div>
  );
}

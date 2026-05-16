import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { isStaffRole } from '../utils/roles';

interface RequireRoleProps {
  children: React.ReactNode;
  /** When true, only `admin` may access. Otherwise admin or employee. */
  adminOnly?: boolean;
}

export function RequireRole({ children, adminOnly = false }: RequireRoleProps) {
  const { isAuthenticated, isLoading, role } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return (
      <div className="page">
        <p className="muted">Checking access…</p>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/account" replace state={{ from: location.pathname }} />;
  }

  const allowed = adminOnly ? role === 'admin' : isStaffRole(role);
  if (!allowed) {
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
}

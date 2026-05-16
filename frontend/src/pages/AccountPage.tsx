import { FormEvent, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { ApiRequestError } from '../api/client';
import { isStaffRole } from '../utils/roles';

export function AccountPage() {
  const { isAuthenticated, isLoading, user, role, login, register, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const returnTo =
    typeof location.state === 'object' &&
    location.state &&
    'from' in location.state &&
    typeof location.state.from === 'string'
      ? location.state.from
      : null;
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      if (mode === 'login') {
        await login(email, password);
      } else {
        await register(email, password);
      }
      if (returnTo?.startsWith('/admin')) {
        navigate(returnTo, { replace: true });
      }
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Authentication failed');
    } finally {
      setSubmitting(false);
    }
  };

  if (isLoading) {
    return (
      <div className="page">
        <p className="muted">Loading account…</p>
      </div>
    );
  }

  if (isAuthenticated && user) {
    return (
      <div className="page">
        <header className="page-header">
          <h1>Your account</h1>
          <p className="muted">Profile from <code>GET /auth/me</code></p>
        </header>

        <section className="card">
          <dl className="profile-grid">
            <div>
              <dt>Email</dt>
              <dd>{user.email}</dd>
            </div>
            <div>
              <dt>Role</dt>
              <dd>
                <span className="badge">{role ?? user.role}</span>
              </dd>
            </div>
            <div>
              <dt>Member since</dt>
              <dd>{new Date(user.created_at).toLocaleDateString()}</dd>
            </div>
            {user.display_name ? (
              <div>
                <dt>Display name</dt>
                <dd>{user.display_name}</dd>
              </div>
            ) : null}
          </dl>

          <div className="hero-actions">
            {isStaffRole(role ?? user.role) ? (
              <Link to="/admin" className="btn btn-primary">
                Open administration
              </Link>
            ) : null}
            <button type="button" className="btn btn-secondary" onClick={() => void logout()}>
              Sign out
            </button>
          </div>
        </section>
      </div>
    );
  }

  return (
    <div className="page account-page">
      <header className="page-header">
        <h1>{mode === 'login' ? 'Sign in' : 'Create account'}</h1>
        <p className="muted">
          Password must be at least 8 characters.
          {returnTo ? ' Sign in with a staff account to open the administration dashboard.' : null}
        </p>
      </header>

      <section className="card auth-card">
        <div className="tabs" role="tablist">
          <button
            type="button"
            role="tab"
            className={mode === 'login' ? 'active' : ''}
            aria-selected={mode === 'login'}
            onClick={() => setMode('login')}
          >
            Sign in
          </button>
          <button
            type="button"
            role="tab"
            className={mode === 'register' ? 'active' : ''}
            aria-selected={mode === 'register'}
            onClick={() => setMode('register')}
          >
            Register
          </button>
        </div>

        <form onSubmit={onSubmit} className="auth-form">
          <label>
            Email
            <input
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </label>
          <label>
            Password
            <input
              type="password"
              autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
              required
              minLength={8}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </label>

          {error ? (
            <div className="alert alert-error" role="alert">
              {error}
            </div>
          ) : null}

          <button type="submit" className="btn btn-primary" disabled={submitting}>
            {submitting ? 'Please wait…' : mode === 'login' ? 'Sign in' : 'Create account'}
          </button>
        </form>
      </section>
    </div>
  );
}

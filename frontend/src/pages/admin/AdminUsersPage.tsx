import { useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminUser } from '../../api/types';

export function AdminUsersPage() {
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError(null);
      try {
        const rows = await apiRequest<AdminUser[]>('/users');
        setUsers(rows);
      } catch (err) {
        setUsers([]);
        setError(err instanceof ApiRequestError ? err.message : 'Failed to load users');
      } finally {
        setLoading(false);
      }
    };
    void load();
  }, []);

  return (
    <div className="page">
      <header className="page-header">
        <p className="eyebrow">Directory</p>
        <h1>Users</h1>
        <p className="muted">Staff view of all accounts from <code>GET /users</code>.</p>
      </header>

      {loading ? <p className="muted">Loading users…</p> : null}
      {error ? (
        <div className="alert alert-error" role="alert">
          {error}
        </div>
      ) : null}

      {!loading && !error ? (
        <section className="card">
          <p className="results-meta">{users.length} account{users.length === 1 ? '' : 's'}</p>
          {users.length === 0 ? (
            <p className="muted">No users in the database.</p>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th scope="col">Email</th>
                    <th scope="col">Name</th>
                    <th scope="col">Role</th>
                    <th scope="col">Joined</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((u) => (
                    <tr key={u.id}>
                      <td>{u.email}</td>
                      <td>{formatName(u)}</td>
                      <td>
                        <span className="badge">{u.role}</span>
                      </td>
                      <td>{formatDate(u.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      ) : null}
    </div>
  );
}

function formatName(user: AdminUser): string {
  const parts = [user.first_name, user.last_name].filter(Boolean);
  if (parts.length) {
    return parts.join(' ');
  }
  return user.display_name ?? '—';
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleDateString();
  } catch {
    return iso;
  }
}



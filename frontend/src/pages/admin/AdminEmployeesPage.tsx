import { FormEvent, useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminEmployee } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';

export function AdminEmployeesPage() {
  const [employees, setEmployees] = useState<AdminEmployee[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [role, setRole] = useState('employee');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiRequest<AdminEmployee[]>('/employees');
      setEmployees(data);
    } catch (err) {
      setEmployees([]);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load employees');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      await apiRequest('/employees', {
        method: 'POST',
        body: JSON.stringify({
          email: email.trim(),
          password,
          display_name: displayName.trim(),
          role,
        }),
      });
      setSuccess('Employee created.');
      setShowForm(false);
      setEmail('');
      setPassword('');
      setDisplayName('');
      setRole('employee');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Create failed');
    } finally {
      setSaving(false);
    }
  };

  const onDelete = async (id: number) => {
    if (!window.confirm('Remove this employee account?')) return;
    try {
      await apiRequest(`/employees/${id}`, { method: 'DELETE' });
      setSuccess('Employee removed.');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Delete failed');
    }
  };

  return (
    <div className="page">
      <AdminPageHeader
        eyebrow="Team"
        title="Employees"
        actions={
          <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowForm(true)}>
            Add employee
          </button>
        }
      />
      <AdminFeedback error={error} success={success} />
      {loading ? <p className="muted">Loading team…</p> : null}

      {showForm ? (
        <section className="card admin-form-card">
          <h2>New employee</h2>
          <form className="admin-form" onSubmit={onSubmit}>
            <label>
              Email
              <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
            </label>
            <label>
              Display name
              <input required value={displayName} onChange={(e) => setDisplayName(e.target.value)} />
            </label>
            <label>
              Password
              <input type="password" required minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} />
            </label>
            <label>
              Role
              <select value={role} onChange={(e) => setRole(e.target.value)}>
                <option value="employee">Employee</option>
                <option value="admin">Admin</option>
              </select>
            </label>
            <div className="admin-form-actions">
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Creating…' : 'Create'}
              </button>
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
            </div>
          </form>
        </section>
      ) : null}

      {!loading ? (
        <section className="card">
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th scope="col">Name</th>
                  <th scope="col">Email</th>
                  <th scope="col">Role</th>
                  <th scope="col" />
                </tr>
              </thead>
              <tbody>
                {employees.map((e) => (
                  <tr key={e.id}>
                    <td>{e.display_name ?? '—'}</td>
                    <td>{e.email}</td>
                    <td>
                      <span className="badge">{e.role}</span>
                    </td>
                    <td className="admin-row-actions">
                      {e.role !== 'admin' ? (
                        <button type="button" className="btn btn-ghost btn-sm btn-danger-text" onClick={() => void onDelete(e.id)}>
                          Remove
                        </button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}
    </div>
  );
}

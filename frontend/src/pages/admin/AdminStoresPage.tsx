import { FormEvent, useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { Store } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';

interface StoreForm {
  name: string;
  description: string;
  currency_symbol: string;
}

const emptyForm: StoreForm = { name: '', description: '', currency_symbol: '$' };

export function AdminStoresPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [form, setForm] = useState<StoreForm>(emptyForm);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiRequest<Store[]>('/stores');
      setStores(data);
    } catch (err) {
      setStores([]);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load stores');
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
    const body = {
      name: form.name.trim(),
      description: form.description.trim() || undefined,
      currency_symbol: form.currency_symbol.trim() || '$',
    };
    try {
      if (editingId) {
        await apiRequest(`/stores/${editingId}`, { method: 'PUT', body: JSON.stringify(body) });
        setSuccess('Store updated.');
      } else {
        await apiRequest('/stores', { method: 'POST', body: JSON.stringify(body) });
        setSuccess('Store created.');
      }
      setShowForm(false);
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const onDelete = async (id: number) => {
    if (!window.confirm('Delete this store?')) return;
    try {
      await apiRequest(`/stores/${id}`, { method: 'DELETE' });
      setSuccess('Store deleted.');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Delete failed');
    }
  };

  return (
    <div className="page">
      <AdminPageHeader
        eyebrow="Locations"
        title="Stores"
        actions={
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => {
              setEditingId(null);
              setForm(emptyForm);
              setShowForm(true);
            }}
          >
            Add store
          </button>
        }
      />
      <AdminFeedback error={error} success={success} />
      {loading ? <p className="muted">Loading stores…</p> : null}

      {showForm ? (
        <section className="card admin-form-card">
          <h2>{editingId ? 'Edit store' : 'New store'}</h2>
          <form className="admin-form" onSubmit={onSubmit}>
            <label>
              Name
              <input required minLength={3} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label>
              Currency
              <input value={form.currency_symbol} onChange={(e) => setForm({ ...form, currency_symbol: e.target.value })} />
            </label>
            <label className="admin-form-full">
              Description
              <textarea rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
            </label>
            <div className="admin-form-actions">
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving…' : 'Save'}
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
                  <th scope="col">Currency</th>
                  <th scope="col">Description</th>
                  <th scope="col" />
                </tr>
              </thead>
              <tbody>
                {stores.map((s) => (
                  <tr key={s.id}>
                    <td>{s.name}</td>
                    <td>{s.currency_symbol ?? '$'}</td>
                    <td>{s.description ?? '—'}</td>
                    <td className="admin-row-actions">
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        onClick={() => {
                          setEditingId(s.id);
                          setForm({
                            name: s.name,
                            description: s.description ?? '',
                            currency_symbol: s.currency_symbol ?? '$',
                          });
                          setShowForm(true);
                        }}
                      >
                        Edit
                      </button>
                      <button type="button" className="btn btn-ghost btn-sm btn-danger-text" onClick={() => void onDelete(s.id)}>
                        Delete
                      </button>
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

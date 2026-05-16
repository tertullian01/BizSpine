import { FormEvent, useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { PaginatedProducts, Product } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';
import { formatMoney } from '../../utils/adminFormat';

interface ProductForm {
  name: string;
  cost: string;
  type: string;
  description: string;
  state: string;
}

const emptyForm: ProductForm = {
  name: '',
  cost: '',
  type: '',
  description: '',
  state: 'For Sale',
};

export function AdminProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [form, setForm] = useState<ProductForm>(emptyForm);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await apiRequest<PaginatedProducts>('/products?limit=100');
      setProducts(result.data);
      setTotal(result.pagination.total);
    } catch (err) {
      setProducts([]);
      setTotal(0);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load products');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm);
    setShowForm(true);
    setSuccess(null);
  };

  const openEdit = (product: Product) => {
    setEditingId(product.id);
    setForm({
      name: product.name,
      cost: product.cost != null ? String(product.cost) : '',
      type: product.type ?? '',
      description: product.description ?? '',
      state: product.state ?? 'For Sale',
    });
    setShowForm(true);
    setSuccess(null);
  };

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    const body = {
      name: form.name.trim(),
      cost: parseFloat(form.cost),
      type: form.type.trim() || undefined,
      description: form.description.trim() || undefined,
      state: form.state,
    };
    try {
      if (editingId) {
        await apiRequest(`/products/${editingId}`, {
          method: 'PUT',
          body: JSON.stringify(body),
        });
        setSuccess('Product updated.');
      } else {
        await apiRequest('/products', { method: 'POST', body: JSON.stringify(body) });
        setSuccess('Product created.');
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
    if (!window.confirm('Delete this product?')) return;
    setError(null);
    try {
      await apiRequest(`/products/${id}`, { method: 'DELETE' });
      setSuccess('Product deleted.');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Delete failed');
    }
  };

  return (
    <div className="page">
      <AdminPageHeader
        eyebrow="Catalog"
        title="Products"
        description="Create and edit products via staff API routes."
        actions={
          <button type="button" className="btn btn-primary btn-sm" onClick={openCreate}>
            Add product
          </button>
        }
      />
      <AdminFeedback error={error} success={success} />
      {loading ? <p className="muted">Loading products…</p> : null}

      {showForm ? (
        <section className="card admin-form-card">
          <h2>{editingId ? 'Edit product' : 'New product'}</h2>
          <form className="admin-form" onSubmit={onSubmit}>
            <label>
              Name
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label>
              Price
              <input
                type="number"
                step="0.01"
                min="0"
                required
                value={form.cost}
                onChange={(e) => setForm({ ...form, cost: e.target.value })}
              />
            </label>
            <label>
              Type
              <input value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} />
            </label>
            <label>
              State
              <select value={form.state} onChange={(e) => setForm({ ...form, state: e.target.value })}>
                <option value="For Sale">For Sale</option>
                <option value="Discontinued">Discontinued</option>
              </select>
            </label>
            <label className="admin-form-full">
              Description
              <textarea
                rows={3}
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
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
          <p className="results-meta">{total} product{total === 1 ? '' : 's'}</p>
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th scope="col">Name</th>
                  <th scope="col">Type</th>
                  <th scope="col">State</th>
                  <th scope="col" className="num">
                    Price
                  </th>
                  <th scope="col" />
                </tr>
              </thead>
              <tbody>
                {products.map((p) => (
                  <tr key={p.id}>
                    <td>{p.name}</td>
                    <td>{p.type ?? '—'}</td>
                    <td>
                      <span className="badge">{p.state ?? '—'}</span>
                    </td>
                    <td className="num">{p.cost != null ? formatMoney(p.cost) : '—'}</td>
                    <td className="admin-row-actions">
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => openEdit(p)}>
                        Edit
                      </button>
                      <button type="button" className="btn btn-ghost btn-sm btn-danger-text" onClick={() => void onDelete(p.id)}>
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

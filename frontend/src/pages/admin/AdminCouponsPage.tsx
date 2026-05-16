import { FormEvent, useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminCoupon } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';

interface CouponForm {
  code: string;
  discount_type: 'percentage' | 'fixed';
  discount_value: string;
  min_purchase_amount: string;
  max_uses: string;
  is_active: boolean;
  description: string;
}

const emptyForm: CouponForm = {
  code: '',
  discount_type: 'percentage',
  discount_value: '10',
  min_purchase_amount: '',
  max_uses: '',
  is_active: true,
  description: '',
};

export function AdminCouponsPage() {
  const [coupons, setCoupons] = useState<AdminCoupon[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [form, setForm] = useState<CouponForm>(emptyForm);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiRequest<AdminCoupon[]>('/coupons');
      setCoupons(data);
    } catch (err) {
      setCoupons([]);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load coupons');
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
      await apiRequest('/coupons', {
        method: 'POST',
        body: JSON.stringify({
          code: form.code.trim().toUpperCase(),
          discount_type: form.discount_type,
          discount_value: parseFloat(form.discount_value),
          min_purchase_amount: form.min_purchase_amount ? parseFloat(form.min_purchase_amount) : null,
          max_uses: form.max_uses ? parseInt(form.max_uses, 10) : null,
          is_active: form.is_active ? 1 : 0,
          description: form.description.trim() || null,
        }),
      });
      setSuccess('Coupon created.');
      setShowForm(false);
      setForm(emptyForm);
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Create failed');
    } finally {
      setSaving(false);
    }
  };

  const toggleActive = async (coupon: AdminCoupon) => {
    try {
      await apiRequest(`/coupons/${coupon.id}`, {
        method: 'PUT',
        body: JSON.stringify({ is_active: coupon.is_active ? 0 : 1 }),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Update failed');
    }
  };

  const onDelete = async (id: number) => {
    if (!window.confirm('Delete this coupon?')) return;
    try {
      await apiRequest(`/coupons/${id}`, { method: 'DELETE' });
      setSuccess('Coupon deleted.');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Delete failed');
    }
  };

  return (
    <div className="page">
      <AdminPageHeader
        eyebrow="Promotions"
        title="Coupons"
        actions={
          <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowForm(true)}>
            New coupon
          </button>
        }
      />
      <AdminFeedback error={error} success={success} />
      {loading ? <p className="muted">Loading coupons…</p> : null}

      {showForm ? (
        <section className="card admin-form-card">
          <h2>New coupon</h2>
          <form className="admin-form" onSubmit={onSubmit}>
            <label>
              Code
              <input required value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
            </label>
            <label>
              Type
              <select
                value={form.discount_type}
                onChange={(e) => setForm({ ...form, discount_type: e.target.value as CouponForm['discount_type'] })}
              >
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed amount</option>
              </select>
            </label>
            <label>
              Value
              <input
                type="number"
                step="0.01"
                required
                value={form.discount_value}
                onChange={(e) => setForm({ ...form, discount_value: e.target.value })}
              />
            </label>
            <label>
              Min purchase
              <input
                type="number"
                step="0.01"
                value={form.min_purchase_amount}
                onChange={(e) => setForm({ ...form, min_purchase_amount: e.target.value })}
              />
            </label>
            <label>
              Max uses
              <input
                type="number"
                value={form.max_uses}
                onChange={(e) => setForm({ ...form, max_uses: e.target.value })}
              />
            </label>
            <label className="admin-form-checkbox">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              />
              Active
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
                  <th scope="col">Code</th>
                  <th scope="col">Discount</th>
                  <th scope="col">Uses</th>
                  <th scope="col">Status</th>
                  <th scope="col" />
                </tr>
              </thead>
              <tbody>
                {coupons.map((c) => (
                  <tr key={c.id}>
                    <td>
                      <strong>{c.code}</strong>
                    </td>
                    <td>
                      {c.discount_type === 'percentage'
                        ? `${c.discount_value}%`
                        : `$${c.discount_value}`}
                    </td>
                    <td>
                      {c.times_used ?? 0}
                      {c.max_uses != null ? ` / ${c.max_uses}` : ''}
                    </td>
                    <td>
                      <span className={`badge${c.is_active ? '' : ' badge-muted'}`}>
                        {c.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="admin-row-actions">
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => void toggleActive(c)}>
                        {c.is_active ? 'Deactivate' : 'Activate'}
                      </button>
                      <button type="button" className="btn btn-ghost btn-sm btn-danger-text" onClick={() => void onDelete(c.id)}>
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

import { useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminOrderDetail, FulfillmentStatus, OrderSummary, PaginatedOrders } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';
import { formatDate, formatMoney } from '../../utils/adminFormat';

const STATUSES: FulfillmentStatus[] = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

export function AdminOrdersPage() {
  const [orders, setOrders] = useState<OrderSummary[]>([]);
  const [total, setTotal] = useState(0);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<AdminOrderDetail | null>(null);
  const [status, setStatus] = useState<FulfillmentStatus>('pending');
  const [tracking, setTracking] = useState('');
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const loadOrders = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await apiRequest<PaginatedOrders>('/orders?limit=50');
      setOrders(result.data);
      setTotal(result.pagination.total);
    } catch (err) {
      setOrders([]);
      setTotal(0);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load orders');
    } finally {
      setLoading(false);
    }
  }, []);

  const loadDetail = useCallback(async (id: number) => {
    setDetailLoading(true);
    setError(null);
    try {
      const data = await apiRequest<AdminOrderDetail>(`/orders/${id}`);
      setDetail(data);
      setStatus((data.fulfillment_status as FulfillmentStatus) ?? 'pending');
      setTracking(data.tracking_number ?? '');
    } catch (err) {
      setDetail(null);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load order');
    } finally {
      setDetailLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadOrders();
  }, [loadOrders]);

  useEffect(() => {
    if (selectedId) {
      void loadDetail(selectedId);
    } else {
      setDetail(null);
    }
  }, [selectedId, loadDetail]);

  const saveFulfillment = async () => {
    if (!selectedId) return;
    setError(null);
    setSuccess(null);
    try {
      await apiRequest(`/orders/${selectedId}/fulfillment`, {
        method: 'PUT',
        body: JSON.stringify({
          fulfillment_status: status,
          tracking_number: tracking.trim() || undefined,
        }),
      });
      setSuccess('Fulfillment updated.');
      await loadOrders();
      await loadDetail(selectedId);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Update failed');
    }
  };

  return (
    <div className="page">
      <AdminPageHeader eyebrow="Fulfillment" title="Orders" description="View orders and update fulfillment status." />
      <AdminFeedback error={error} success={success} />

      <div className="admin-split">
        <section className="card admin-split-list">
          {loading ? <p className="muted">Loading orders…</p> : null}
          {!loading ? (
            <>
              <p className="results-meta">{total} order{total === 1 ? '' : 's'}</p>
              <ul className="admin-picker">
                {orders.map((order) => (
                  <li key={order.id}>
                    <button
                      type="button"
                      className={`admin-picker-item${selectedId === order.id ? ' active' : ''}`}
                      onClick={() => setSelectedId(order.id)}
                    >
                      <span>{order.order_number ?? `#${order.id}`}</span>
                      <span className="muted">{order.fulfillment_status ?? 'pending'}</span>
                      <strong>{formatMoney(order.total ?? 0)}</strong>
                    </button>
                  </li>
                ))}
              </ul>
            </>
          ) : null}
        </section>

        <section className="card admin-split-detail">
          {!selectedId ? (
            <p className="muted">Select an order to view details.</p>
          ) : detailLoading ? (
            <p className="muted">Loading order…</p>
          ) : detail ? (
            <>
              <h2>{detail.order_number ?? `Order #${detail.id}`}</h2>
              <dl className="profile-grid">
                <div>
                  <dt>Customer</dt>
                  <dd>{detail.user_email ?? detail.customer_name_display ?? '—'}</dd>
                </div>
                <div>
                  <dt>Date</dt>
                  <dd>{detail.order_date ? formatDate(detail.order_date, true) : '—'}</dd>
                </div>
                <div>
                  <dt>Total</dt>
                  <dd>{formatMoney(detail.total ?? 0)}</dd>
                </div>
              </dl>

              {detail.items && detail.items.length > 0 ? (
                <div className="table-wrap">
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th scope="col">Item</th>
                        <th scope="col" className="num">
                          Qty
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {detail.items.map((item, i) => (
                        <tr key={i}>
                          <td>{item.product_name ?? 'Item'}</td>
                          <td className="num">{item.quantity ?? 1}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : null}

              <div className="admin-form admin-form-inline">
                <label>
                  Status
                  <select value={status} onChange={(e) => setStatus(e.target.value as FulfillmentStatus)}>
                    {STATUSES.map((s) => (
                      <option key={s} value={s}>
                        {s}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  Tracking #
                  <input value={tracking} onChange={(e) => setTracking(e.target.value)} />
                </label>
                <button type="button" className="btn btn-primary" onClick={() => void saveFulfillment()}>
                  Update fulfillment
                </button>
              </div>
            </>
          ) : null}
        </section>
      </div>
    </div>
  );
}

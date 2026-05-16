import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiRequest } from '../../api/client';
import type {
  BookkeepingSummary,
  InventoryRow,
  OrderSummary,
  PaginatedOrders,
  PaginatedProducts,
} from '../../api/types';
import { useAuth } from '../../context/AuthContext';
import { isAdminRole } from '../../utils/roles';

interface DashboardStats {
  userCount: number | null;
  productCount: number | null;
  orderCount: number | null;
  lowStockCount: number | null;
  bookkeeping: BookkeepingSummary | null;
  recentOrders: OrderSummary[];
}

export function AdminDashboardPage() {
  const { role, user } = useAuth();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    const load = async () => {
      setLoading(true);

      const next: DashboardStats = {
        userCount: null,
        productCount: null,
        orderCount: null,
        lowStockCount: null,
        bookkeeping: null,
        recentOrders: [],
      };

      const tasks: Promise<void>[] = [
        apiRequest<unknown[]>('/users')
          .then((users) => {
            next.userCount = users.length;
          })
          .catch(() => {
            next.userCount = null;
          }),
        apiRequest<PaginatedProducts>('/products?limit=1')
          .then((result) => {
            next.productCount = result.pagination.total;
          })
          .catch(() => {
            next.productCount = null;
          }),
        apiRequest<PaginatedOrders>('/orders?limit=5')
          .then((result) => {
            next.orderCount = result.pagination.total;
            next.recentOrders = result.data;
          })
          .catch(() => {
            next.orderCount = null;
          }),
        apiRequest<InventoryRow[]>('/inventory/low-stock')
          .then((rows) => {
            next.lowStockCount = rows.length;
          })
          .catch(() => {
            next.lowStockCount = null;
          }),
      ];

      if (isAdminRole(role)) {
        tasks.push(
          apiRequest<BookkeepingSummary>('/bookkeeping/summary')
            .then((summary) => {
              next.bookkeeping = summary;
            })
            .catch(() => {
              next.bookkeeping = null;
            }),
        );
      }

      await Promise.all(tasks);
      setStats(next);
      setLoading(false);
    };

    void load();
  }, [role]);

  return (
    <div className="page">
      <header className="page-header">
        <p className="eyebrow">Dashboard</p>
        <h1>Overview</h1>
        <p className="muted">
          Signed in as {user?.email ?? 'staff'}. This example pulls live data from staff and admin API
          endpoints.
        </p>
      </header>

      {loading ? <p className="muted">Loading dashboard…</p> : null}

      {stats && !loading ? (
        <>
          <div className="stat-grid">
            <StatCard label="Users" value={stats.userCount} href="/admin/users" />
            <StatCard label="Products" value={stats.productCount} href="/admin/products" />
            <StatCard label="Orders" value={stats.orderCount} href="/admin/orders" />
            <StatCard label="Low stock SKUs" value={stats.lowStockCount} href="/admin/inventory" />
          </div>

          {stats.bookkeeping ? (
            <section className="card">
              <div className="card-header">
                <h2>Bookkeeping</h2>
                <Link to="/admin/bookkeeping" className="btn btn-secondary btn-sm">
                  Details
                </Link>
              </div>
              <div className="stat-grid stat-grid-inline">
                <StatInline label="Income" value={formatMoney(stats.bookkeeping.total_income)} />
                <StatInline label="Expenses" value={formatMoney(stats.bookkeeping.total_expenses)} />
                <StatInline
                  label="Profit"
                  value={formatMoney(stats.bookkeeping.profit)}
                  highlight={stats.bookkeeping.profit >= 0}
                />
              </div>
            </section>
          ) : isAdminRole(role) ? (
            <p className="muted">Bookkeeping summary unavailable (no records or API error).</p>
          ) : null}

          <section className="card">
            <div className="card-header">
              <h2>Recent orders</h2>
              <Link to="/admin/orders" className="btn btn-secondary btn-sm">
                View all
              </Link>
            </div>
            {stats.recentOrders.length === 0 ? (
              <p className="muted">No orders yet.</p>
            ) : (
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th scope="col">Order</th>
                      <th scope="col">Customer</th>
                      <th scope="col">Status</th>
                      <th scope="col" className="num">
                        Total
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {stats.recentOrders.map((order) => (
                      <tr key={order.id}>
                        <td>{order.order_number ?? `#${order.id}`}</td>
                        <td>{order.user_email ?? order.customer_name_display ?? '—'}</td>
                        <td>
                          <span className="badge">{order.fulfillment_status ?? 'unknown'}</span>
                        </td>
                        <td className="num">{formatMoney(order.total ?? 0)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          <section className="card">
            <h2>Quick links</h2>
            <div className="admin-quick-links">
              <Link to="/admin/products" className="btn btn-secondary btn-sm">
                Manage products
              </Link>
              <Link to="/admin/stores" className="btn btn-secondary btn-sm">
                Manage stores
              </Link>
              <Link to="/admin/clients" className="btn btn-secondary btn-sm">
                View clients
              </Link>
              <Link to="/admin/returns" className="btn btn-secondary btn-sm">
                Process returns
              </Link>
              <Link to="/admin/reviews" className="btn btn-secondary btn-sm">
                Moderate reviews
              </Link>
              {isAdminRole(role) ? (
                <Link to="/admin/coupons" className="btn btn-secondary btn-sm">
                  Coupons
                </Link>
              ) : null}
            </div>
          </section>
        </>
      ) : null}
    </div>
  );
}

function StatCard({
  label,
  value,
  href,
  external,
}: {
  label: string;
  value: number | null;
  href: string;
  external?: boolean;
}) {
  const display = value == null ? '—' : String(value);
  if (external) {
    return (
      <a href={href} className="stat-card">
        <span className="stat-label">{label}</span>
        <span className="stat-value">{display}</span>
      </a>
    );
  }
  return (
    <Link to={href} className="stat-card">
      <span className="stat-label">{label}</span>
      <span className="stat-value">{display}</span>
    </Link>
  );
}

function StatInline({
  label,
  value,
  highlight,
}: {
  label: string;
  value: string;
  highlight?: boolean;
}) {
  return (
    <div className="stat-inline">
      <span className="stat-label">{label}</span>
      <span className={`stat-value${highlight === false ? ' stat-value-warn' : ''}`}>{value}</span>
    </div>
  );
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(amount);
}



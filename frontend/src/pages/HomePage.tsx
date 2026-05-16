import { Link } from 'react-router-dom';
import { HealthStatus } from '../components/HealthStatus';
import { branding } from '../branding';

export function HomePage() {
  return (
    <div className="page">
      <section className="hero">
        <img src={branding.hero} alt="BizSpine" className="hero-logo" width={320} height={320} decoding="async" />
        <p className="eyebrow">BizSpine example</p>
        <h1>Storefront starter connected to your API</h1>
        <p className="lead">
          This app demonstrates authentication, product catalog, and store locations against the
          BizSpine REST backend. Fork it, restyle it, and deploy it with your own domain.
        </p>
        <div className="hero-actions">
          <Link to="/products" className="btn btn-primary">
            Browse products
          </Link>
          <Link to="/account" className="btn btn-secondary">
            Sign in
          </Link>
          <Link to="/admin" className="btn btn-secondary">
            Administration
          </Link>
        </div>
      </section>

      <HealthStatus />

      <section className="card">
        <h2>What is included</h2>
        <ul className="feature-list">
          <li>JWT login and registration via <code>POST /auth/login</code> and <code>POST /auth/register</code></li>
          <li>Public product catalog from <code>GET /products</code></li>
          <li>Store locations from <code>GET /stores</code></li>
          <li>Example staff dashboard at <code>/admin</code> (orders, users, inventory, bookkeeping)</li>
          <li>Dev proxy so you do not need CORS changes on localhost</li>
        </ul>
      </section>
    </div>
  );
}

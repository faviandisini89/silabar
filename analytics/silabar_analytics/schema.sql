-- Reference schema used by the test-suite. The authoritative schema lives in the
-- Laravel migrations (database/migrations); tools/check_schema.py keeps both in sync.
CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, role TEXT, nim TEXT, organization TEXT, phone TEXT, trust_score INTEGER, is_active INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT);
CREATE TABLE locations (id INTEGER PRIMARY KEY, name TEXT, description TEXT);
CREATE TABLE items (id INTEGER PRIMARY KEY, category_id INTEGER, location_id INTEGER, name TEXT, sku TEXT, tracking_type TEXT, total_stock INTEGER, max_loan_days INTEGER, requires_approval INTEGER, replacement_value REAL, tile INTEGER, photo_path TEXT, description TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE item_units (id INTEGER PRIMARY KEY, item_id INTEGER, asset_code TEXT, condition TEXT, status TEXT, acquired_at TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE loans (id INTEGER PRIMARY KEY, loan_code TEXT, borrower_id INTEGER, approved_by INTEGER, handed_over_by INTEGER, status TEXT, purpose TEXT, start_at TEXT, due_at TEXT, approved_at TEXT, handed_over_at TEXT, returned_at TEXT, late_days INTEGER, reject_reason TEXT, cancel_note TEXT, qr_token TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE loan_details (id INTEGER PRIMARY KEY, loan_id INTEGER, item_id INTEGER, item_unit_id INTEGER, qty INTEGER);
CREATE TABLE returns (id INTEGER PRIMARY KEY, loan_id INTEGER, received_by INTEGER, returned_at TEXT, late_days INTEGER, notes TEXT);
CREATE TABLE return_details (id INTEGER PRIMARY KEY, return_id INTEGER, loan_detail_id INTEGER, condition_after TEXT, qty_returned INTEGER);
CREATE TABLE penalties (id INTEGER PRIMARY KEY, return_id INTEGER, loan_id INTEGER, user_id INTEGER, type TEXT, amount REAL, status TEXT, note TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE maintenance_logs (id INTEGER PRIMARY KEY, item_unit_id INTEGER, reported_by INTEGER, issue TEXT, status TEXT, cost REAL, started_at TEXT, finished_at TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE activity_logs (id INTEGER PRIMARY KEY, causer_id INTEGER, action TEXT, subject_type TEXT, subject_id TEXT, properties TEXT, created_at TEXT);
CREATE TABLE daily_item_stats (id INTEGER PRIMARY KEY, stat_date TEXT, item_id INTEGER, loan_count INTEGER, qty_borrowed INTEGER, unique_borrowers INTEGER, avg_duration_days REAL, unmet_requests INTEGER);
CREATE TABLE demand_forecasts (id INTEGER PRIMARY KEY, item_id INTEGER, forecast_date TEXT, predicted_qty REAL, lower_bound REAL, upper_bound REAL, model_name TEXT, generated_at TEXT);
CREATE TABLE overdue_risk_scores (id INTEGER PRIMARY KEY, loan_id INTEGER, risk_probability REAL, model_version TEXT, scored_at TEXT);
CREATE TABLE unmet_demand_logs (id INTEGER PRIMARY KEY, item_id INTEGER, user_id INTEGER, requested_qty INTEGER, available_qty INTEGER, requested_start_at TEXT, requested_end_at TEXT, reason TEXT, util REAL, maint_share REAL, overdue_units INTEGER, created_at TEXT);
CREATE TABLE procurement_recommendations (id INTEGER PRIMARY KEY, item_id INTEGER, period_start TEXT, period_end TEXT, unmet_count INTEGER, affected_users INTEGER, fill_rate REAL, peak_shortage_qty INTEGER, recommended_add_qty INTEGER, cause TEXT, priority_score REAL, status TEXT, reviewed_by INTEGER, reviewed_at TEXT, admin_note TEXT, rationale TEXT, window_text TEXT, trend INTEGER, fulfilled_at TEXT, fill_before REAL, added_qty INTEGER, generated_at TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE analysis_outputs (id INTEGER PRIMARY KEY, kind TEXT, item_id INTEGER, payload TEXT, generated_at TEXT);
CREATE TABLE settings (id INTEGER PRIMARY KEY, name TEXT, value TEXT);
CREATE TABLE loan_extensions (id INTEGER PRIMARY KEY, loan_id INTEGER, days INTEGER, reason TEXT, status TEXT, decided_by INTEGER, decided_at TEXT, note TEXT, created_at TEXT, updated_at TEXT);

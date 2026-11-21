\set ON_ERROR_STOP on

\if :{?database}
\else
\set database moashir
\endif
\if :{?owner_role}
\else
\set owner_role moashir_owner
\endif
\if :{?app_role}
\else
\set app_role moashir_app
\endif
\if :{?maintenance_role}
\else
\set maintenance_role moashir_maintenance
\endif

SELECT NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'owner_role') AS create_owner_role,
       NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_role') AS create_app_role,
       NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'maintenance_role') AS create_maintenance_role \gset

\if :create_owner_role
CREATE ROLE :"owner_role" LOGIN;
\endif
\if :create_app_role
CREATE ROLE :"app_role" LOGIN;
\endif
\if :create_maintenance_role
CREATE ROLE :"maintenance_role" LOGIN;
\endif

BEGIN;

REVOKE ALL ON DATABASE :"database" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"database" TO :"owner_role", :"app_role", :"maintenance_role";
ALTER SCHEMA public OWNER TO :"owner_role";
GRANT USAGE ON SCHEMA public TO :"app_role", :"maintenance_role";

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"app_role";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO :"app_role";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_role";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO :"app_role";

REVOKE UPDATE, DELETE, TRUNCATE ON audits, authentication_events, terms_acceptances, criteria_edit_events FROM :"app_role";
GRANT SELECT, INSERT ON audits, authentication_events, terms_acceptances, criteria_edit_events TO :"app_role";

REVOKE UPDATE, TRUNCATE ON entity_profile_changes FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON entity_profile_changes TO :"app_role";

REVOKE UPDATE, TRUNCATE ON assessment_status_transitions, assessment_result_snapshots, assessment_criteria_moves FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON assessment_status_transitions, assessment_result_snapshots, assessment_criteria_moves TO :"app_role";

REVOKE UPDATE, TRUNCATE ON assessment_scores FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON assessment_scores TO :"app_role";
GRANT UPDATE (superseded_at) ON assessment_scores TO :"app_role";

REVOKE UPDATE, TRUNCATE ON assessment_feedback_items FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON assessment_feedback_items TO :"app_role";
GRANT UPDATE (superseded_at) ON assessment_feedback_items TO :"app_role";

REVOKE UPDATE, TRUNCATE ON assessment_summaries FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON assessment_summaries TO :"app_role";
GRANT UPDATE (superseded_at) ON assessment_summaries TO :"app_role";

REVOKE UPDATE, TRUNCATE ON assessment_assignments FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON assessment_assignments TO :"app_role";
GRANT UPDATE (status, started_at, ended_at, end_reason, updated_at) ON assessment_assignments TO :"app_role";

REVOKE UPDATE, TRUNCATE ON filtering_decisions FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON filtering_decisions TO :"app_role";

REVOKE UPDATE, TRUNCATE ON committee_averages, secretary_decisions FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON committee_averages, secretary_decisions TO :"app_role";

REVOKE UPDATE, TRUNCATE ON committee_scores, committee_notes, committee_discussion_entries FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON committee_scores, committee_notes, committee_discussion_entries TO :"app_role";
GRANT UPDATE (superseded_at) ON committee_scores, committee_notes, committee_discussion_entries TO :"app_role";

REVOKE UPDATE, DELETE, TRUNCATE ON refunds FROM :"app_role";
GRANT SELECT, INSERT ON refunds TO :"app_role";

REVOKE UPDATE, DELETE, TRUNCATE ON payment_events FROM :"app_role";
GRANT SELECT, INSERT ON payment_events TO :"app_role";
GRANT UPDATE (payment_id, processed_at, processing_result, error_code) ON payment_events TO :"app_role";

REVOKE DELETE, TRUNCATE ON payments, payment_receipts, finance_exports FROM :"app_role";
GRANT SELECT, INSERT, UPDATE ON payments, payment_receipts, finance_exports TO :"app_role";

REVOKE UPDATE, DELETE, TRUNCATE ON housekeeping_runs FROM :"app_role";
GRANT SELECT, INSERT ON housekeeping_runs TO :"app_role";

REVOKE DELETE, TRUNCATE ON certificates FROM :"app_role";
GRANT SELECT, INSERT, UPDATE ON certificates TO :"app_role";

REVOKE UPDATE, TRUNCATE ON certificate_verification_logs FROM :"app_role";
GRANT SELECT, INSERT, DELETE ON certificate_verification_logs TO :"app_role";

GRANT SELECT ON audits, authentication_events TO :"maintenance_role";

COMMIT;

\if :{?skip_password_prompts}
\else
\password :owner_role
\password :app_role
\password :maintenance_role
\endif

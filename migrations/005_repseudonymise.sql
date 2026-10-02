-- Phase 8, second half: throw away facts that were written under names.
--
-- Migration 004 renamed student_id to student_key, which left rows holding a name
-- in a column that now means "pseudonym". Those rows cannot be converted — there is
-- no way to turn a name into the random key that should have been there.
--
-- So they go, along with the cursor that says they were projected. The worker then
-- rebuilds every one of them from the event log, correctly pseudonymised, because
-- the log and not the projection was always the source of truth. In a system that
-- stored its state directly this would be an irreversible data migration.

DELETE FROM fact_attempt;
DELETE FROM projection_cursors WHERE projection = 'attempts';

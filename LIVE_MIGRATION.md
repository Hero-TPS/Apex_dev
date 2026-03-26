# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

## Pending

### Access Control (2026-03-25)
- [x] Run `migrations/access_control.sql` — creates `users`, `roles`, `user_roles`, `pages`, `role_permissions` tables and seeds default Admin role + admin user
- [x] **After migration**: Log in at `/login.php` with `admin` / `admin123` and **change the password immediately** via Access Control → Users → Edit

---

**Notes:**
- Always run on local first and verify before running on live
- Check off each item after executing
- Move completed items to the Completed section with the date done
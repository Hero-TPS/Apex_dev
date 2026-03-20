# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

## Pending

### Duplicate Client Management Tables

```sql
-- Contact links (household / associates)
CREATE TABLE IF NOT EXISTS contact_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id_a INT NOT NULL,
    contact_id_b INT NOT NULL,
    link_type VARCHAR(50) NOT NULL DEFAULT 'household',
    created_at DATETIME DEFAULT NOW(),
    UNIQUE KEY unique_pair (contact_id_a, contact_id_b),
    INDEX idx_a (contact_id_a),
    INDEX idx_b (contact_id_b)
);

-- Dismissed duplicate pairs (never flag again)
CREATE TABLE IF NOT EXISTS duplicate_dismissals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id_a INT NOT NULL,
    contact_id_b INT NOT NULL,
    dismissed_at DATETIME DEFAULT NOW(),
    UNIQUE KEY unique_pair (contact_id_a, contact_id_b)
);
```

---

**Notes:**
- Always run on local first and verify before running on live
- Check off each item after executing
- Move completed items to the Completed section with the date done
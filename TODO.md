# HPTS-XAMPP Development TODO

**Last Updated:** 2026-03-13
**Timezone:** Africa/Johannesburg (UTC+2 / SAST)

---

### Bookings Module Enhancements and Fixes
- [x] WhatsApp message history tracking (see detailed spec below)
- [ ] Some clients wants to make a booking on a date, but do not have the time or details. I need ideas how to handle this viea calendar
  - idea prebooking and reminders
  - use another calendar
  -turn reminders into bookings
- [ ] Start thinkng of how to deal with additional car drivers, mark bookings as such and commission taken

---

### Clients Module Enhancements and Fixes
- [ ] Create list of clients with prior bookings:
  - Export names and phone numbers
  - Discuss WhatsApp Business group creation
  - Ideas for direct WhatsApp communication with existing clients
- [ ] WhatsApp Business integration for bulk messaging

---

### Fuel Module Enhancements and Fixes

---

### Uber Module Enhancements and Fixes

---

### Driver Module Enhancements and Fixes

---

### Financials:

#### Bockport finance overview structure
- [ ] Use the same structure in Bookings, fuel and Uber report have having a month view and weeks are a toggle.

#### Uber Additional Costs Integration
- [x] core/Time.php is used in exactly one place: financials/helper.php, which includes it and calls. Deleted. Note for when we work on financials
- [x] Update `financials/helper.php` to include `additional_cost` in expense calculations
- [x] Modify `getWeeklyMetrics()` function
- [x] Modify `getMonthlyMetrics()` function
- [x] Update financial dashboard displays
- [x] Test that additional costs are deducted from net profit

**Calculation:**
```
Total Expenses = Fuel + Car Rental + Additional Costs
Net Profit = Total Income - Total Expenses
```

---

### Send Custom WhatsApp Message to Client
- [x] Add "Send Message" button to booking view page (`modules/Bookings/view.php`)
- [x] Add "Send Message" button to client list (`modules/Clients/index.php`)
- [x] Open a WA msg with Pre-fill client name

---

### WhatsApp Message History (Detailed Spec)
- [x] Log all WhatsApp confirmations sent
- [x] Create `whatsapp_log` table with columns:
  - `id` INT AUTO_INCREMENT PRIMARY KEY
  - `booking_id` INT (foreign key)
  - `contact_id` INT (foreign key)
  - `message_type` VARCHAR (confirmation, custom, thank_you, etc.)
  - `message_content` TEXT
  - `sent_at` DATETIME
  - `sent_by` VARCHAR (user identifier)
- [x] Display message history in booking view page
- [x] Display message history in client bookings page
- [x] Useful for tracking "I never got a confirmation" disputes
- [x] Add filter/search in message log

---

### General System Improvements
- [ ] **Breadcrumbs:** Check consistency across all pages
  - Consider creating helper function for breadcrumb generation
  - OR implement proper main menu navigation

---

## ✅ Recently Completed (2026-03-08)

### High Priority

#### Fix Missing `getSystemVariable()` Function
- [x] Currently hardcoded values in financials
- [x] Check Maintenance module for proper implementation
- [x] Update `financials/helper.php` to use system variables properly

---

### Bookings Module Enhancements
- [x] Reduce logging verbosity (tone down on logging every action)
- [x] Add link to booking view in Calendar event
- [x] When updating a client name in booking edit, update the pickup address as well
  - If the current pickup is other, overwrite (reset) it to the new address
- [x] Swop destination does not work in add
- [x] Save Gate code in booking view. only have a button called update. No add or edit. Add field to table

---

### Clients Module Enhancements
- [x] Show payment method in booking lists
- [x] Improve client search functionality:
  - Change to "includes" search (not exact match)
  - Expand search to include phone and address fields
  - Consider fuzzy matching

---

### Dashboard: Tomorrow's Booking Confirmations Widget
- [x] Create dashboard widget showing tomorrow's bookings
- [x] Display bookings where `trip_date = tomorrow` AND not confirmed today
- [x] "Send Confirmation" button → opens WhatsApp with pre-filled message
- [x] Mark booking as confirmed when button clicked
- [x] Remove from widget once confirmed
- [x] Add `last_confirmed_at DATETIME` column to `bookings` table

---

### Fuel Module
- [x] Show payment method in lists
- [x] Consider showing weekly and monthly as with booking reports
- [x] Add total cost and kms for weekly and monthly
- [x] Keep existing report as is
- [x] Show last 10 logs with "Show All" button

---

### Uber Module
- [x] Additional costs should be multiple, such as add another cost
  - Mobile data to be moved from a separate field to additional cost

---

### General System Improvements
- [x] **Maintenance cleanup:**
  - Mark past overdue bookings as done
  - Add manual cleanup tool in Maintenance section
- [x] **Code cleanup:**
  - Identify and remove unused functions
  - Document remaining functions
  - Consider creating functions.md reference

---

## ✅ Previously Completed (2026-03-05)

### Timezone Audit — Resolved
**Standard adopted:** SAST (UTC+2) throughout — DB stores SAST, PHP displays SAST, no UTC conversion.
`uber_income` and `fuel_logs` TIMESTAMP columns left as-is (not a functional bug, not worth the migration risk).

- [x] Diagnosed root cause: live server MySQL running on SYSTEM (UTC-10), local XAMPP on SYSTEM (SAST) — worked by accident locally
- [x] Fixed: added `$pdo->exec("SET time_zone = '+02:00'");` to `config.php` — forces SAST on all connections, both environments
- [x] Fixed: removed incorrect UTC→SAST double-conversion in `includes/helpers.php` and `modules/Bookings/view.php`
  - Was: `new DateTime($value, new DateTimeZone('UTC'))` then `->setTimezone($timezone)` — added 2 extra hours
  - Fix: `new DateTime($value, new DateTimeZone(TIME_ZONE))` — value from MySQL is already SAST

---

## ✅ Previously Completed (2026-02-28)

### Clients Page: Summary Statistics
- [x] Added stats widget to `modules/Clients/index.php`
- [x] Displays at top of page (below title, before table)
- [x] Shows three metrics: Total Clients, Clients with Bookings, Total Bookings
- [x] Updates via AJAX when filtering ("Show Only With Bookings")
- [x] Styles added to `assets/css/styles.css`

**Implementation Details:**
- Stats container on line 14
- JavaScript calculation lines 102-121
- Real-time updates when toggling filter
- Clean card-based design with stats-grid layout

---

### Uber Income: Additional Costs Tracking
- [x] Added `additional_cost` DECIMAL(10,2) column to `uber_income` table
- [x] Added `cost_reason` VARCHAR(255) column to `uber_income` table
- [x] Created `uber_cost_reasons` table for maintenance
- [x] Updated `modules/Uber/add.php` with additional cost fields
- [x] Updated `modules/Uber/edit.php` with additional cost fields
- [x] Updated `modules/Uber/api/index.php` INSERT and UPDATE handlers
- [x] Updated `modules/Uber/index.php` to display additional costs
- [x] Added cost reasons management to `maintenance/index.php`
- [x] Added cost reasons sync to `maintenance/api/index.php`

**Default Cost Reasons:**
- Car Wash
- Parking
- Tolls
- Car Maintenance
- Other


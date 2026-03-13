# HPTS-XAMPP Development TODO

**Last Updated:** 2026-03-13
**Timezone:** Africa/Johannesburg (UTC+2 / SAST)

---

### Bookings Module Enhancements and Fixes
- [ ] WhatsApp message history tracking (see detailed spec below)
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
- [ ] core/Time.php is used in exactly one place: financials/helper.php, which includes it and calls. Deleted. Note for when we work on financials
- [ ] Update `financials/helper.php` to include `additional_cost` in expense calculations
- [ ] Modify `getWeeklyMetrics()` function
- [ ] Modify `getMonthlyMetrics()` function
- [ ] Update financial dashboard displays
- [ ] Test that additional costs are deducted from net profit

**Calculation:**
```
Total Expenses = Fuel + Car Rental + Additional Costs
Net Profit = Total Income - Total Expenses
```

---

### Send Custom WhatsApp Message to Client
- [ ] Add "Send Message" button to booking view page (`modules/Bookings/view.php`)
- [ ] Add "Send Message" button to client list (`modules/Clients/index.php`)
- [ ] Open a WA msg with Pre-fill client name

---

### WhatsApp Message History (Detailed Spec)
- [ ] Log all WhatsApp confirmations sent
- [ ] Create `whatsapp_log` table with columns:
  - `id` INT AUTO_INCREMENT PRIMARY KEY
  - `booking_id` INT (foreign key)
  - `contact_id` INT (foreign key)
  - `message_type` VARCHAR (confirmation, custom, thank_you, etc.)
  - `message_content` TEXT
  - `sent_at` DATETIME
  - `sent_by` VARCHAR (user identifier)
- [ ] Display message history in booking view page
- [ ] Display message history in client bookings page
- [ ] Useful for tracking "I never got a confirmation" disputes
- [ ] Add filter/search in message log

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
`uber_income` and all booking/fuel timestamps now consistent.

# HPTS-XAMPP Development TODO

**Last Updated:** 2026-03-08
**Timezone:** Africa/Johannesburg (UTC+2 / SAST)

---

### Bookings Module Enhancements and Fixes
- [ ] WhatsApp message history tracking (see detailed spec below)
- [ ] Some clients wants to make a booking on a date, but do not have the time or details. I need ideas how to handle this vie calendar

---

### Clients Module Enhancements and Fixes
- [ ] Show payment method in booking lists
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
#### Uber Additional Costs Integration
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

### Dashboard: Tomorrow's Booking Confirmations Widget
- [ ] Create dashboard widget showing tomorrow's bookings
- [ ] Display bookings where `trip_date = tomorrow` AND not confirmed today
- [ ] "Send Confirmation" button → opens WhatsApp with pre-filled message
- [ ] Mark booking as confirmed when button clicked
- [ ] Remove from widget once confirmed
- [ ] Add `last_confirmed_at DATETIME` column to `bookings` table

**WhatsApp Message Template:**
```
Good evening [Client Name]! 👋

Just confirming your booking for tomorrow:

📅 Date: [Date]
🕐 Pickup Time: [Time]
📍 From: [Pickup Location]
🎯 To: [Destination]

See you tomorrow! 🚗
```

**Workflow:**
- Manual evening routine (no automation)
- Open dashboard → see list → click confirm → send WhatsApp → done
- Simple and effective

**Files to Update:**
- `dashboard.php` (add widget)
- Database migration for `last_confirmed_at` column
- Create helper function for message template

---

### Send Custom WhatsApp Message to Client
- [ ] Add "Send Message" button to booking view page (`modules/Bookings/view.php`)
- [ ] Add "Send Message" button to client list (`modules/Clients/index.php`)
- [ ] Create modal with textarea for custom message
- [ ] Pre-fill client name and phone number
- [ ] Open WhatsApp with custom typed message
- [ ] Store client context (name, phone) in modal

**Modal Design:**
```
┌─────────────────────────────────────┐
│  💬 Send Message to [Client Name]  │
├─────────────────────────────────────┤
│  📱 Phone: [Phone Number]          │
│                                     │
│  Message:                           │
│  ┌─────────────────────────────┐   │
│  │                             │   │
│  │  [Type your message here]   │   │
│  │                             │   │
│  │                             │   │
│  └─────────────────────────────┘   │
│                                     │
│  [Cancel]  [Send via WhatsApp 💬]  │
└─────────────────────────────────────┘
```

**Features:**
- Modal overlay (similar to delete confirmation)
- Textarea for free-form message typing
- Pre-fill with "Hi [Client Name],"
- Button opens WhatsApp Web with typed message
- Works from both booking view and client list
- Escape key to close modal

**Files to Update:**
- `modules/Bookings/view.php` - Add button and modal
- `modules/Clients/index.php` - Add button to each client row
- `assets/css/styles.css` - Modal styles (reuse existing modal styles)

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
- [x] **Maintenance cleanup:**
  - Mark past overdue bookings as done
  - Add manual cleanup tool in Maintenance section
- [x] **Code cleanup:**
  - Identify and remove unused functions
  - Document remaining functions
  - Consider creating functions.md reference

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
- [x] Improve client search functionality:
  - Change to "includes" search (not exact match)
  - Expand search to include phone and address fields
  - Consider fuzzy matching

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

---

### Bookings: View All Client Bookings Button
- [x] Added button to `modules/Bookings/view.php`
- [x] Links to `modules/Clients/bookings.php?id=[contact_id]`
- [x] Shows all bookings for the client of current booking
- [x] Uses existing client bookings page (already in repo)

---

### Bookings: Exclude Completed from Upcoming View
- [x] Modified `modules/Bookings/api/index.php`
- [x] Updated `handleGetBookings()` function
- [x] "Upcoming" mode: `WHERE trip_date >= ? AND status != 'completed'`
- [x] "Show All" mode: Shows everything including completed
- [x] Completed bookings only visible in "Show All Bookings" view

---

### Bookings: Edit Form Improvements
- [x] Added "Other" fields for pickup and destination
- [x] Shows text input when "Other" selected from dropdown
- [x] Added "Add to destinations" checkbox for new locations
- [x] Auto-saves new destinations to database via API
- [x] Updated `modules/Bookings/edit.php`
- [x] Updated `modules/Bookings/api/index.php`

---

### Bookings: Error Handling Improvements
- [x] Better error messages in `modules/Bookings/index.php`
- [x] Shows specific API error responses (not generic "connection error")
- [x] Console logging for debugging
- [x] Fixed duplicate `jsonResponse()` function declaration
- [x] Added missing `createEventDescription()` helper function

---

### Bug Fixes
- [x] Fixed `ROOT_DIR` constant issues in config
- [x] Fixed missing `fetchColumn()` in Uber forms (added `includes/helpers.php`)
- [x] Fixed WhatsApp message timezone display
- [x] Added `require_once ROOT_DIR . '/includes/helpers.php'` to Uber add/edit forms

---

## 📚 Development Guidelines

### Always Remember:
1. **All styles go in `assets/css/styles.css`** - Never inline styles
2. **Use TIME_ZONE constant** - `Africa/Johannesburg` for all date/time operations
3. **Test on both views** - Local (XAMPP) and hosted server
4. **Database changes** - Always provide migration SQL first
5. **Error handling** - Show specific errors, log to console for debugging
6. **WhatsApp links** - Use `formatPhoneNumberForWhatsApp()` helper
7. **Consistent naming** - Follow existing patterns in repo
8. **Timezone standard** - SAST throughout; DB stores SAST, PHP displays SAST, no UTC conversion
9. **MySQL session timezone** - Always set via `SET time_zone = '+02:00'` in `config.php`; never rely on server SYSTEM timezone
10. **New DATETIME columns** - Always use `DATETIME`, never `TIMESTAMP`, for new columns going forward

### Code Patterns:
- API responses: `jsonResponse(['success' => bool, 'message' => string])`
- Logging: `logInfo()`, `logError()`, `logWarning()`, `logCritical()`
- Phone formatting: `formatPhoneNumberForWhatsApp($phone)`
- Escaping: `htmlspecialchars()` for output, PDO prepared statements for queries
- Dates: `new DateTime($value, new DateTimeZone(TIME_ZONE))` — never assume UTC from DB

---

## 🗂️ Key File Locations

### Bookings Module
- `modules/Bookings/index.php` - Main bookings list
- `modules/Bookings/add.php` - Create new booking
- `modules/Bookings/edit.php` - Edit existing booking
- `modules/Bookings/view.php` - Booking details
- `modules/Bookings/api/index.php` - Bookings API
- `modules/Bookings/helpers.php` - Booking helper functions

### Clients Module
- `modules/Clients/index.php` - Clients list
- `modules/Clients/bookings.php` - Client's booking history
- `modules/Clients/api/index.php` - Clients API

### Uber Module
- `modules/Uber/index.php` - Uber income reports
- `modules/Uber/add.php` - Log Uber income
- `modules/Uber/edit.php` - Edit Uber income
- `modules/Uber/api/index.php` - Uber API

### Maintenance
- `maintenance/index.php` - Dropdown lists & system variables
- `maintenance/api/index.php` - Maintenance API

### Financials
- `financials/index.php` - Financial dashboard
- `financials/helper.php` - Financial calculations

### Core
- `config.php` - Database & constants
- `includes/helpers.php` - Global helper functions
- `assets/css/styles.css` - All styles

---

## 💡 Ideas for Future Consideration

- SMS integration as fallback for WhatsApp
- Client preference tracking (WhatsApp vs SMS vs Email)
- Booking analytics dashboard
- Automated backup reminders
- Mobile-responsive improvements
- Calendar sync for multiple drivers
- Customer rating/feedback system
- Push notifications for booking reminders
- Multi-language support
- Client portal for self-service booking

---

**End of TODO.md**
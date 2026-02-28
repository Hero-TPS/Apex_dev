# HPTS-XAMPP Development TODO

**Last Updated:** 2026-02-26  
**Timezone:** Africa/Johannesburg (UTC+2 / SAST)

---

## 🎯 Next Session Priority

### 1. Dashboard: Tomorrow's Booking Confirmations Widget
- [ ] Create dashboard widget showing tomorrow's bookings
- [ ] Display bookings where `trip_date = tomorrow` AND not confirmed today
- [ ] "Send Confirmation" button → opens WhatsApp with pre-filled message
- [ ] Mark booking as confirmed when button clicked
- [ ] Remove from widget once confirmed
- [ ] Add `last_confirmed_at TIMESTAMP` column to `bookings` table

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

### 2. Clients Page: Summary Statistics
- [ ] Add stats widget to `modules/Clients/index.php`
- [ ] Display at top of page (below title, before table)
- [ ] Show three metrics:
  - Total Clients
  - Clients with Bookings
  - Total Bookings (across all clients)
- [ ] Update via AJAX when filtering (e.g., "Show Only With Bookings")
- [ ] Add styles to `assets/css/styles.css`

**Design:**
```
┌─────────────────────────────────────────┐
│  [50]              [35]           [127] │
│  Total Clients     With Bookings  Total │
│                                   Bookings│
└─────────────────────────────────────────┘
```

---

### 3. Send Custom WhatsApp Message to Client
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
├─────────────────��───────────────────┤
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

## 📋 Backlog (Future Sessions)

### High Priority

#### Fix Missing `getSystemVariable()` Function
- [ ] Currently hardcoded values in financials
- [ ] Check Maintenance module for proper implementation
- [ ] Update `financials/helper.php` to use system variables properly

#### Timezone Comprehensive Audit
- [ ] Fix timestamps showing UTC+4 instead of UTC+2
- [ ] Audit all `NOW()` MySQL functions
- [ ] Audit all PHP `DateTime` objects
- [ ] Test on both local (XAMPP) and hosted server
- [ ] Ensure consistency across booking creation, updates, WhatsApp messages

**Note:** Deferred because existing pickup times work correctly. Needs careful testing.

#### Financials: Uber Additional Costs Integration
- [ ] Update `financials/helper.php` to include `additional_cost` in expense calculations
- [ ] Modify `getWeeklyMetrics()` function
- [ ] Modify `getMonthlyMetrics()` function
- [ ] Update financial dashboard displays
- [ ] Test that additional costs are deducted from net profit

**Calculation:**
```
Total Expenses = Fuel + Car Rental + Mobile Data + Additional Costs
Net Profit = Total Income - Total Expenses
```

---

### Bookings Module Enhancements
- [ ] Reduce logging verbosity (tone down on logging every action)
- [ ] WhatsApp message history tracking (see detailed spec below)

---

### Clients Module Enhancements
- [ ] Show payment method in booking lists
- [ ] Improve client search functionality:
  - Change to "includes" search (not exact match)
  - Expand search to include phone and address fields
  - Consider fuzzy matching
- [ ] Create list of clients with prior bookings:
  - Export names and phone numbers
  - Discuss WhatsApp Business group creation
  - Ideas for direct WhatsApp communication with existing clients
- [ ] WhatsApp Business integration for bulk messaging

---

### Fuel Module
- [ ] Show payment method in lists

---

### WhatsApp Message History (Detailed Spec)
- [ ] Log all WhatsApp confirmations sent
- [ ] Create `whatsapp_log` table with columns:
  - `id` INT AUTO_INCREMENT PRIMARY KEY
  - `booking_id` INT (foreign key)
  - `contact_id` INT (foreign key)
  - `message_type` VARCHAR (confirmation, custom, thank_you, etc.)
  - `message_content` TEXT
  - `sent_at` TIMESTAMP
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
- [ ] **Maintenance cleanup:**
  - Remove past overdue bookings (archive vs delete decision)
  - Add manual cleanup tool in Maintenance section
- [ ] **Code cleanup:**
  - Identify and remove unused functions
  - Document remaining functions
  - Consider creating functions.md reference

---

## ✅ Recently Completed (2026-02-26)

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

### Code Patterns:
- API responses: `jsonResponse(['success' => bool, 'message' => string])`
- Logging: `logInfo()`, `logError()`, `logWarning()`, `logCritical()`
- Phone formatting: `formatPhoneNumberForWhatsApp($phone)`
- Escaping: `htmlspecialchars()` for output, PDO prepared statements for queries

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

## 🚀 Starting Next Session

**Copy-paste this to start:**

```
Ready to continue! Reference TODO.md in the repo.

Priority tasks:
1. Dashboard widget for tomorrow's booking confirmations
2. Client stats on clients page
3. Custom WhatsApp message modal

Let's start with #1...
```

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
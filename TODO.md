# HPTS-XAMPP Development TODO

**Last Updated:** 2026-03-14
**Timezone:** Africa/Johannesburg (UTC+2 / SAST)

---

### Bookings Module Enhancements and Fixes
- [ ] Some clients wants to make a booking on a date, but do not have the time or details. I need ideas how to handle this via calendar
  - idea prebooking and reminders
  - use another calendar
  - turn reminders into bookings

---

### Clients Module Enhancements and Fixes
- [ ] Create list of clients with prior bookings:
  - Export names and phone numbers
  - Discuss WhatsApp Business group creation
  - Ideas for direct WhatsApp communication with existing clients
- [ ] WhatsApp Business integration for bulk messaging

---
### Group management/duplicate clients
- [ ] Currently it is a still a seperate folder. it is a sub of clients module, but it also going to use data from bookings
  - whole idea is to deal with duplicated clients.
- [ ] Move it to a new module.
- [ ] Show which duplicate already has bookings, that way I can decide which one to delete.



---

### Fuel Module Enhancements and Fixes
- [x] fuel reports: 
  - add cost/km
  - efficiancy in both km/l and l/100km. Can be done on one line. Also add this to financials.

---

### Uber Module Enhancements and Fixes
- [x] Currently, I can only add the current week's uber log after end of day on Sunday . Want to be able to add an uber log on Sunday as well, even if the day is not done (End of work week for me).
 - Keep in mind Uber period does not change, it is still from start of day on Monday till end of day on Sunday.
 - The way it is done now is old code.

---

### Driver Module Enhancements and Fixes

---

### Maintanance Enhancements and Fixes

#### Logs
- [ ] Logs are still to verbrose, being cluttered up with actions marked as info, but show now actual errors logs, those are still shown on page views.
- [ ] Filter select : Page not found.

---

### Financials:

#### Backport financials overview structure
- [x] Use the same structure in Bookings, fuel and Uber reports having a month view and weeks are a toggle.
  - it is also a change from table view to block view.
  - use the same financial history from system variables

#### Balance sheets. This is the big one:
- [x] Create a monthly balance sheet report. with credit and debits.
  - it will only be filled with money in and out info. and totals at the end.
  - if it is a eft or cash payment must show.
  - stylng is more laptop related than mobile, as I need to create PDF's on A4 sheets (by using Windows print to pdf, no extra function needed)
  - additional link for this under finances.
  - default reporting months follow the setting in maintainace.
  - Will be used for general reporting and credit applications, etc.
- [x] Add to top of ballance sheet. Summary report.
  -all the similar entries grouped together with total
---

### General System Improvements
- [x] **Breadcrumbs:** Check consistency across all pages
  - Created `buildBreadcrumb()` helper function in `includes/helpers.php`
  - Fixed missing breadcrumb in `maintenance/index.php`
  - Fixed wrong order in `modules/Bookings/reports.php` (was `> Reports > Bookings`)
  - Fixed missing parent segment in `modules/Bookings/add.php`
  - Added parent links to all multi-segment breadcrumbs across all modules
  - Standardised "Add New" → "Add" in `modules/Clients/add.php`
  - Fixed include order in `modules/Clients/bookings.php` (helpers loaded before header)


## NEXT CYCLE

### Drivers
#### Start thinking of how to deal with additional car drivers, mark bookings as such and commission taken
- [ ] Driver has an account 
- [ ] Admin or manager role can assign bookings to drivers
- [ ] Set up a commision persent field in maintanance
- [ ] set up role permission for a role that does not pay commision (such as admin)
- [ ] if driver is asigned a booking 
    - the booking is not added as income, only the commision
    - booking is removed from calendar
    - booking is still show in list, but marked as assigned
    - when a booking is created, then it can also be emmediatly asigned
- [ ] Admin overview of driver bookings
    -drivers has access to their own list of upcomming bookings

### Access Control
- [ ] Similar to dupal access control
  - Admin user. Superuser
  - access control to pages, based on roles. need a set up page for this.
  - users with roles


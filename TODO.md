# HPTS-XAMPP Development TODO

**Last Updated:** 2026-03-14
**Timezone:** Africa/Johannesburg (UTC+2 / SAST)

---

### Bookings Module Enhancements and Fixes
- [ ] Some clients wants to make a booking on a date, but do not have the time or details. I need ideas how to handle this via calendar
  - idea prebooking and reminders
  - use another calendar
  - turn reminders into bookings
- [ ] Start thinkng of how to deal with additional car drivers, mark bookings as such and commission taken

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
- [ ] Show which duplicate already has bookings, that why I can decide which one to delete.



---

### Fuel Module Enhancements and Fixes

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
- [ ] Use the same structure in Bookings, fuel and Uber reports having a month view and weeks are a toggle.
  - it is also a change from table view to block view.
  - use the same financial history from system variables

---

### General System Improvements
- [ ] **Breadcrumbs:** Check consistency across all pages
  - Consider creating helper function for breadcrumb generation
  - OR implement proper main menu navigation
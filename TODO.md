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
- [ ] Group management still to be discussed.

---

### Fuel Module Enhancements and Fixes

---

### Uber Module Enhancements and Fixes
- [ ] Currently, I can only add the current week's uber log after end of day on Sunday . Want to be able to add an uber log on Sunday as well, even if the day is not done (End of work week for me).
 - Keep in mind Uber period does not change, it is still from start of day on Monday till end of day on Sunday.
 - The way it is done now is old code.

---

### Driver Module Enhancements and Fixes

---

### Maintanance Enhancements and Fixes

#### Logs
- [ ] Logs are still to verbrose, being cluttered up with actions marked as info, but show now actual errors logs, those are still shown on page views.

---

### Financials:

#### Backport finance overview structure
- [ ] Use the same structure in Bookings, fuel and Uber report have having a month view and weeks are a toggle.

---

### General System Improvements
- [ ] **Breadcrumbs:** Check consistency across all pages
  - Consider creating helper function for breadcrumb generation
  - OR implement proper main menu navigation
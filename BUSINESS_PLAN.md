# Apex Transit — Vehicle Acquisition Business Plan
## Session Notes & Working Document
**Last Updated:** March 2026
**Status:** In Progress

---

## Context

This document captures the findings and decisions from a business planning session aimed at replacing the current rental vehicle with an investor-funded owned vehicle.

- **Business Name:** Apex Transit
- **System:** HPTS-XAMPP (business management system)
- **Business Name Constant:** `BUSINESS_NAME` in `config.php` — change to `'Apex Transit'` to update everywhere
- **Planning Horizon:** 36 months (3 years)
- **Reason for 3-year limit:** High monthly km (~4,809/month) means ~180,000 km on clock at end of term — vehicle must be sold/replaced

---

## Key Financial Data

### System Notes
- Car rental cost stored as `car_rental_price` system variable in `system_variables` table
- Default value: **R2,600/week**
- Monthly rental calculated as: `R2,600 × billing weeks in month`
- 4-week month = R10,400 | 5-week month = R13,000
- Monthly average (4.33 weeks): **~R11,267/month**

---

### 3-Month Actuals (December 2025 – February 2026)

| Item | Dec 2025 | Jan 2026 | Feb 2026 | Monthly Average |
|---|---|---|---|---|
| Uber Income | R13,935.59 | R11,551.70 | R10,559.15 | R12,015.48 |
| Booking Income | R21,380.00 | R16,200.00 | R22,995.00 | R20,191.67 |
| **Total Income** | **R35,315.59** | **R27,751.70** | **R33,554.15** | **R32,207.15** |
| Fuel | R7,394.27 | R7,417.00 | R7,510.00 | R7,440.42 |
| Car Rental | R13,000.00 | R10,400.00 | R10,400.00 | R11,266.67 |
| Uber Costs | R0.00 | R1,550.00 | R1,430.00 | R993.33 |
| **Total Expenses** | **R20,394.27** | **R19,367.00** | **R19,340.00** | **R19,700.42** |
| **Net Profit** | **R14,921.32** | **R8,384.70** | **R14,214.15** | **R12,506.72** |
| Total km | 4,698.9 | 4,725.5 | 5,003.0 | **4,809.1** |
| Total Trips | 255 | 206 | 204 | **221.7** |

### Average Monthly Income Breakdown (3-month basis)

| Item | Amount | % of Income |
|---|---|---|
| Total Income | R32,207.15 | 100% |
| Fuel | R7,440.42 | 23.1% |
| Car Rental | R11,266.67 | 35.0% |
| Uber Costs | R993.33 | 3.1% |
| **Net Profit** | **R12,506.72** | **38.8%** |

---

## Vehicle Requirements

| Requirement | Detail |
|---|---|
| Body type | Sedan, comfort class |
| Boot space | Must fit wheelchair OR 3 large suitcases |
| Passenger space | 3 passengers with legroom |
| Maximum age | Not older than 4 years (2022 or newer) |
| Maximum mileage | ≤ 50,000 km on the clock |
| Budget | ~R250,000 (ballpark) |

---

## Vehicle Shortlist & Service Plans Remaining at 50,000 km

| Vehicle | Est. Price | Service Plan | km Remaining at 50k | Months Left at 4,809/mo | Verdict |
|---|---|---|---|---|---|
| **Kia Pegas 1.4 EX Auto** | ~R245,000–R260,000 | 5yr / 90,000 km | **40,000 km** | **~8 months** | ✅ Best remaining |
| **Kia Sonet** | ~R280,000–R320,000 | 5yr / 90,000 km | **40,000 km** | **~8 months** | ✅ Best remaining |
| **Suzuki Ciaz 1.5 GLX Auto** | ~R260,000–R270,000 | 3yr / 60,000 km | **10,000 km** | **~2 months** | ⚠️ Nearly gone |
| **VW Polo Sedan 1.6 Auto** | ~R252,000–R300,000 | 1yr / 15,000 km | **0 km** | **Expired** | ❌ Nothing left |
| **Toyota Corolla 1.8 XS** | ~R300,000–R350,000 | 3yr / 45,000 km | **0 km** | **Expired** | ❌ Nothing left |

### Key Insight — Kia Advantage
The Kia Pegas/Sonet 5yr/90,000 km service plan gives ~8 months of free services worth approximately **R12,824** (8 × R1,603) — reducing or eliminating the need to provision for services in the first 8 months.

---

## Service & Maintenance Planning

| Item | Value |
|---|---|
| Service interval | Every 15,000 km |
| Monthly km average | 4,809 km |
| **Months between services** | **15,000 ÷ 4,809 = 3.12 months** |
| Estimated service cost (sedan, independent) | R5,000–R6,000 per service |
| **Monthly provision (R5,000 ÷ 3.12)** | **R1,603/month** |

### Service Plan Options
- Standard plans (60,000 km) expire in ~12–13 months at this usage
- Full 36-month coverage not available via standard plans at this km
- **Recommended:** Roll ~R10,000 service plan into investment ask to cover first 12 months, provision monthly thereafter

---

## Ownership Running Costs (Monthly, excl. fuel)

| Item | Monthly Cost |
|---|---|
| Insurance (comprehensive + public transport) | R2,100 |
| Service provision (R5,000 ÷ 3.12 months) | R1,603 |
| Tyres & general wear | R400 |
| Licensing | R150 |
| **Total running costs** | **R4,253** |

> Fuel is excluded — it is a separate operational business expense that exists regardless of rental or ownership.

---

## Investment Structure

### Core Formula
> **Total investor receives = Investment × (1 + return%)**  
> **Monthly payment = (Total − Resale proceeds) ÷ 36**

### Assumptions
- Investment amount: **R250,000**
- Resale at 36 months / projected km: **R60,000–R120,000 range** (finalised once a
  specific vehicle is selected — see [RESALE_VALUE_METHOD.md](RESALE_VALUE_METHOD.md))
- Investor receives 100% of resale proceeds
- Owner receives 0 from resale (monthly cash flow priority chosen over lump sum at end)
- Repayment period: **36 months**

### Why 100% Resale to Investor
At month 36 the car is sold. Two options were considered:
- **Investor gets 100% resale** → lower monthly payment, owner gets nothing at end but keeps +R1,042–R2,708/month (depending on resale)
- **50/50 resale split** → monthly payment rises toward break-even, owner gets ~R50,000 at end
- **Decision: 100% to investor** — monthly cash flow was the priority

### Chosen Structure — 10% Total Return (Example at R80,000 Resale)

| Item | Calculation | Value |
|---|---|---|
| Total investor receives | R250,000 × 1.10 | R275,000 |
| Less resale (example: R80,000) | −R80,000 | |
| Via monthly payments | R195,000 | |
| **Monthly investor payment** | R195,000 ÷ 36 | **R5,417/month** |
| Monthly saving vs rental | R11,267 − R5,417 − R4,253 | **+R1,597** |

> The R80,000 resale is used here as a conservative planning figure.
> See [RESALE_VALUE_METHOD.md](RESALE_VALUE_METHOD.md) for how the final resale
> is determined once a specific vehicle is selected.

### Resale Sensitivity — Monthly Payment vs Resale Outcome (R250,000, 10%)

| Resale Value | Monthly Payment | Monthly Saving vs Rental | Notes |
|---|---|---|---|
| R120,000 | R4,306 | +R2,708 | Optimistic — low mileage, strong model |
| R100,000 | R4,861 | +R2,153 | Mid-range estimate |
| R80,000 | R5,417 | +R1,597 | Conservative planning figure |
| R60,000 | R5,972 | +R1,042 | Worst case — high mileage, weak model |

> Monthly saving calculated after R4,253 running costs. Deal is cash-flow positive at all scenarios.

---

## Income Comparison — Rental vs Ownership (3-Month Average Basis)

> The ownership column below uses the R80,000 conservative resale assumption
> (monthly payment R5,417). Adjust the investor payment row for other scenarios
> — see the sensitivity table above.

| Item | Rental | Ownership | Difference |
|---|---|---|---|
| Total Income | R32,207.15 | R32,207.15 | — |
| Fuel | R7,440.42 | R7,440.42 | — |
| Car Rental / Vehicle Costs | R11,266.67 | R9,670.00 | **−R1,596.67** |
| Uber Costs | R993.33 | R993.33 | — |
| **Net Profit** | **R12,506.72** | **R14,103.40** | **+R1,596.68** |
| **% of Income** | **38.8%** | **43.8%** | **+5.0%** |

> Vehicle Costs (ownership) = R5,417 investor payment + R4,253 running costs = R9,670.
> At R100,000 resale: payment = R4,861, vehicle costs = R9,114, saving = +R2,153/month.

### Vehicle Cost as % of Income
| | Rental | Ownership (R80k resale) | Ownership (R100k resale) |
|---|---|---|---|
| Vehicle cost | 35.0% | 30.0% | 28.3% |
| Net profit | 38.8% | **43.8%** | **43.0%** |

---

## 36-Month Outcome

| Item | R80k Resale | R100k Resale |
|---|---|---|
| Extra per month during repayment | **+R1,597** | **+R2,153** |
| Over 36 months total | **+R57,492** | **+R77,508** |
| Position at month 37 | Car sold, investor paid, start again | Car sold, investor paid, start again |
| Long-term saving post-repayment | N/A — car sold at month 36 | N/A — car sold at month 36 |

> **Important:** The "after repayment" permanent saving does not materialise because the vehicle must be sold at 36 months due to high mileage. The real benefit is the monthly saving across the 36-month term — which ranges from +R1,042/month (R60k resale) to +R2,708/month (R120k resale). See the sensitivity table for all scenarios.

---

## Investor ROI — Return % Comparison (R250,000, R80,000 resale assumed, 36 months)

> Payments below use R80,000 as the conservative resale. "You Keep/Month" is after R4,253 running costs.
> At R100,000 resale the payment is R4,861/month; see sensitivity table for full range.

| Return % | Monthly Payment | You Keep/Month | Investor Rand Profit | Notes |
|---|---|---|---|---|
| 5% | R5,069 | +R1,945 | R12,500 | Low — family/friend only |
| **10%** | **R5,417** | **+R1,597** | **R25,000** | ✅ Recommended |
| 15% | R5,764 | +R1,250 | R37,500 | Investor-favoured |
| 20% | R6,111 | +R903 | R50,000 | Too tight |

---

## Investor One-Pager Summary

**Investment:** R250,000  
**Monthly repayment:** R4,306–R5,972 (depends on resale — see [RESALE_VALUE_METHOD.md](RESALE_VALUE_METHOD.md))  
**Period:** 36 months  
**Resale at end:** R60,000–R120,000 range — finalised once vehicle is selected  
**Total investor receives:** R275,000  
**Investor profit:** R25,000 (10%)  
**Business improvement:** +R1,042–R2,708/month from day one (depending on resale)  
**Security:** Comprehensive insurance, full transaction records available

---

## Key Decisions Made This Session

| Decision | Choice | Reason |
|---|---|---|
| Business name | **Apex Transit** | Rebrand from HPTS |
| Planning horizon | **36 months** | High km — car sold at ~180,000 km |
| Priority | **Monthly cash flow** | Not lump sum at end |
| Resale split | **100% to investor** | Keeps monthly payment lower |
| Target return | **10%** | Fair for investor, manageable for business |
| Fuel in vehicle costs | **No** | Separate operational expense |
| Vehicle preference | **Kia Pegas** (leading) | Lowest cost + best remaining service plan |
| Service plan strategy | Roll into investment | Reduces monthly uncertainty |

---

## Corrections Made During Session

- ❌ Initial monthly km estimate of 1,200 km was wrong — was a weekly figure
- ✅ Corrected to 4,809 km/month (3-month average from actual data)
- ❌ "Rental is 73% of net profit" statement was wrong — net profit is AFTER rental deduction
- ✅ Corrected: rental is 35% of total income
- ❌ Earlier savings calculations used Feb-only rental (R10,400) not average (R11,267)
- ✅ Corrected to use 3-month average throughout

---

## Open Items / Next Steps

- [ ] Confirm actual monthly km from Fuel module (3-month average preferred)
- [ ] Decide on final vehicle from shortlist
- [ ] Decide whether to include service plan in investment ask (+R10,000 → R260,000)
- [ ] Present one-pager to potential investor
- [ ] Update `config.php` → `define('BUSINESS_NAME', 'Apex Transit');`
- [ ] When starting next session — share latest 3 months of financial data for updated calculations

---

## How to Resume This Plan in a New Session

1. Share this file with Copilot at the start of the session
2. Pull latest 3 months of financial data from the Financials module
3. Update the 3-month averages table above
4. Recalculate monthly investor payment using updated rental average and km
5. Update the one-pager with new figures before presenting to investor

---

*Document generated from Copilot planning session — March 2026*
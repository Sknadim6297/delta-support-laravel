# Phase 3 — Customer Tour Interest & Request Management

**Date:** 2026-11-24  
**Mode:** Local only. Enquiry/callback module — not booking or payment.  
**Protected:** ROI, Level Income, payment controls, wallets, scheduler, Rewards, public site unchanged. Financial auth flags remain disabled.

---

## Verdict

Members can toggle tour interest from the dashboard; admins manage requests under **Tour Requests** with KPIs, filters, export, Call/WhatsApp, status workflow, internal notes, and history. In-app notifications reuse `support_notifications`.

---

## Database (additive)

Migration: `2026_11_24_150000_create_tour_interest_request_tables.php`

| Table | Purpose |
| --- | --- |
| `tour_interest_requests` | Requests; `active_marker='1'` for pending/contacted/confirmed (unique per member) |
| `tour_interest_request_events` | Immutable status/history |
| `support_notifications` | Adds nullable `tour_interest_request_id`; `support_ticket_id` made nullable (MySQL / sqlite rebuild) |

Statuses: `pending`, `contacted`, `confirmed`, `completed`, `cancelled`.

---

## Routes

| Method | URI | Name |
| --- | --- | --- |
| POST | `/member/tour-interest` | `member.tour-interest.enable` |
| POST | `/member/tour-interest/withdraw` | `member.tour-interest.withdraw` |
| GET | `/admin/tour-requests` | `admin.tour-requests.index` |
| GET | `/admin/tour-requests/export` | `admin.tour-requests.export` |
| GET | `/admin/tour-requests/{tourRequest}` | `admin.tour-requests.show` |
| POST | `/admin/tour-requests/{tourRequest}` | `admin.tour-requests.update` |

---

## Key code

- `TourInterestService` — create (idempotent), withdraw, status update, KPIs, packages snapshot, notifications  
- Controllers: `Member\TourInterestController`, `Admin\TourInterestRequestController`  
- UI: member dashboard card; admin index/show; sidebar badge pending count  

Duplicate active requests blocked by row lock + unique `(member_id, active_marker)` + unique-violation catch.

---

## Notifications

- New pending → admin `support_notifications` (in-app; email skipped)  
- Status change → member notice  
- Header links tour notices to tour request / dashboard  
- Sidebar shows pending count on Tour Requests  

Internal `admin_notes` never rendered on member views.

---

## Tests & build

```
TourInterestRequestTest: 4 passed
Full suite: 666 passed (6719 assertions)
Vite production build: SUCCESS (panel-v2 CSS includes nav count)
```

---

## Rollback

1. Remove routes/sidebar/dashboard card.  
2. `migrate:rollback` for this migration (drops tour tables; restores notifications carefully).  
3. No financial or wallet data is touched by this module.

---

## Screenshots

Capture locally after MySQL migrate when reviewing:

1. Member dashboard — Interested in a Tour? (off / on + confirmation)  
2. Admin Tour Requests — KPI row + table  
3. Admin request detail — status form + history  
4. Admin sidebar pending badge + notification dropdown  

---

## Non-actions

No deploy, no production migrate, no financial flag changes, no ROI/Level/payment-control edits.

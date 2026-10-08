---
paths:
  - 'app/**'
---

# App

## A student's status is one of StudentStatus's five, changed only through StudentStatusService
Decided with Faisal on 5 Oct 2026: active مشارك, registering تحت التسجيل, suspended موقوف, inactive غير فعّال (out of his cohort, still the academy's), left غادر الأكاديمية. Labels, badge colours, options and validation come from App\Support\StudentStatus (Rule::enum, <x-student-status-badge>) — never a local map. Write a status only via StudentStatusService::changeStatus (it refuses other words and writes the history row); a new student starts registering with his first history row. "Was he مشارك on day X" is StudentStatusService::countsAsActive / Attendance::activeStatusOnDateSql — no row in force means his column answers.

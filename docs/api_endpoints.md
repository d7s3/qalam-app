# دليل واجهات برمجة التطبيقات (API Reference) - المعلم والتحضير

يوفر هذا المستند توثيقاً للمنافذ البرمجية (API Endpoints) الخاصة بتسجيل دخول المعلمين وتحضير الطلاب.

---

## 1. تسجيل دخول المعلم (Teacher Login)

- **المسار:** `/api/teacher/login`
- **البروتوكول:** `POST`
- **الترويسة (Headers):** 
  - `Accept: application/json`
  - `Content-Type: application/json`

### جسم الطلب (Request Body):
```json
{
  "email": "teacher@example.com",
  "password": "password",
  "device_name": "iphone_15"
}
```
*(ملاحظة: حقل `device_name` اختياري ويستخدم لتسمية توكن التوثيق)*

### رد النجاح (200 OK):
```json
{
  "token": "1|abcdef123456...",
  "teacher": {
    "id": 1,
    "name": "معلم تجريبي",
    "email": "teacher@example.com",
    "phone": "123456789",
    "is_approved": true
  }
}
```

### رد الخطأ - بيانات خاطئة (401 Unauthorized):
```json
{
  "message": "بيانات الدخول غير صحيحة."
}
```

### رد الخطأ - حساب غير مفعل (403 Forbidden):
```json
{
  "message": "لم يتم تفعيل حسابك من قبل الإدارة بعد."
}
```

---

## 2. عرض الحلقات والتحضير (Fetch Circles & Students)

يستخدم لجلب قائمة الحلقات المرتبطة بالمعلم، وقائمة طلاب الحلقة المختارة مع حالة حضورهم الحالية، أو لمزامنة البيانات محلياً.

- **المسار:** `/api/teacher/attendance`
- **البروتوكول:** `GET`
- **الترويسة (Headers):**
  - `Accept: application/json`
  - `Authorization: Bearer <TOKEN>`

### معاملات الاستعلام (Query Parameters):
- `circle_id` (اختياري - integer): معرف الحلقة المراد جلب طلابها.
- `date` (اختياري - string): تاريخ التحضير بتنسيق `YYYY-MM-DD` (القيمة الافتراضية: تاريخ اليوم).
- `last_synced_at` (اختياري - string): توقيت آخر مزامنة بتنسيق ISO 8601 (مثل `2026-06-21T18:00:00Z`). عند إرساله، سيعيد الـ API السجلات المحدثة أو المضافة بعد هذا التوقيت فقط لجميع حلقات المعلم.

### رد النجاح - الاستعلام العام (بدون تحديد حلقة وبدون مزامنة):
```json
{
  "circles": [
    {
      "id": 1,
      "name": "حلقة الإيمان",
      "description": "مخصصة للمرحلة الثانوية"
    }
  ],
  "selected_circle": null,
  "students": []
}
```

### رد النجاح - عند إرسال معرف الحلقة والتاريخ:
`/api/teacher/attendance?circle_id=1&date=2026-06-21`
```json
{
  "circles": [
    {
      "id": 1,
      "name": "حلقة الإيمان",
      "description": "مخصصة للمرحلة الثانوية"
    }
  ],
  "selected_circle": {
    "id": 1,
    "name": "حلقة الإيمان",
    "description": "مخصصة للمرحلة الثانوية"
  },
  "students": [
    {
      "id": 5,
      "name": "طالب تجريبي",
      "circle_id": 1,
      "attendance_status": "present"
    }
  ]
}
```
*(ملاحظة: قيم `attendance_status` الممكنة هي: `present`, `absent`, `late`, `excused` أو نص فارغ `""` في حال لم يحضر الطالب بعد).*

### رد النجاح - عند المزامنة التدريجية (باستخدام `last_synced_at`):
`/api/teacher/attendance?last_synced_at=2026-06-21T18:00:00Z`
```json
{
  "circles": [
    {
      "id": 1,
      "name": "حلقة الإيمان",
      "description": "مخصصة للمرحلة الثانوية"
    }
  ],
  "students": [
    {
      "id": 5,
      "name": "طالب تجريبي",
      "circle_id": 1,
      "attendance_status": ""
    }
  ],
  "attendances": [
    {
      "id": 10,
      "student_id": 5,
      "teacher_id": 1,
      "circle_id": 1,
      "date": "2026-06-21",
      "status": "present",
      "notes": null,
      "updated_at": "2026-06-21T18:30:00.000000Z"
    }
  ],
  "server_time": "2026-06-21T18:45:25.000000Z"
}
```
*(ملاحظة: حقل `attendance_status` في الطلاب المسترجعين عند المزامنة التدريجية يكون فارغاً، حيث يتم الاعتماد على مصفوفة الـ `attendances` المرفقة لبناء السجل المحلي).*

### رد الخطأ - حلقة غير تابعة للمعلم (403 Forbidden):
```json
{
  "message": "غير مصرح لك بالوصول لهذه الحلقة."
}
```

---

## 3. رصد وحفظ حضور الطلاب (Mark Student Attendance)

يستخدم لحفظ أو تحديث حضور وغياب الطلاب في حلقة وتاريخ محددين، أو لمزامنة البيانات محلياً.

- **المسار:** `/api/teacher/attendance`
- **البروتوكول:** `POST`
- **الترويسة (Headers):**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer <TOKEN>`

### جسم الطلب (Request Body):

يدعم المنفذ طريقتين للطلب لضمان توافقية الأنظمة:

#### الطريقة الأولى: الإرسال البسيط لمجموعة طلاب (Associative Array)
```json
{
  "circle_id": 1,
  "date": "2026-06-21",
  "records": {
    "5": "present",
    "6": "late",
    "7": "absent"
  }
}
```
*(ملاحظة: هيكل الـ `records` هو عبارة عن قاموس يربط معرف الطالب `student_id` بحالة الحضور المقررة له: `present`, `absent`, `late`, `excused`).*

#### الطريقة الثانية: المزامنة الدفعية للمحلي أولاً (Local-First Batch Sync)
تسمح بمزامنة سجلات لتواريخ وحلقات مختلفة مع دعم فرز التعارضات بالتوقيت:
```json
{
  "records": [
    {
      "student_id": 5,
      "status": "present",
      "date": "2026-06-21",
      "updated_at": "2026-06-21T18:30:00Z"
    },
    {
      "student_id": 6,
      "status": "absent",
      "date": "2026-06-20",
      "updated_at": "2026-06-21T18:25:00Z"
    }
  ]
}
```
*(ملاحظة: يقوم النظام بتطبيق خوارزمية **"آخر كتابة تفوز" (Last-Write-Wins)** بمقارنة `updated_at` الممررة مع نظيرتها في قاعدة البيانات. يتم تخطي التحديث إذا كانت النسخة على السيرفر أحدث).*

### رد النجاح (200 OK):
```json
{
  "message": "تم تسجيل حضور الطلاب بنجاح.",
  "synced_attendances": [
    {
      "id": 10,
      "student_id": 5,
      "teacher_id": 1,
      "circle_id": 1,
      "date": "2026-06-21",
      "status": "present",
      "notes": null,
      "updated_at": "2026-06-21T18:45:25.000000Z"
    }
  ]
}
```

### رد الخطأ - عدم صلاحية الحلقة (403 Forbidden):
```json
{
  "message": "غير مصرح لك بالوصول لهذه الحلقة."
}
```

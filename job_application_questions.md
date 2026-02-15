سأقوم بإنشاء نظام إدارة أسئلة طلبات التوظيف (Job Application Questions) الكامل.Created 4 files, ran a commandCreating directory structure for job application questionsScriptCreating Job Application Questions RepositoryPdoJobApplicationQuestionsRepository.phpCreating Job Application Questions ValidatorJobApplicationQuestionsValidator.phpCreating Job Application Questions Service layerJobApplicationQuestionsService.phpCreating Job Application Questions ControllerJobApplicationQuestionsController.phpDoneالآن سأقوم بإنشاء ملف API بالمسار المطلوب:Creating API endpoint file with specified routeCreating API endpoint file with specified routeScriptDoneالآن سأقوم بنسخ كل شيء إلى outputs وتقديم الملفات:Ran a command, read a fileCopying job application questions to outputsScriptPresented 5 filesDoneتم إنشاء نظام إدارة أسئلة طلبات التوظيف الكامل بنجاح! 🎉
📦 الملفات المُنشأة:
1. Repository Layer (PdoJobApplicationQuestionsRepository.php)
✅ العمليات الأساسية:

إدارة الأسئلة الكاملة CRUD
فلاتر متقدمة (job_id, question_type, is_required, search)
الترتيب المخصص (sort_order)
الحصول على أسئلة وظيفة معينة
الحصول على الأسئلة المطلوبة فقط

✅ الوظائف المتقدمة:

getByJob() - جميع أسئلة وظيفة معينة
updateSortOrder() - تحديث الترتيب
reorder() - إعادة ترتيب جماعي
deleteByJob() - حذف جميع أسئلة وظيفة
duplicateFromJob() - نسخ الأسئلة من وظيفة لأخرى
getQuestionTypes() - أنواع الأسئلة المتاحة

2. Validator Layer (JobApplicationQuestionsValidator.php)
✅ التحقق من جميع الحقول
✅ التحقق من أنواع الأسئلة المسموحة
✅ التحقق من صحة الخيارات (Options) للأسئلة التي تحتاجها
✅ التحقق من بيانات إعادة الترتيب
✅ دعم JSON للخيارات
3. Service Layer (JobApplicationQuestionsService.php)
✅ منطق الأعمال الكامل
✅ إنشاء جماعي للأسئلة (Bulk Create)
✅ نسخ الأسئلة من وظيفة لأخرى
✅ إدارة الترتيب
4. Controller Layer (JobApplicationQuestionsController.php)
✅ واجهة واضحة للـ API
✅ جميع العمليات CRUD
✅ العمليات الجماعية
✅ إدارة الترتيب
5. API Endpoint (job_application_questions.php)
✅ المسار المطلوب: api/routes/job_application_questions
🎯 أنواع الأسئلة المدعومة:
javascriptconst QUESTION_TYPES = [
  'text',         // نص قصير
  'textarea',     // نص طويل
  'select',       // قائمة منسدلة (اختيار واحد)
  'multiselect',  // قائمة منسدلة (اختيارات متعددة)
  'radio',        // اختيار واحد (Radio buttons)
  'checkbox',     // اختيارات متعددة (Checkboxes)
  'file',         // رفع ملف
  'date',         // تاريخ
  'number'        // رقم
];
📡 API Endpoints الرئيسية:
1. قائمة الأسئلة
javascriptGET /api/routes/job_application_questions?job_id=5
2. أسئلة وظيفة معينة
javascriptGET /api/routes/job_application_questions?job_id=5&by_job=1

// الأسئلة المطلوبة فقط
GET /api/routes/job_application_questions?job_id=5&by_job=1&required_only=1
3. أنواع الأسئلة المتاحة
javascriptGET /api/routes/job_application_questions?question_types=1
4. إنشاء سؤال
javascriptPOST /api/routes/job_application_questions
{
  "job_id": 5,
  "question_text": "ما هي سنوات خبرتك في البرمجة؟",
  "question_type": "number",
  "is_required": 1,
  "sort_order": 1
}

// سؤال مع خيارات
POST /api/routes/job_application_questions
{
  "job_id": 5,
  "question_text": "ما هي لغة البرمجة المفضلة لديك؟",
  "question_type": "select",
  "options": [
    {"value": "php", "label": "PHP"},
    {"value": "python", "label": "Python"},
    {"value": "javascript", "label": "JavaScript"}
  ],
  "is_required": 1,
  "sort_order": 2
}
5. إنشاء جماعي
javascriptPOST /api/routes/job_application_questions
{
  "bulk_create": true,
  "job_id": 5,
  "questions": [
    {
      "question_text": "الاسم الكامل",
      "question_type": "text",
      "is_required": 1,
      "sort_order": 1
    },
    {
      "question_text": "سنوات الخبرة",
      "question_type": "number",
      "is_required": 1,
      "sort_order": 2
    },
    {
      "question_text": "رفع السيرة الذاتية",
      "question_type": "file",
      "is_required": 1,
      "sort_order": 3
    }
  ]
}
6. نسخ الأسئلة من وظيفة أخرى
javascriptPOST /api/routes/job_application_questions
{
  "duplicate_from": true,
  "source_job_id": 5,
  "target_job_id": 10
}
7. تحديث سؤال
javascriptPUT /api/routes/job_application_questions
{
  "id": 123,
  "question_text": "ما هي مهاراتك البرمجية؟",
  "question_type": "textarea",
  "is_required": 0
}
8. إعادة الترتيب (Drag & Drop)
javascriptPATCH /api/routes/job_application_questions?action=reorder
{
  "order": [
    {"id": 1, "sort_order": 1},
    {"id": 3, "sort_order": 2},
    {"id": 2, "sort_order": 3},
    {"id": 4, "sort_order": 4}
  ]
}
9. حذف سؤال
javascriptDELETE /api/routes/job_application_questions
{
  "id": 123
}
10. حذف جميع أسئلة وظيفة
javascriptDELETE /api/routes/job_application_questions?job_id=5&delete_all=1
💡 أمثلة عملية:
1. إنشاء نموذج طلب توظيف كامل
javascriptconst questions = [
  {
    question_text: "الاسم الكامل",
    question_type: "text",
    is_required: 1,
    sort_order: 1
  },
  {
    question_text: "البريد الإلكتروني",
    question_type: "text",
    is_required: 1,
    sort_order: 2
  },
  {
    question_text: "سنوات الخبرة",
    question_type: "number",
    is_required: 1,
    sort_order: 3
  },
  {
    question_text: "المؤهل العلمي",
    question_type: "select",
    options: [
      {value: "high_school", label: "ثانوية عامة"},
      {value: "bachelor", label: "بكالوريوس"},
      {value: "master", label: "ماجستير"},
      {value: "phd", label: "دكتوراه"}
    ],
    is_required: 1,
    sort_order: 4
  },
  {
    question_text: "المهارات البرمجية",
    question_type: "multiselect",
    options: ["PHP", "JavaScript", "Python", "Java", "C#"],
    is_required: 0,
    sort_order: 5
  },
  {
    question_text: "رفع السيرة الذاتية",
    question_type: "file",
    is_required: 1,
    sort_order: 6
  }
];

// إنشاء جميع الأسئلة دفعة واحدة
await fetch('/api/routes/job_application_questions', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    bulk_create: true,
    job_id: 5,
    questions: questions
  })
});
2. نسخ الأسئلة عند إنشاء وظيفة مشابهة
javascript// نسخ جميع الأسئلة من وظيفة موجودة
await duplicateQuestions(sourceJobId, newJobId);

async function duplicateQuestions(sourceId, targetId) {
  const response = await fetch('/api/routes/job_application_questions', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      duplicate_from: true,
      source_job_id: sourceId,
      target_job_id: targetId
    })
  });
  
  return await response.json();
}
🌟 الميزات الخاصة:

✅ دعم 9 أنواع مختلفة من الأسئلة
✅ الخيارات المرنة (JSON format)
✅ الترتيب المخصص مع Drag & Drop
✅ الإنشاء الجماعي لتوفير الوقت
✅ النسخ من وظيفة أخرى
✅ الأسئلة الإلزامية والاختيارية
✅ التحقق الشامل من البيانات
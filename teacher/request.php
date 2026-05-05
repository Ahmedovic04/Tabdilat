<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || !in_array($_SESSION['rased_role'], ['teacher', 'coordinator'])) {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['rased_user_id'];

// Check if email is registered
$stmtEmail = $db->prepare("SELECT email FROM rased_users WHERE id = ?");
$stmtEmail->execute([$user_id]);
$user_email = $stmtEmail->fetchColumn();

$has_email = !empty($user_email);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب تبديل حصة - راصد تبديلاتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5; --primary-hover: #4338CA;
            --bg-color: #F3F4F6; --card-bg: #FFFFFF; --text-main: #1F2937; --border-color: #E5E7EB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Tajawal', sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); }
        .navbar {
            background: var(--card-bg); padding: 1rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: var(--primary); margin-bottom: 1rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="date"], select {
            width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem;
        }
        .btn {
            background: var(--primary); color: white; padding: 0.75rem 1.5rem;
            border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: 0.3s;
        }
        .btn:hover { background: var(--primary-hover); }
        
        .class-row {
            padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 1rem;
            background: #F9FAFB;
        }
        .class-info { font-weight: bold; margin-bottom: 1rem; color: var(--primary); font-size: 1.1rem; border-bottom: 1px solid #ddd; padding-bottom: 0.5rem; }
        
        .row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 600px) { .row-grid { grid-template-columns: 1fr; } }
        
        #submit-btn { display: none; width: 100%; margin-top: 1rem; }
        
        .repay-container { background: #EEF2FF; padding: 0.75rem; border-radius: 8px; border: 1px dashed var(--primary); }
        .repay-container label { color: var(--primary); font-size: 0.9em; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand"><a href="../index.php" style="text-decoration:none; color:inherit; font-weight:bold;">راصد تبديلاتي</a></div>
    <div><a href="../<?= $_SESSION['rased_role'] ?>/index.php" style="color:var(--text-main); text-decoration:none; font-weight: bold;">العودة للوحة الرئيسية</a></div>
</div>

<div class="container">
    <div class="card shadow-sm border-0">
        <?php if (!$has_email): ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-envelope-exclamation-fill text-warning" style="font-size: 5rem;"></i>
                </div>
                <h2 class="text-dark fw-bold mb-3">عذراً، البريد الإلكتروني غير مسجل!</h2>
                <p class="text-muted mb-4 fs-5">لضمان وصول إشعارات التبديل والموافقات إليك، يجب تسجيل بريدك الإلكتروني أولاً في ملفك الشخصي قبل البدء بطلب أي تبديل.</p>
                <a href="profile.php" class="btn btn-primary btn-lg px-5 shadow">
                    <i class="bi bi-person-gear me-2"></i> انتقل للملف الشخصي الآن
                </a>
            </div>
        <?php else: ?>
            <h2>طلب تبديل ذكي</h2>
            <p style="margin-bottom: 1.5rem; color: #6B7280;">اختر تاريخ الغياب وسيقوم النظام باقتراح المعلمين المتاحين واقتراح أفضل الحصص التي يمكنك من خلالها تعويضهم.</p>
            
            <div class="form-group">
                <label>تاريخ الغياب / التبديل</label>
                <input type="date" id="date-input" min="<?= date('Y-m-d') ?>" class="form-control">
            </div>
            
            <div id="classes-container"></div>
            
            <button id="submit-btn" class="btn btn-primary w-100 mt-4 shadow-sm py-3 fw-bold" style="display: none;">تأكيد إرسال الطلب</button>
        <?php endif; ?>
    </div>
</div>

<script>
    const dateInput = document.getElementById('date-input');
    const classesContainer = document.getElementById('classes-container');
    const submitBtn = document.getElementById('submit-btn');
    
    let currentClasses = [];

    dateInput.addEventListener('change', async (e) => {
        const date = e.target.value;
        if (!date) return;
        
        classesContainer.innerHTML = '<p>جاري تحميل الحصص...</p>';
        submitBtn.style.display = 'none';
        
        try {
            const res = await fetch(`api.php?action=get_classes&date=${date}`);
            const data = await res.json();
            
            if (data.classes && data.classes.length > 0) {
                renderClasses(data.classes, data.day);
            } else {
                classesContainer.innerHTML = '<p style="color:red;">لا توجد حصص في هذا اليوم، أو أنه يوم عطلة.</p>';
            }
        } catch (err) {
            classesContainer.innerHTML = '<p>حدث خطأ في الاتصال.</p>';
        }
    });
    
    async function renderClasses(classes, dayOfWeek) {
        classesContainer.innerHTML = '';
        currentClasses = classes;
        
        for (const cls of classes) {
            const row = document.createElement('div');
            row.className = 'class-row';
            
            row.innerHTML = `
                <div class="class-info">
                    الحصة ${cls.period_number} - ${cls.class_name}
                </div>
                <div class="row-grid">
                    <div>
                        <label>اختر المعلم البديل</label>
                        <select id="sub_${cls.period_number}" data-class="${cls.class_id}" data-period="${cls.period_number}" onchange="handleSubChange(${cls.period_number}, ${cls.class_id})">
                            <option value="">-- اختر المعلم البديل --</option>
                        </select>
                    </div>
                    <div class="repay-container">
                        <label>اقتراحات الحصص لتعويض الزميل</label>
                        <select id="repay_${cls.period_number}" disabled>
                            <option value="">-- اختر المعلم أولاً --</option>
                        </select>
                    </div>
                </div>
            `;
            classesContainer.appendChild(row);
            
            loadSubstitutes(cls.class_id, dayOfWeek, cls.period_number, `sub_${cls.period_number}`);
        }
        
        submitBtn.style.display = 'block';
    }
    
    async function loadSubstitutes(classId, day, period, selectId) {
        const select = document.getElementById(selectId);
        try {
            const res = await fetch(`api.php?action=get_substitutes&class_id=${classId}&day=${day}&period=${period}`);
            const data = await res.json();
            
            if (data.substitutes) {
                data.substitutes.forEach(sub => {
                    const opt = document.createElement('option');
                    opt.value = sub.id;
                    opt.textContent = `${sub.name} (لديه ${sub.daily_classes_count} حصص اليوم)`;
                    select.appendChild(opt);
                });
            }
        } catch (err) {
            console.error(err);
        }
    }
    
    async function handleSubChange(periodNumber, classId) {
        const subSelect = document.getElementById(`sub_${periodNumber}`);
        const repaySelect = document.getElementById(`repay_${periodNumber}`);
        const dateVal = dateInput.value;
        const subId = subSelect.value;
        
        if (!subId) {
            repaySelect.innerHTML = '<option value="">-- اختر المعلم أولاً --</option>';
            repaySelect.disabled = true;
            return;
        }
        
        repaySelect.innerHTML = '<option value="">جاري البحث عن حصص...</option>';
        repaySelect.disabled = true;
        
        try {
            const res = await fetch(`api.php?action=get_repayment_suggestions&sub_id=${subId}&date=${dateVal}&class_id=${classId}`);
            const data = await res.json();
            
            repaySelect.innerHTML = '<option value="">-- اختر حصة لتعويض الزميل --</option>';
            if (data.suggestions && data.suggestions.length > 0) {
                data.suggestions.forEach(sug => {
                    const opt = document.createElement('option');
                    opt.value = `${sug.date}_${sug.period}`;
                    opt.textContent = `${sug.formatted_date} - الحصة ${sug.period} (${sug.class_name})`;
                    repaySelect.appendChild(opt);
                });
                repaySelect.disabled = false;
            } else {
                repaySelect.innerHTML = '<option value="">لا توجد حصص متاحة لتعويض هذا المعلم قريباً</option>';
                const manualOpt = document.createElement('option');
                manualOpt.value = 'manual';
                manualOpt.textContent = 'تحديد يدوي لاحقاً';
                repaySelect.appendChild(manualOpt);
                repaySelect.disabled = false;
            }
        } catch (err) {
            repaySelect.innerHTML = '<option value="">خطأ في جلب الاقتراحات</option>';
        }
    }
    
    submitBtn.addEventListener('click', async () => {
        const requests = [];
        let missingRepayment = false;
        
        currentClasses.forEach(cls => {
            const subSelect = document.getElementById(`sub_${cls.period_number}`);
            const repaySelect = document.getElementById(`repay_${cls.period_number}`);
            
            if (subSelect.value) {
                if (!repaySelect.value) {
                    missingRepayment = true;
                }
                
                requests.push({
                    class_id: cls.class_id,
                    period_number: cls.period_number,
                    substitute_id: subSelect.value,
                    repayment_val: repaySelect.value === 'manual' ? null : repaySelect.value
                });
            }
        });
        
        if (requests.length === 0) {
            alert('يرجى اختيار معلم بديل لحصة واحدة على الأقل.');
            return;
        }
        
        if (missingRepayment) {
            alert('يرجى اختيار حصة التعويض لجميع الطلبات.');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'جاري الإرسال...';
        
        try {
            const res = await fetch('api.php?action=submit_request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date: dateInput.value,
                    requests: requests
                })
            });
            const data = await res.json();
            if (data.success) {
                alert('تم إرسال الطلب بنجاح.');
                window.location.href = '../<?= $_SESSION['rased_role'] ?>/index.php';
            } else {
                alert(data.message || 'حدث خطأ');
                submitBtn.disabled = false;
                submitBtn.textContent = 'تأكيد إرسال الطلب';
            }
        } catch (err) {
            alert('خطأ في الاتصال');
            submitBtn.disabled = false;
            submitBtn.textContent = 'تأكيد إرسال الطلب';
        }
    });
</script>

</body>
</html>

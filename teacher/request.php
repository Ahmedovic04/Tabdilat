<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || !in_array($_SESSION['rased_role'], ['teacher', 'coordinator', 'deputy'])) {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['rased_user_id'];

// Check if email is registered
$stmtEmail = $db->prepare("SELECT email FROM rased_users WHERE id = ?");
$stmtEmail->execute([$user_id]);
$user_email = $stmtEmail->fetchColumn();

// Check if we are editing an existing request
$edit_request = null;
if (isset($_GET['request_id'])) {
    $sql = "SELECT r.*, u2.name as substitute_name FROM rased_requests r JOIN rased_users u2 ON r.substitute_id = u2.id WHERE r.id = ?";
    $params = [$_GET['request_id']];
    
    if ($_SESSION['rased_role'] !== 'deputy') {
        $sql .= " AND r.requester_id = ?";
        $params[] = $user_id;
    }

    $stmtEdit = $db->prepare($sql);
    $stmtEdit->execute($params);
    $edit_request = $stmtEdit->fetch();
}

$has_email = !empty($user_email);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_request ? 'تعديل طلب تبديل' : 'طلب تبديل حصة' ?> - راصد تبديلاتي</title>
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
                <h2 class="text-dark fw-bold mb-3">تسجيل بريد إلكتروني وكلمة مرور</h2>
                <p class="text-muted mb-4">يرجى إدخال بريدك الإلكتروني. يمكنك أيضاً تغيير كلمة المرور إذا رغبت في ذلك.</p>
                <form id="profile-save-form" class="row g-3" style="max-width: 500px; margin: 0 auto;">
                    <div class="form-group" style="text-align: right;">
                        <label>البريد الإلكتروني <span style="color:red;">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" required placeholder="example@school.com">
                    </div>
                    <div class="form-group" style="text-align: right; border-top: 1px solid #eee; padding-top: 1rem;">
                        <label>كلمة المرور الجديدة (اختياري)</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="اتركها فارغة إذا لم ترد التغيير">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control mt-2" placeholder="تأكيد كلمة المرور">
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" id="save-profile-btn" class="btn btn-success btn-lg px-5 shadow-sm" style="background:#059669; width: 100%;">حفظ البيانات والمتابعة</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <h2><?= $edit_request ? 'تعديل طلب تبديل #' . $edit_request['id'] : 'طلب تبديل ذكي' ?></h2>
            <p style="margin-bottom: 1.5rem; color: #6B7280;">اختر تاريخ الغياب وسيقوم النظام باقتراح المعلمين المتاحين واقتراح أفضل الحصص التي يمكنك من خلالها تعويضهم.</p>
            
            <div class="form-group">
                <label>تاريخ الغياب / التبديل</label>
                <input type="date" id="date-input" min="<?= date('Y-m-d') ?>" class="form-control" value="<?= $edit_request ? $edit_request['request_date'] : '' ?>" <?= $edit_request ? 'disabled' : '' ?>>
            </div>
            
            <div id="classes-container"></div>
            
            <button id="submit-btn" class="btn btn-primary w-100 mt-4 shadow-sm py-3 fw-bold" style="display: none;">
                <?= $edit_request ? 'حفظ التعديلات' : 'تأكيد إرسال الطلب' ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<script>
    // Profile save logic for teachers without email
    const saveProfileBtn = document.getElementById('save-profile-btn');
    if (saveProfileBtn) {
        saveProfileBtn.addEventListener('click', async () => {
            const email = document.getElementById('email').value;
            const new_password = document.getElementById('new_password').value;
            const confirm_password = document.getElementById('confirm_password').value;

            if (!email) {
                alert('يرجى إدخال البريد الإلكتروني');
                return;
            }

            if (new_password && new_password !== confirm_password) {
                alert('كلمتا المرور غير متطابقتين');
                return;
            }

            saveProfileBtn.disabled = true;
            saveProfileBtn.textContent = 'جاري الحفظ...';

            try {
                const res = await fetch('api.php?action=save_profile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, new_password, confirm_password })
                });
                const data = await res.json();
                if (data.success) {
                    location.reload(); 
                } else {
                    alert(data.message || 'حدث خطأ أثناء الحفظ');
                    saveProfileBtn.disabled = false;
                    saveProfileBtn.textContent = 'حفظ البيانات والمتابعة';
                }
            } catch (err) {
                alert('خطأ في الاتصال');
                saveProfileBtn.disabled = false;
                saveProfileBtn.textContent = 'حفظ البيانات والمتابعة';
            }
        });
    }

    const dateInput = document.getElementById('date-input');
    const classesContainer = document.getElementById('classes-container');
    const submitBtn = document.getElementById('submit-btn');
    
    let currentClasses = [];
    const editData = <?= $edit_request ? json_encode($edit_request) : 'null' ?>;
    let isFirstLoad = true; // Flag to only pre-fill once

    if (editData) {
        // Trigger loading classes for the edit date
        window.addEventListener('load', () => {
            loadClasses(editData.request_date);
        });
    }

    dateInput.addEventListener('change', (e) => {
        isFirstLoad = false; // Disable pre-fill if date is changed manually
        loadClasses(e.target.value);
    });

    async function loadClasses(date) {
        if (!date) return;
        
        classesContainer.innerHTML = '<div class="text-center p-4">⌛ جاري تحميل الحصص والبدلاء...</div>';
        submitBtn.style.display = 'none';
        
        try {
            const res = await fetch(`api.php?action=get_classes&date=${date}`);
            const data = await res.json();
            
            if (data.classes && data.classes.length > 0) {
                await renderClasses(data.classes, data.day);
            } else {
                classesContainer.innerHTML = '<p style="color:red; text-align:center; padding:2rem;">لا توجد حصص في هذا اليوم، أو أنه يوم عطلة.</p>';
            }
        } catch (err) {
            classesContainer.innerHTML = '<p style="text-align:center; padding:2rem;">حدث خطأ في الاتصال بالسيرفر.</p>';
        }
    }
    
    async function renderClasses(classes, dayOfWeek) {
        classesContainer.innerHTML = '';
        currentClasses = classes;
        
        let renderedAny = false;
        for (const cls of classes) {
            // In edit mode, only show the row for the period we are editing
            if (editData && cls.period_number != editData.period_number) continue;

            renderedAny = true;
            const row = document.createElement('div');
            row.className = 'class-row';
            
            row.innerHTML = `
                <div class="class-info">
                    الحصة ${cls.period_number} - ${cls.class_name}
                </div>
                <div class="row-grid">
                    <div>
                        <label>اختر المعلم البديل</label>
                        <select id="sub_${cls.period_number}" class="sub-select" data-class="${cls.class_id}" data-period="${cls.period_number}" onchange="handleSubChange(${cls.period_number}, ${cls.class_id})">
                            <option value="">-- اختر المعلم البديل --</option>
                        </select>
                    </div>
                    <div class="repay-container">
                        <label>اقتراحات الحصص لتعويض الزميل</label>
                        <select id="repay_${cls.period_number}" class="repay-select" disabled>
                            <option value="">-- اختر المعلم أولاً --</option>
                        </select>
                    </div>
                </div>
            `;
            classesContainer.appendChild(row);
            
            await loadSubstitutes(cls.class_id, dayOfWeek, cls.period_number, `sub_${cls.period_number}`);
            
            // Only pre-fill if it's the first load AND the date matches
            if (editData && isFirstLoad && cls.period_number == editData.period_number && dateInput.value === editData.request_date) {
                const subSelect = document.getElementById(`sub_${cls.period_number}`);
                subSelect.value = editData.substitute_id;
                await handleSubChange(cls.period_number, cls.class_id);
                const repaySelect = document.getElementById(`repay_${cls.period_number}`);
                repaySelect.value = editData.repayment_date + '_' + editData.repayment_period;
            }
        }
        
        if (editData && !renderedAny) {
            classesContainer.innerHTML = '<p style="color:red; text-align:center; padding:1rem; border:1px solid #ffcccc; border-radius:8px;">⚠️ تنبيه: لا توجد حصة رقم (' + editData.period_number + ') مسجلة لك في هذا اليوم حسب الجدول الحالي. يرجى مراجعة النائب الأكاديمي.</p>';
        }
        
        submitBtn.style.display = renderedAny ? 'block' : 'none';
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
        
        // Final collection of data
        const subSelects = document.querySelectorAll('.sub-select');
        subSelects.forEach(subSelect => {
            const pNum = subSelect.dataset.period;
            const repaySelect = document.getElementById(`repay_${pNum}`);
            
            if (subSelect.value) {
                if (!repaySelect || !repaySelect.value) {
                    missingRepayment = true;
                }
                
                requests.push({
                    class_id: subSelect.dataset.class,
                    period_number: pNum,
                    substitute_id: subSelect.value,
                    sub_name: subSelect.options[subSelect.selectedIndex].text.split('(')[0].trim(), // For debugging alert
                    repayment_val: (repaySelect && (repaySelect.value === 'manual' || !repaySelect.value)) ? null : repaySelect.value
                });
            }
        });
        
        if (requests.length === 0) {
            alert('يرجى اختيار معلم بديل.');
            return;
        }
        
        if (missingRepayment) {
            alert('يرجى اختيار حصة التعويض.');
            return;
        }
        
        const confirmMsg = editData 
            ? `تأكيد التعديل:\nالمعلم البديل: ${requests[0].sub_name}\nالتاريخ: ${dateInput.value}\nهل تريد الحفظ؟`
            : `تأكيد إرسال الطلب؟`;
            
        if(!confirm(confirmMsg)) return;

        submitBtn.disabled = true;
        submitBtn.textContent = 'جاري الحفظ...';
        
        try {
            const actionUrl = editData ? `api.php?action=update_request&request_id=${editData.id}` : 'api.php?action=submit_request';
            const res = await fetch(actionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date: dateInput.value,
                    requests: requests
                })
            });
            const data = await res.json();
            if (data.success) {
                alert('تم التحديث بنجاح. الموظف المسجل حالياً هو: ' + (data.updated_sub || ''));
                window.location.href = '../<?= $_SESSION['rased_role'] ?>/index.php';
            } else {
                alert(data.message || 'حدث خطأ في الخادم');
                submitBtn.disabled = false;
                submitBtn.textContent = editData ? 'حفظ التعديلات' : 'تأكيد إرسال الطلب';
            }
        } catch (err) {
            console.error(err);
            alert('خطأ في الاتصال بالسيرفر');
            submitBtn.disabled = false;
            submitBtn.textContent = editData ? 'حفظ التعديلات' : 'تأكيد إرسال الطلب';
        }
    });
</script>

</body>
</html>

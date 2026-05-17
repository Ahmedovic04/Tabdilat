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
    // Only Deputy can edit existing requests
    if ($_SESSION['rased_role'] !== 'deputy') {
        die('<div style="text-align:center; padding:20px; font-family:sans-serif;"><h3>عذراً، التعديل متاح فقط للنائب الأكاديمي.</h3><a href="../my_requests.php">العودة لطلباتي</a></div>');
    }
    
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
<?php 
$page_title = 'طلب تبديل ذكي - راصد تبديلاتي';
$active_page = 'new_request';
$base_url = '../';
include '../includes/header.php'; 
?>

<style>
    /* Scoped styles to restore the preferred look inside the sidebar layout */
    .request-container { max-width: 1000px; margin: 0 auto; }
    .request-card { 
        background: #fff; border-radius: 20px; padding: 2.5rem; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none;
    }
    .request-title { font-weight: 800; font-size: 2.2rem; color: #1a3a5c; margin-bottom: 0.5rem; text-align: center; }
    .request-hint { color: #64748b; margin-bottom: 2.5rem; font-size: 1.1rem; text-align: center; }
    
    .class-row {
        padding: 1.5rem; border: 1px solid #f1f5f9; border-radius: 16px; margin-bottom: 1.5rem;
        background: #f8faff; transition: 0.3s;
    }
    .class-info { font-weight: 800; margin-bottom: 1.2rem; color: #1e293b; font-size: 1.2rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.8rem; }
    
    .grid-item { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; margin-bottom: 1rem; }
    .grid-item:last-child { margin-bottom: 0; }
    .grid-item label { margin: 0; white-space: nowrap; min-width: 220px; text-align: right; font-weight: 700; color: #475569; }
    .grid-item select { 
        flex: 1; padding: 0.8rem 1rem; border: 1px solid #cbd5e1; border-radius: 10px; 
        font-size: 1rem; font-family: inherit; cursor: pointer; transition: 0.3s;
    }
    
    .dropdowns-row { display: flex; gap: 2rem; align-items: flex-end; }
    .dropdowns-row .grid-item { flex: 1; margin-bottom: 0; flex-direction: column; align-items: stretch; }
    .dropdowns-row .grid-item label { text-align: right; margin-bottom: 0.5rem; }
    .dropdowns-row .grid-item select { width: 100%; }
    .grid-item select:focus { border-color: #1a3a5c; box-shadow: 0 0 0 4px rgba(26,58,92,0.1); }

    .btn-submit-main {
        background: #1a3a5c; color: white; padding: 1.2rem;
        border: none; border-radius: 15px; cursor: pointer; font-size: 1.3rem; font-weight: 800;
        width: 100%; margin-top: 2rem; transition: 0.3s; box-shadow: 0 10px 20px rgba(26,58,92,0.2);
    }
    .btn-submit-main:hover { background: #122a44; transform: translateY(-2px); box-shadow: 0 15px 30px rgba(26,58,92,0.3); }
    
    .repay-container { background: #fff; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 0.5rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
</style>

<div class="request-container py-4">
    <div class="request-card shadow-sm">
        <?php if (!$has_email): ?>
            <div class="text-center py-5">
                <h1 class="request-title mb-3">إكمال بيانات الملف الشخصي</h1>
                <p class="request-hint">يرجى تسجيل بريدك الإلكتروني لضمان وصول التنبيهات والطلبات إليك فوراً.</p>
                <form id="profile-save-form" class="text-start mx-auto" style="max-width: 450px;">
                    <div class="mb-4">
                        <label class="form-label fw-bold">البريد الإلكتروني <span class="text-danger">*</span></label>
                        <input type="email" id="email" class="form-control form-control-lg" placeholder="example@mail.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الجديدة (اختياري)</label>
                        <input type="password" id="new_password" class="form-control form-control-lg" placeholder="••••••••">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">تأكيد كلمة المرور</label>
                        <input type="password" id="confirm_password" class="form-control form-control-lg" placeholder="••••••••">
                    </div>
                    <button type="button" id="save-profile-btn" class="btn-submit-main shadow-sm">حفظ البيانات والمتابعة</button>
                </form>
            </div>
        <?php else: ?>
            <h1 class="request-title"><?= $edit_request ? 'تعديل طلب تبديل #' . $edit_request['id'] : 'طلب تبديل ذكي' ?></h1>
            <p class="request-hint">اختر تاريخ الغياب وسيقوم النظام باقتراح المعلمين المتاحين واقتراح أفضل الحصص التي يمكنك من خلالها تعويضهم.</p>
            
            <div class="form-group mb-5">
                <label class="fw-bold mb-2">تاريخ الغياب / التبديل</label>
                <input type="date" id="date-input" min="<?= date('Y-m-d') ?>" class="form-control form-control-lg" value="<?= $edit_request ? $edit_request['request_date'] : '' ?>" <?= $edit_request ? 'disabled' : '' ?>>
            </div>
            
            <div id="classes-container"></div>
            
            <button id="submit-btn" class="btn-submit-main shadow-sm" style="display: none;">
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
                <div class="dropdowns-row">
                    <div class="grid-item">
                        <label>اختر المعلم البديل</label>
                        <select id="sub_${cls.period_number}" class="sub-select" data-class="${cls.class_id}" data-period="${cls.period_number}" onchange="handleSubChange(${cls.period_number}, ${cls.class_id})">
                            <option value="">-- اختر المعلم البديل --</option>
                        </select>
                    </div>
                    <div class="grid-item">
                        <label>اقتراحات الحصص لتعويض الزميل</label>
                        <div class="repay-container" style="flex:1;">
                            <select id="repay_${cls.period_number}" class="repay-select" disabled>
                                <option value="">-- اختر المعلم أولاً --</option>
                            </select>
                        </div>
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
                    const beforeLabel = sug.is_before ? ' (قبل الغياب)' : '';
                    opt.textContent = `${sug.formatted_date} - الحصة ${sug.period} (${sug.class_name})${beforeLabel}`;
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

    </div>
</div>

<?php include '../includes/footer.php'; ?>

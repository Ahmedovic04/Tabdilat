<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}
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
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --bg-color: #F3F4F6;
            --card-bg: #FFFFFF;
            --text-main: #1F2937;
            --border-color: #E5E7EB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Tajawal', sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); }
        .navbar {
            background: var(--card-bg);
            padding: 1rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
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
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 1rem;
            background: #F9FAFB;
        }
        .class-info { font-weight: bold; }
        .subs-select { max-width: 300px; }
        
        #submit-btn { display: none; width: 100%; margin-top: 1rem; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand"><a href="index.php" style="text-decoration:none; color:inherit;">راصد تبديلاتي</a></div>
    <div>رجوع للوحة الرئيسية</div>
</div>

<div class="container">
    <div class="card">
        <h2>طلب تبديل</h2>
        
        <div class="form-group">
            <label>تاريخ الغياب / التبديل</label>
            <input type="date" id="date-input" min="<?= date('Y-m-d') ?>">
        </div>
        
        <div id="classes-container"></div>
        
        <button id="submit-btn" class="btn">تأكيد إرسال الطلب</button>
    </div>
</div>

<script>
    const dateInput = document.getElementById('date-input');
    const classesContainer = document.getElementById('classes-container');
    const submitBtn = document.getElementById('submit-btn');
    
    let currentClasses = [];
    let selectedSubstitutes = {};

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
        selectedSubstitutes = {};
        
        for (const cls of classes) {
            const row = document.createElement('div');
            row.className = 'class-row';
            
            row.innerHTML = `
                <div class="class-info">
                    الحصة ${cls.period_number} - ${cls.class_name}
                </div>
                <div class="subs-select">
                    <select id="sub_${cls.period_number}" data-class="${cls.class_id}" data-period="${cls.period_number}">
                        <option value="">-- اختر المعلم البديل --</option>
                    </select>
                </div>
            `;
            classesContainer.appendChild(row);
            
            // Load substitutes
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
                    opt.textContent = sub.name;
                    select.appendChild(opt);
                });
            }
        } catch (err) {
            console.error(err);
        }
    }
    
    submitBtn.addEventListener('click', async () => {
        const requests = [];
        let hasError = false;
        
        currentClasses.forEach(cls => {
            const select = document.getElementById(`sub_${cls.period_number}`);
            if (select.value) {
                requests.push({
                    class_id: cls.class_id,
                    period_number: cls.period_number,
                    substitute_id: select.value
                });
            }
        });
        
        if (requests.length === 0) {
            alert('يرجى اختيار معلم بديل لحصة واحدة على الأقل.');
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
                alert('تم إرسال الطلب بنجاح للتقييم.');
                window.location.href = 'index.php';
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

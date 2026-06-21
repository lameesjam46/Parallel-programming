<?php
// balancer.php

// 1. مصفوفة السيرفرات الخلفية (Backend Instances) المتاحة لدينا
$backends = [
    'http://127.0.0.1:8001', // السيرفر الأول
    'http://127.0.0.1:8002'  // السيرفر الثاني
];

// 2. تحديد مسار لملف نصي بسيط لتخزين عداد الطلبات
$counterFile = __DIR__ . '/balancer_counter.txt';
$index = 0;

// إذا كان الملف موجوداً من قبل، نقرأ الرقم المخزن داخله
if (file_exists($counterFile)) {
    $index = (int)file_get_contents($counterFile);
}

// 3. خوارزمية التدوير (Round Robin Algorithm): اختيار السيرفر بالتناوب
$selectedBackend = $backends[$index % count($backends)];

// 4. نزيد العداد بمقدار 1 ونحفظه في الملف من أجل الطلب القادم
file_put_contents($counterFile, ($index + 1));

// 5. جلب المسار الفعلي المطلوب ديناميكياً (مثال: /api/orders/place/unsafe)
// استبعاد أي Query Parameters من الـ URI لتجنب تكرارها عند بناء الرابط
$requestUri = strtok($_SERVER['REQUEST_URI'], '?');

// جلب الـ Query Parameters إن وجدت (مثال: mode=unsafe)
$queryString = $_SERVER['QUERY_STRING'] ?? '';

// بناء الرابط النهائي الموجه للسيرفر المختار بشكل ديناميكي وصحيح 100%
$url = $selectedBackend . $requestUri . ($queryString ? '?' . $queryString : '');

// 6. استخدام تقنية cURL لإعادة توجيه الطلب (Reverse Proxy)
$ch = curl_init();

// جلب البيانات الخام (JSON) القادمة مع طلب الـ POST من أداة ab
$postData = file_get_contents('php://input');

// إعداد خيارات الـ cURL لمحاكاة الـ Proxy والعبور الآمن للبيانات
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']); // الحفاظ على نوع الطلب POST
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);                     // تمرير بيانات الـ JSON الأصلية
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

// إرسال الطلب الفعلي إلى السيرفر الخلفي المختار واستقبال النتيجة
$output = curl_exec($ch);
$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 7. إعادة إرجاع النتيجة والـ HTTP Status Code المستلمة إلى أداة ab مباشرة
http_response_code($httpStatusCode);
echo $output;
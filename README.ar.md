[🇸🇦 العربية](README.ar.md) | [🇬🇧 English](README.md)

# eidcloud-project-archaeologist | أثري الأنظمة القديمة — تفكيك الكود المهجور وعكس هندسة المعمارية

[![الإصدار](https://img.shields.io/badge/version-v1.0.0-blue.svg)](https://github.com/shadialhasan/eidcloud-project-archaeologist/releases)
[![بيئة التشغيل](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://www.php.net)
[![الترخيص: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![فتح في كولاب](https://colab.research.google.com/assets/colab-badge.svg)](https://colab.research.google.com/github/shadialhasan/eidcloud-project-archaeologist/blob/main/notebooks/quickstart.ipynb)
[![المنظومة](https://img.shields.io/badge/EIDCloud-Ecosystem-green.svg)](https://github.com/shadialhasan/eidcloud-cli)

أداة التنقيب في الأنظمة البرمجية القديمة لتفكيك بنية التطبيقات، كشف الكود الميت (Dead Code)، ورسم الخرائط المعمارية للمشاريع الكبيرة.


> **☁️ إشعار المنظومة:** هذه الأداة هي جزء مستقل من منظومة **[EIDCloud](https://github.com/shadialhasan/eidcloud-cli)**.  
> يمكنك استخدامها بمفردها كأداة متخصصة، أو إدارتها مع كافة أدوات المنظومة الـ 49 عبر الماستر CLI المركزي: `eidcloud`.


---

## 📑 التصنيف والقطاع
**أمن الشيفرات وهندسة البرمجيات (Security & Code Intel)**

---

## ✨ المميزات الرئيسية

- **كشف الفئات والدوال المهجورة التي لا يوجد أي استدعاء لها في المشروع**
- **رسم خريطة معمارية تفاعلية لنقاط الدخول ومسارات التخاطب وقواعد البيانات**
- **اكتشاف الروائح الأمنية (Hardcoded Secrets, Raw SQL, Global State)**
- **توليد وثائق معمارية كاملة تلقائياً: ARCHITECTURE.md و REFACTORING.md**

---

## 🚀 التثبيت والتشغيل السريع

### 1. الاستخدام المستقل (Standalone)
يمكنك استنساخ وتشغيل هذه الأداة بشكل منفصل تماماً:

```bash
git clone https://github.com/shadialhasan/eidcloud-project-archaeologist.git
cd eidcloud-project-archaeologist
php tests/run_tests.php
```

### 2. التثبيت كجزء من الماستر CLI الموحد
إذا كان لديك أداة `eidcloud-cli` مثبتة، يمكنك تسجيل هذه الأداة أو تثبيتها بأمر واحد:

```bash
eidcloud plugin add https://github.com/shadialhasan/eidcloud-project-archaeologist.git
```

---

## 💻 أمثلة الاستخدام عبر سطر الأوامر

```bash
# تشغيل الأداة مباشرة
php bin/eidcloud-archaeologist excavate ./legacy_app/ --out=./docs/
php bin/eidcloud-archaeologist dead-code ./legacy_app/
```

```bash
# عرض المساعدة والدليل الشامل
php bin/eidcloud-archaeologist --help
```

---

## 🧪 الاختبارات الآلية

تم تجهيز هذا المستودع بمجموعة اختبارات ذاتية متكاملة تعمل بدون أي مكتبات خارجية:

```bash
php tests/run_tests.php
```

---

## 🌐 تجربة فورية عبر المتصفح (Google Colab)

يمكنك تجربة الأداة مباشرة على سحابة Google بدون الحاجة لتثبيت أي متطلبات محلياً:  
[![Open In Colab](https://colab.research.google.com/assets/colab-badge.svg)](https://colab.research.google.com/github/shadialhasan/eidcloud-project-archaeologist/blob/main/notebooks/quickstart.ipynb)

---

## 👤 المؤلف والمشرف

**م. محمد شادي الحسن**  
- **الدور:** المدير التقني التنفيذي ومهندس الحلول المؤسسية  
- **البريد الإلكتروني:** [mhd.shadi.alhasan@gmail.com](mailto:mhd.shadi.alhasan@gmail.com)  
- **الهاتف / واتساب:** [+963934005922](tel:+963934005922)  
- **الموقع:** دمشق، سوريا  
- **GitHub:** [shadialhasan](https://github.com/shadialhasan)  

---

## 📄 الرخصة

هذا المشروع مرخص بموجب رخصة MIT - انظر ملف [LICENSE](LICENSE) للتفاصيل.  
حقوق النشر (c) 2026 **م. محمد شادي الحسن**. جميع الحقوق محفوظة.

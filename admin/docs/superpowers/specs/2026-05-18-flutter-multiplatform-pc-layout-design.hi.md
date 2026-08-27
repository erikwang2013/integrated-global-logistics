> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) | [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) | [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) | [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) | [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) | [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) | [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) | [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) | [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) | [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) | [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) | [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) | [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)

# Flutter मल्टी-प्लेटफ़ॉर्म PC-शैली लेआउट — डिज़ाइन स्पेक

दिनांक: 2026-05-18

## लक्ष्य

macOS और Windows डेस्कटॉप प्लेटफ़ॉर्म सक्षम करें, और सुनिश्चित करें कि iOS (iPhone + iPad), macOS, Windows, Linux सभी प्लेटफ़ॉर्म PC प्रशासन पैनल-शैली लेआउट (साइडबार + टॉप बार + सामग्री क्षेत्र) का उपयोग करें, जबकि मोबाइल पर ड्रॉअर मेनू से अनुकूलन हो।

## प्लेटफ़ॉर्म रणनीति

| प्लेटफ़ॉर्म | स्थिति | विवरण |
|------|------|------|
| Linux | पहले से सक्षम | कोई कार्रवाई आवश्यक नहीं |
| macOS | सक्षम करना है | `flutter config --enable-macos-desktop` |
| Windows | सक्षम करना है | `flutter config --enable-windows-desktop` |
| iOS | पहले से मौजूद | iPhone (मोबाइल लेआउट) और iPad (डेस्कटॉप लेआउट) दोनों कवर करता है |
| Web | पहले से मौजूद | कोई कार्रवाई आवश्यक नहीं |

iPad का कोई अलग प्लेटफ़ॉर्म टारगेट नहीं है; यह रिस्पॉन्सिव ब्रेकपॉइंट के माध्यम से TABLET श्रेणी में आकर डेस्कटॉप लेआउट प्राप्त करता है।

## रिस्पॉन्सिव ब्रेकपॉइंट

| ब्रेकपॉइंट | रेंज | लेआउट मोड |
|------|------|----------|
| PHONE | 0 - 767 | ड्रॉअर मेनू (AppBar + Drawer) |
| TABLET | 768 - 1199 | फोल्डेबल साइडबार (डिफ़ॉल्ट फोल्ड 64px) |
| DESKTOP | 1200 - 2460 | साइडबार (डिफ़ॉल्ट खुला 240px) |

iPad पोर्ट्रेट मोड की न्यूनतम चौड़ाई 768px है, यह TABLET श्रेणी में आकर साइडबार लेआउट प्राप्त करता है।
iPhone की चौड़ाई हमेशा 768px से कम है, यह PHONE श्रेणी में आकर ड्रॉअर मेनू प्राप्त करता है।

## फ़ाइल परिवर्तन

### 1. main.dart — ब्रेकपॉइंट कॉन्फ़िगरेशन

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- बाकी कोड अपरिवर्तित

### 2. admin_layout.dart — रिस्पॉन्सिव नेविगेशन स्विचिंग

- `_isPhone`: PHONE ब्रेकपॉइंट से मेल खाता है
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; Drawer के अंदर NavigationDrawer डेस्कटॉप साइडबार के समान मेनू आइटम पुन: उपयोग करता है
- `_buildDesktopLayout()`: मौजूदा Row लेआउट (साइडबार + टॉप बार + सामग्री क्षेत्र)
- TABLET में साइडबार डिफ़ॉल्ट रूप से फोल्ड होता है, DESKTOP में डिफ़ॉल्ट रूप से खुला रहता है

### 3. app_theme.dart — डार्क थीम पूरक

- कंपोनेंट स्टाइल को प्राइवेट कॉन्स्टेंट `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme` में निकाला गया
- लाइट और डार्क थीम एक ही कंपोनेंट स्टाइल सेट पुन: उपयोग करती हैं
- डार्क थीम Material 3 + समान seed + dark brightness का उपयोग करती है

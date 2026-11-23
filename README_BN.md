# Zoynal E-bike — নিরাপদ অনলাইন শপ (প্রাথমিক সংস্করণ)

এটি PHP 8.3 + MySQL ভিত্তিক একটি নতুন, স্বতন্ত্র অনলাইন শপের প্রাথমিক সংস্করণ।

## ফিচার
- পণ্য তালিকা ও পণ্যের বিস্তারিত
- সেশনভিত্তিক শপিং কার্ট
- COD (ক্যাশ অন ডেলিভারি) অর্ডার
- অ্যাডমিন লগইন
- অ্যাডমিন থেকে পণ্য যোগ/সম্পাদনা/মুছে ফেলা
- পাসওয়ার্ড `password_hash()` দিয়ে সংরক্ষণ
- PDO prepared statements, CSRF token, output escaping
- কোনো ডেভেলপার রিমোট লগইন, গোপন টেলিমেট্রি বা লাইসেন্স সার্ভার কল নেই

## যা এখনো যুক্ত করা হয়নি
- bKash/Nagad/SSLCommerz লাইভ পেমেন্ট: মার্চেন্ট অ্যাকাউন্ট ও API credential ছাড়া নিরাপদভাবে সক্রিয় করা যায় না।
- পণ্যের ছবি আপলোড: প্রাথমিক সংস্করণে URL দিয়ে ছবি দেখানো হয়; ফলে অনিরাপদ ফাইল আপলোডের ঝুঁকি নেই।
- কুরিয়ার API, SMS, ইমেইল নোটিফিকেশন, কুপন, রিটার্ন/রিফান্ড।

## সার্ভারের চাহিদা
- PHP 8.3 বা নতুন 8.x সংস্করণ
- MySQL 8.0+ / MariaDB 10.6+
- PHP extension: PDO MySQL, mbstring, openssl
- HTTPS অবশ্যই চালু করতে হবে

## ইনস্টলেশন
1. `public/` ফোল্ডারকে ডোমেইনের document root করুন। `app/` ও `config/` যেন public web root-এর বাইরে থাকে।
2. MySQL-এ একটি database ও সীমিত-ক্ষমতার database user তৈরি করুন।
3. `database/schema.sql` চালান।
4. `config/config.example.php` কপি করে `config/config.php` বানান এবং database তথ্য বসান।
5. `scripts/create_admin.php` একবার CLI থেকে চালান:
   `php scripts/create_admin.php`
   এটি অ্যাডমিন ইমেইল ও পাসওয়ার্ড চাইবে। তৈরি হয়ে গেলে স্ক্রিপ্টটি সার্ভার থেকে সরিয়ে ফেলুন।
6. `public/`-এ ওয়েবসাইট চালু করুন।
7. Admin: `/admin/login.php`

## নিরাপত্তা জরুরি
- `config/config.php` কখনো public directory-তে রাখবেন না।
- HTTPS ছাড়া বাস্তব গ্রাহকের তথ্য নেবেন না।
- শক্তিশালী আলাদা অ্যাডমিন পাসওয়ার্ড ব্যবহার করুন এবং 2FA যোগ না করা পর্যন্ত অ্যাডমিন অ্যাকাউন্ট সীমিত রাখুন।
- নিয়মিত off-site backup রাখুন।
- লাইভ করার আগে staging-এ পরীক্ষা এবং একজন অভিজ্ঞ ডেভেলপার দিয়ে security review করান।
- পেমেন্টের PIN/OTP কখনো ওয়েবসাইটে চাইবেন না। bKash/Nagad-এর অফিসিয়াল checkout/API ব্যবহার করতে হবে।

এই সংস্করণটি একটি starter/MVP; production launch-এর আগে hosting configuration, HTTPS, backup, privacy policy, terms, delivery/return policy এবং payment testing সম্পন্ন করতে হবে।

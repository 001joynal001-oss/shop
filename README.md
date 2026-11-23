
# shop
https://001joynal001-oss.github.io/shop/
Require all denied

<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$email=trim(readline('Admin email: '));$password=readline('Strong admin password (min 12 chars): ');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Invalid email or password too short.\n");exit(1);}
$hash=password_hash($password,PASSWORD_DEFAULT);$s=db()->prepare("INSERT INTO admins(email,password_hash) VALUES(?,?)");$s->execute([$email,$hash]);echo "Admin created. Delete this script from the server now.\n";

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

Require all denied

<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Configuration missing. Copy config/config.example.php to config/config.php and configure it.');
}
$config = require $configPath;
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Dhaka');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name($config['app']['session_name'] ?? 'zoynal_shop_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO {
    global $config;
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $d = $config['db'];
    $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('অনুরোধটি যাচাই করা যায়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
    }
}
function is_admin(): bool {
    return !empty($_SESSION['admin_id']);
}
function require_admin(): void {
    if (!is_admin()) {
        header('Location: login.php');
        exit;
    }
}
function money(int|float|string $amount): string {
    return '৳' . number_format((float)$amount, 2);
}
function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'zoynal_ebike',
        'user' => 'zoynal_shop_user',
        'pass' => 'CHANGE_THIS_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Zoynal E-bike',
        'base_url' => 'https://example.com',
        'currency' => 'BDT',
        'timezone' => 'Asia/Dhaka',
        'session_name' => 'zoynal_shop_session',
    ],
];

Require all denied

Require all denied

CREATE TABLE admins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  description TEXT NULL,
  price DECIMAL(12,2) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  image_url VARCHAR(1000) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_active (is_active, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(32) NOT NULL UNIQUE,
  customer_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address TEXT NOT NULL,
  note TEXT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'cod',
  payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
  order_status VARCHAR(30) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_created (created_at),
  INDEX idx_orders_status (order_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(190) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

Options -Indexes
DirectoryIndex index.php
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set X-Frame-Options "DENY"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>
<FilesMatch "\.(env|ini|log|sql|bak)$">
  Require all denied
</FilesMatch>

<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$stmt=db()->prepare("SELECT * FROM products WHERE slug=? AND is_active=1 LIMIT 1");$stmt->execute([$_GET['slug']??'']);$p=$stmt->fetch();
if(!$p){http_response_code(404);exit('পণ্য পাওয়া যায়নি');}
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();$qty=max(1,min(20,(int)($_POST['quantity']??1)));if($qty>(int)$p['stock']){$error='পর্যাপ্ত স্টক নেই।';}else{$_SESSION['cart'][$p['id']]=min(20,($_SESSION['cart'][$p['id']]??0)+$qty);redirect('cart.php');}}
?><!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($p['name'])?></title><link rel="stylesheet" href="assets/style.css"></head><body><header class="top"><div class="wrap nav"><a class="brand" href="index.php">Zoynal E-bike</a><a href="cart.php">কার্ট</a></div></header><main class="wrap"><p><a href="index.php">হোম</a></p><div class="card"><?php if($p['image_url']):?><img class="photo" style="max-width:420px" src="<?=e($p['image_url'])?>" alt="<?=e($p['name'])?>"><?php endif;?><h1><?=e($p['name'])?></h1><p class="price"><?=money($p['price'])?></p><p><?=nl2br(e($p['description']??''))?></p><p>স্টক: <?= (int)$p['stock']?></p><?php if(!empty($error)):?><p class="danger"><?=e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>পরিমাণ</label><input type="number" name="quantity" value="1" min="1" max="20"><button <?=((int)$p['stock']<1?'disabled':'')?>>কার্টে যোগ করুন</button></form></div></main></body></html>

<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$cart=$_SESSION['cart']??[];$error='';
function load_cart_items(array $cart): array {if(!$cart)return [[],0];$ids=array_values(array_filter(array_map('intval',array_keys($cart))));if(!$ids)return [[],0];$marks=implode(',',array_fill(0,count($ids),'?'));$s=db()->prepare("SELECT id,name,price,stock FROM products WHERE is_active=1 AND id IN ($marks)");$s->execute($ids);$items=[];$total=0;foreach($s as $p){$q=(int)$cart[$p['id']];if($q<1||$q>(int)$p['stock'])throw new RuntimeException('স্টক পরিবর্তিত হয়েছে। কার্ট আপডেট করুন।');$p['qty']=$q;$items[]=$p;$total+=$q*(float)$p['price'];}if(count($items)!==count($cart))throw new RuntimeException('কার্টে থাকা একটি পণ্য আর পাওয়া যাচ্ছে না।');return [$items,$total];}
try{[$items,$total]=load_cart_items($cart);}catch(Throwable $ex){$items=[];$total=0;$error=$ex->getMessage();}
if($_SERVER['REQUEST_METHOD']==='POST'&&!$error){csrf_check();$name=trim($_POST['name']??'');$phone=trim($_POST['phone']??'');$address=trim($_POST['address']??'');$note=trim($_POST['note']??'');if(mb_strlen($name)<2||mb_strlen($name)>150||!preg_match('/^[0-9+\-\s]{8,20}$/',$phone)||mb_strlen($address)<8||mb_strlen($address)>2000){$error='নাম, ফোন নম্বর বা ঠিকানা সঠিকভাবে দিন।';}else{try{$pdo=db();$pdo->beginTransaction();[$items,$total]=load_cart_items($_SESSION['cart']??[]);$number='ZEB'.date('ymd').strtoupper(bin2hex(random_bytes(3)));$s=$pdo->prepare("INSERT INTO orders(order_number,customer_name,phone,address,note,subtotal,payment_method,payment_status) VALUES(?,?,?,?,?,?,'cod','unpaid')");$s->execute([$number,$name,$phone,$address,$note,$total]);$oid=(int)$pdo->lastInsertId();$ins=$pdo->prepare("INSERT INTO order_items(order_id,product_id,product_name,unit_price,quantity) VALUES(?,?,?,?,?)");$stock=$pdo->prepare("UPDATE products SET stock=stock-? WHERE id=? AND stock>=?");foreach($items as $it){$ins->execute([$oid,$it['id'],$it['name'],$it['price'],$it['qty']]);$stock->execute([$it['qty'],$it['id'],$it['qty']]);if($stock->rowCount()!==1)throw new RuntimeException('স্টক শেষ হয়ে গেছে। আবার চেষ্টা করুন।');}$pdo->commit();$_SESSION['cart']=[];$_SESSION['last_order']=$number;redirect('order-success.php');}catch(Throwable $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error='অর্ডার সম্পন্ন হয়নি। স্টক যাচাই করে আবার চেষ্টা করুন।';}}}
?><!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>অর্ডার</title><link rel="stylesheet" href="assets/style.css"></head><body><header class="top"><div class="wrap nav"><a class="brand" href="index.php">Zoynal E-bike</a></div></header><main class="wrap"><h1>অর্ডার সম্পন্ন করুন</h1><?php if($error):?><p class="danger"><?=e($error)?></p><?php endif;?><?php if(!$items):?><p>কার্ট খালি বা অর্ডার করা যাচ্ছে না। <a href="index.php">পণ্য দেখুন</a></p><?php else:?><div class="card"><p>সর্বমোট: <strong><?=money($total)?></strong></p><p>পেমেন্ট: ক্যাশ অন ডেলিভারি</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>আপনার নাম</label><input name="name" maxlength="150" required><label>মোবাইল নম্বর</label><input name="phone" maxlength="20" required placeholder="01XXXXXXXXX"><label>সম্পূর্ণ ঠিকানা</label><textarea name="address" maxlength="2000" required></textarea><label>অতিরিক্ত নোট (ঐচ্ছিক)</label><textarea name="note" maxlength="1000"></textarea><button>অর্ডার নিশ্চিত করুন</button></form></div><?php endif;?></main></body></html>

<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$products = db()->query("SELECT id,name,slug,description,price,stock,image_url FROM products WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($config['app']['name']) ?></title><link rel="stylesheet" href="assets/style.css"></head><body>
<header class="top"><div class="wrap nav"><a class="brand" href="index.php">Zoynal E-bike</a><div><a href="cart.php">কার্ট (<?= array_sum($_SESSION['cart'] ?? []) ?>)</a> · <a href="admin/login.php">অ্যাডমিন</a></div></div></header>
<main class="wrap"><section class="hero"><h1>আপনার পছন্দের পণ্য কিনুন</h1><p>সহজ অর্ডার, বাংলাদেশজুড়ে ডেলিভারি।</p></section>
<?php if (!$products): ?><div class="notice">এখনো কোনো পণ্য যোগ করা হয়নি। অ্যাডমিন প্যানেল থেকে পণ্য যোগ করুন।</div><?php endif; ?>
<div class="grid"><?php foreach($products as $p): ?><article class="card">
<?php if($p['image_url']): ?><img class="photo" src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><?php else: ?><div class="photo"></div><?php endif; ?>
<h3><a href="product.php?slug=<?= urlencode($p['slug']) ?>"><?= e($p['name']) ?></a></h3><p class="price"><?= money($p['price']) ?></p><p class="muted">স্টক: <?= (int)$p['stock'] ?></p><a class="btn" href="product.php?slug=<?= urlencode($p['slug']) ?>">বিস্তারিত</a>
</article><?php endforeach; ?></div></main><footer class="wrap footer">© <?= date('Y') ?> Zoynal E-bike</footer></body></html>

<?php require dirname(__DIR__).'/app/bootstrap.php';$n=$_SESSION['last_order']??null;unset($_SESSION['last_order']);?><!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>অর্ডার সফল</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="wrap"><div class="card"><h1>ধন্যবাদ! আপনার অর্ডার গ্রহণ করা হয়েছে।</h1><?php if($n):?><p>অর্ডার নম্বর: <strong><?=e($n)?></strong></p><?php endif;?><p>আমরা আপনার সঙ্গে যোগাযোগ করব।</p><a class="btn" href="index.php">হোমে ফিরুন</a></div></main></body></html>

<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();if(isset($_POST['remove']))unset($_SESSION['cart'][(int)$_POST['remove']]);elseif(isset($_POST['update'])){foreach(($_POST['qty']??[]) as $id=>$q){$id=(int)$id;$q=(int)$q;if($q<1)unset($_SESSION['cart'][$id]);else $_SESSION['cart'][$id]=min(20,$q);}}redirect('cart.php');}
$cart=$_SESSION['cart']??[];$items=[];$total=0;
if($cart){$ids=array_values(array_filter(array_map('intval',array_keys($cart))));$marks=implode(',',array_fill(0,count($ids),'?'));$s=db()->prepare("SELECT id,name,price,stock FROM products WHERE is_active=1 AND id IN ($marks)");$s->execute($ids);foreach($s as $p){$q=min((int)$cart[$p['id']],(int)$p['stock']);if($q>0){$p['qty']=$q;$p['line']=$q*(float)$p['price'];$items[]=$p;$total+=$p['line'];}}}
?><!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>কার্ট</title><link rel="stylesheet" href="assets/style.css"></head><body><header class="top"><div class="wrap nav"><a class="brand" href="index.php">Zoynal E-bike</a><a href="index.php">কেনাকাটা চালিয়ে যান</a></div></header><main class="wrap"><h1>আপনার কার্ট</h1><?php if(!$items):?><p>কার্ট খালি। <a href="index.php">পণ্য দেখুন</a></p><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><table><tr><th>পণ্য</th><th>দাম</th><th>পরিমাণ</th><th>মোট</th><th></th></tr><?php foreach($items as $it):?><tr><td><?=e($it['name'])?></td><td><?=money($it['price'])?></td><td><input style="max-width:80px" type="number" name="qty[<?=$it['id']?>]" value="<?=$it['qty']?>" min="0" max="20"></td><td><?=money($it['line'])?></td><td><button class="btn secondary" name="remove" value="<?=$it['id']?>">বাদ দিন</button></td></tr><?php endforeach;?></table><p class="price">সর্বমোট: <?=money($total)?></p><button name="update" value="1">কার্ট আপডেট</button> <a class="btn" href="checkout.php">অর্ডার করুন</a></form><?php endif;?></main></body></html>

*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f6f7f9;color:#18202a}a{color:#075985;text-decoration:none}.wrap{max-width:1100px;margin:auto;padding:18px}.top{background:#0f2d3d;color:white}.top a{color:white}.nav{display:flex;gap:18px;align-items:center;justify-content:space-between;flex-wrap:wrap}.brand{font-size:22px;font-weight:800}.hero{padding:28px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.card{background:white;border:1px solid #e5e7eb;border-radius:12px;padding:14px}.photo{width:100%;height:170px;object-fit:cover;background:#e8edf1;border-radius:8px}.price{font-weight:800;font-size:19px}.btn,button{display:inline-block;background:#087e8b;color:white;border:0;border-radius:8px;padding:10px 15px;cursor:pointer;font:inherit}.btn.secondary{background:#334155}input,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:7px;margin:5px 0 13px;font:inherit}label{font-weight:600}table{width:100%;border-collapse:collapse;background:white}td,th{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}.muted{color:#64748b}.notice{padding:12px;background:#e0f2fe;border-radius:8px;margin:12px 0}.danger{background:#fee2e2;padding:12px;border-radius:8px}.footer{padding:30px 0;color:#64748b}.actions{display:flex;gap:8px;flex-wrap:wrap}.inline{display:inline}.adminnav{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0}

<?php
require dirname(__DIR__,2).'/app/bootstrap.php';require_admin();$orders=db()->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 200")->fetchAll();
?><!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>অর্ডার</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="wrap"><h1>সাম্প্রতিক অর্ডার</h1><p><a href="index.php">অ্যাডমিনে ফিরুন</a></p><table><tr><th>অর্ডার</th><th>গ্রাহক</th><th>ফোন</th><th>মোট</th><th>অবস্থা</th><th>তারিখ</th></tr><?php foreach($orders as $o):?><tr><td><?=e($o['order_number'])?></td><td><?=e($o['customer_name'])?><br><?=e($o['address'])?></td><td><?=e($o['phone'])?></td><td><?=money($o['subtotal'])?></td><td><?=e($o['order_status'])?></td><td><?=e($o['created_at'])?></td></tr><?php endforeach;?></table></main></body></html>

<?php
require dirname(__DIR__,2).'/app/bootstrap.php';require_admin();
if(time()-($_SESSION['admin_last']??time())>1800){$_SESSION=[];session_destroy();redirect('login.php');}$_SESSION['admin_last']=time();
$products=db()->query("SELECT id,name,price,stock,is_active FROM products ORDER BY id DESC")->fetchAll();
?><!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>অ্যাডমিন</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="wrap"><h1>Zoynal E-bike — অ্যাডমিন</h1><div class="adminnav"><a class="btn" href="product-edit.php">নতুন পণ্য যোগ</a><a class="btn secondary" href="orders.php">অর্ডার দেখুন</a><a class="btn secondary" href="logout.php">লগআউট</a><a href="../index.php">শপ দেখুন</a></div><h2>পণ্য তালিকা</h2><table><tr><th>নাম</th><th>দাম</th><th>স্টক</th><th>অবস্থা</th><th>কাজ</th></tr><?php foreach($products as $p):?><tr><td><?=e($p['name'])?></td><td><?=money($p['price'])?></td><td><?=$p['stock']?></td><td><?=$p['is_active']?'সক্রিয়':'বন্ধ'?></td><td><a href="product-edit.php?id=<?=$p['id']?>">সম্পাদনা</a></td></tr><?php endforeach;?></table></main></body></html>

<?php
require dirname(__DIR__,2).'/app/bootstrap.php';require_admin();$id=(int)($_GET['id']??0);$p=['name'=>'','slug'=>'','description'=>'','price'=>'','stock'=>0,'image_url'=>'','is_active'=>1];if($id){$s=db()->prepare("SELECT * FROM products WHERE id=?");$s->execute([$id]);$p=$s->fetch()?:$p;}
$error='';if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();$name=trim($_POST['name']??'');$slug=strtolower(trim($_POST['slug']??''));$desc=trim($_POST['description']??'');$price=(float)($_POST['price']??0);$stock=(int)($_POST['stock']??0);$image=trim($_POST['image_url']??'');$active=isset($_POST['is_active'])?1:0;if(mb_strlen($name)<2||mb_strlen($name)>190||!preg_match('/^[a-z0-9-]{2,220}$/',$slug)||$price<0||$stock<0||mb_strlen($image)>1000||($image&&!filter_var($image,FILTER_VALIDATE_URL))){$error='তথ্য যাচাই করুন। Slug ইংরেজি ছোট অক্ষর/সংখ্যা/হাইফেন হবে।';}else{try{if($id){$s=db()->prepare("UPDATE products SET name=?,slug=?,description=?,price=?,stock=?,image_url=?,is_active=? WHERE id=?");$s->execute([$name,$slug,$desc,$price,$stock,$image?:null,$active,$id]);}else{$s=db()->prepare("INSERT INTO products(name,slug,description,price,stock,image_url,is_active) VALUES(?,?,?,?,?,?,?)");$s->execute([$name,$slug,$desc,$price,$stock,$image?:null,$active]);}redirect('index.php');}catch(Throwable $ex){$error='সংরক্ষণ হয়নি। একই slug আগে থেকে থাকতে পারে।';}}}
?><!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>পণ্য সম্পাদনা</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="wrap"><h1><?= $id?'পণ্য সম্পাদনা':'নতুন পণ্য'?></h1><?php if($error):?><p class="danger"><?=e($error)?></p><?php endif;?><form class="card" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>পণ্যের নাম</label><input name="name" maxlength="190" value="<?=e($p['name'])?>" required><label>Slug (যেমন e-bike-model-x)</label><input name="slug" maxlength="220" value="<?=e($p['slug'])?>" required><label>বিবরণ</label><textarea name="description"><?=e($p['description'])?></textarea><label>দাম (টাকা)</label><input type="number" step="0.01" min="0" name="price" value="<?=e((string)$p['price'])?>" required><label>স্টক</label><input type="number" min="0" name="stock" value="<?=e((string)$p['stock'])?>" required><label>ছবির URL (ঐচ্ছিক)</label><input type="url" name="image_url" value="<?=e($p['image_url']??'')?>"><label><input style="width:auto" type="checkbox" name="is_active" <?=$p['is_active']?'checked':''?>> পণ্য সক্রিয়</label><p><button>সংরক্ষণ</button> <a href="index.php">ফিরে যান</a></p></form></main></body></html>

<?php require dirname(__DIR__,2).'/app/bootstrap.php';$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);}session_destroy();redirect('login.php');

<?php
require dirname(__DIR__,2).'/app/bootstrap.php';
if(is_admin())redirect('index.php');$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();$email=trim($_POST['email']??'');$pass=$_POST['password']??'';$s=db()->prepare("SELECT id,password_hash FROM admins WHERE email=? LIMIT 1");$s->execute([$email]);$a=$s->fetch();if($a&&password_verify($pass,$a['password_hash'])){session_regenerate_id(true);$_SESSION['admin_id']=(int)$a['id'];$_SESSION['admin_last']=time();redirect('index.php');}$error='ইমেইল বা পাসওয়ার্ড সঠিক নয়।';}
?><!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>অ্যাডমিন লগইন</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="wrap"><div class="card" style="max-width:450px;margin:50px auto"><h1>অ্যাডমিন লগইন</h1><?php if($error):?><p class="danger"><?=e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>ইমেইল</label><input type="email" name="email" required><label>পাসওয়ার্ড</label><input type="password" name="password" required><button>লগইন</button></form><a href="../index.php">শপে ফিরে যান</a></div></main></body></html>

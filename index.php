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

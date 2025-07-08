<?php

// Comments START

//db()->query("CREATE TABLE IF NOT EXISTS `products` (
//  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
//  `title` varchar(255) NOT NULL,
//  `slug` varchar(255) DEFAULT NULL,
//  `category_id` int(10) UNSIGNED NOT NULL,
//  `price` int(10) UNSIGNED NOT NULL,
//  `old_price` int(10) UNSIGNED NOT NULL DEFAULT 0,
//  `excerpt` varchar(255) DEFAULT NULL,
//  `content` text NOT NULL,
//  `image` varchar(255) DEFAULT NULL,
//  `gallery` text DEFAULT NULL,
//  `is_sale` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
//  `in_stock` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
//  PRIMARY KEY (`id`),
//  UNIQUE KEY `slug` (`slug`),
//  KEY `category_id` (`category_id`)
//) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
//
//db()->query("ALTER TABLE `products`
//  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)");

// Comments END

db()->query("truncate table products");

$cnt = 1;
for ($j = 1; $j <= 21; $j++) {
    for ($i = 1; $i <= 10; $i++, $cnt++) {
        $gallery = [];
        for ($k = 0; $k < rand(0, 7); $k++) {
            $gallery[] = 'assets/img/products/' . ($k + 1) . '.jpg';
        }
        shuffle($gallery);

        db()->query("insert into products (title, slug, category_id, price, old_price, excerpt, content, image, gallery, is_sale, in_stock) values (?,?,?,?,?,?,?,?,?,?,?)", [
            "Test product $cnt",
            "test-product-$cnt",
            $j,
            $price = rand(50, 3000),
            rand(0, 1) ? intval($price * 1.1) : 0,
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
            '<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aspernatur, beatae dolorem doloremque error eveniet excepturi molestiae molestias nam numquam officia, officiis perspiciatis quaerat quia rem soluta velit voluptas voluptatem voluptatum.</p>
<p>Deleniti eius, ipsum iure molestiae nam repudiandae saepe unde! Atque fugit ipsam maiores, molestiae nemo quas vero? Consequuntur, culpa dolores, doloribus ea eum maiores officiis provident quidem quis sapiente voluptate!</p>
<p>Accusantium, dolore eos magnam maxime quibusdam ratione voluptates. A accusamus animi aperiam architecto commodi culpa cum cupiditate delectus distinctio eum fugiat hic magni nihil ratione sed, soluta unde vitae voluptatum?</p>',
            'assets/img/products/' . rand(1, 8) . '.jpg',
            $gallery ? json_encode($gallery) : null,
            rand(0, 1),
            rand(0, 1)
        ]);
    }
}
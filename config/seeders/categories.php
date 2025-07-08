<?php

// Comments START

//db()->query("CREATE TABLE IF NOT EXISTS `categories` (
//  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
//  `title` varchar(255) NOT NULL,
//  `slug` varchar(255) DEFAULT NULL,
//  `parent_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
//  `image` varchar(255) DEFAULT NULL,
//  PRIMARY KEY (`id`),
//  UNIQUE KEY `slug` (`slug`)
//) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Comments END

db()->query("set foreign_key_checks = 0");
db()->query("truncate table categories");
$categories = [
    ['title' => 'Женская обувь', 'slug' => 'zhenskaya-obuv-1', 'parent_id' => 0, 'image' => 'assets/img/categories/1.png'],
    ['title' => 'Мужская обувь', 'slug' => 'muzhskaya-obuv-2', 'parent_id' => 0, 'image' => 'assets/img/categories/2.png'],
    ['title' => 'Женская одежда', 'slug' => 'zhenskaya-odezhda-3', 'parent_id' => 0, 'image' => 'assets/img/categories/3.png'],
    ['title' => 'Мужская одежда', 'slug' => 'muzhskaya-odezhda-4', 'parent_id' => 0, 'image' => 'assets/img/categories/4.png'],
    ['title' => 'Туфли', 'slug' => 'tufli-5', 'parent_id' => 1, 'image' => null],
    ['title' => 'Сапоги', 'slug' => 'sapogi-6', 'parent_id' => 1, 'image' => null],
    ['title' => 'Кроссовки', 'slug' => 'krossovki-7', 'parent_id' => 1, 'image' => null],
    ['title' => 'Босоножки', 'slug' => 'bosonozhki-8', 'parent_id' => 1, 'image' => null],
    ['title' => 'Туфли', 'slug' => 'tufli-9', 'parent_id' => 2, 'image' => null],
    ['title' => 'Сапоги', 'slug' => 'sapogi-10', 'parent_id' => 2, 'image' => null],
    ['title' => 'Кроссовки', 'slug' => 'krossovki-11', 'parent_id' => 2, 'image' => null],
    ['title' => 'Куртки', 'slug' => 'kurtki-12', 'parent_id' => 3, 'image' => null],
    ['title' => 'Шубы', 'slug' => 'shuby-13', 'parent_id' => 3, 'image' => null],
    ['title' => 'Платья', 'slug' => 'platya-14', 'parent_id' => 3, 'image' => null],
    ['title' => 'Спортивные костюмы', 'slug' => 'sportivnye-kostyumy-15', 'parent_id' => 3, 'image' => null],
    ['title' => 'Джинсы', 'slug' => 'dzhinsy-16', 'parent_id' => 3, 'image' => null],
    ['title' => 'Куртки', 'slug' => 'kurtki-17', 'parent_id' => 4, 'image' => null],
    ['title' => 'Спортивные костюмы', 'slug' => 'sportivnye-kostyumy-18', 'parent_id' => 4, 'image' => null],
    ['title' => 'Костюмы', 'slug' => 'kostyumy-19', 'parent_id' => 4, 'image' => null],
    ['title' => 'Брюки', 'slug' => 'bryuki-20', 'parent_id' => 4, 'image' => null],
    ['title' => 'Футболки', 'slug' => 'futbolki-21', 'parent_id' => 4, 'image' => null],
];
foreach ($categories as $category) {
    db()->query("insert into `categories` (`title`, `slug`, `parent_id`, `image`) values (?,?,?,?)", [$category['title'], $category['slug'], $category['parent_id'], $category['image']]);
}
db()->query("set foreign_key_checks = 1");
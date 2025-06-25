<?php

define('ROOT', dirname(__DIR__));

const DEBUG = 1;
const WWW = ROOT . '/public';
const CONFIG = ROOT . '/config';
const HELPERS = ROOT . '/helpers';
const APP = ROOT . '/app';
const CORE = ROOT . '/core';
const VIEWS = APP . '/Views';
const ERROR_LOGS = ROOT . '/tmp/error.log';
const CACHE = ROOT . '/tmp/cache';
const LAYOUT = 'default';
const THEME = 'default';
const PATH = 'http://localhost:8888';
const UPLOADS = WWW . '/uploads';
const SITE_NAME = 'FBL';

const DB_SETTINGS = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'FBL',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'port' => 8889,
    'prefix' => '',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
];

const MAIL_SETTINGS = [
    'host' => 'sandbox.smtp.mailtrap.io', // smtp.gmail.com
    'auth' => true,
    'username' => '5a8227c0fb4058', // your_email@gmail.com
    'password' => '4ca21db6c36d9a', // xxxx xxxx xxxx xxxx
    'secure' => 'tls', // ssl
    'port' => 587,
    'from_email' => '809dd70a9c-b1e56f@inbox.mailtrap.io', // your_email@gmail.com
    'from_name' => 'FBL',
    'is_html' => true,
    'charset' => 'UTF-8',
    'debug' => 0, // 0 - 4
];

const PAGINATION_SETTINGS = [
    'perPage' => 5,
    'midSize' => 2,
    'maxPages' => 7,
    'tpl' => 'pagination/base',
];

const MULTILANGS = 1;
const LANGS = [
    'ru' => [
        'id' => 1,
        'code' => 'ru',
        'title' => 'Русский',
        'base' => 1
    ],
    'en' => [
        'id' => 2,
        'code' => 'en',
        'title' => 'English',
        'base' => 0
    ],
//    'de' => [
//        'id' => 3,
//        'code' => 'de',
//        'title' => 'Deutsch',
//        'base' => 0
//    ],
];

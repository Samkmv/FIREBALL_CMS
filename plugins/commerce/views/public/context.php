<?php
$t = static fn(string $key): string => htmlSC(FireballPluginCommerce::t($key));
$url = static fn(string $path): string => htmlSC(base_href($path));
$media = static fn(string $path): string => htmlSC(base_url($path));
$money = static fn(int $minor): string => htmlSC(\Fireball\Commerce\Input::money($minor));

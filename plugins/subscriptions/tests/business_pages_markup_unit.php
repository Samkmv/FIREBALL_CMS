<?php
declare(strict_types=1);
$checks=0;
foreach (['ru','en','de','zh-cn'] as $locale) {
    foreach (['public','guest','manage','locked','admin','owner-overview','owner-posts','owner-camera','owner-statistics','owner-gallery'] as $mode) {
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixtures/business-page.php').' '.escapeshellarg($mode).' '.escapeshellarg($locale);
        $html=shell_exec($command);
        if (!$html || str_contains($html,'Warning:') || str_contains($html,'Fatal error:')) { throw new RuntimeException('Fixture failed '.$mode.'/'.$locale); }
        $dom=new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML($html); libxml_clear_errors();
        $xpath=new DOMXPath($dom);
        if ($xpath->query('//script')->length!==0) { throw new RuntimeException('Stored HTML must be escaped'); }
        foreach ($xpath->query('//form') as $form) {
            if ($xpath->query('.//input[@name="csrf_token"]',$form)->length!==1) { throw new RuntimeException('Every form needs CSRF'); }
        }
        $ids=[];
        foreach ($xpath->query('//*[@id]') as $node) { $id=$node->getAttribute('id'); if (isset($ids[$id])) { throw new RuntimeException('Duplicate id '.$id); } $ids[$id]=true; }
        if ($mode==='guest' && $xpath->query('//select[@name="rating"]')->length!==0) { throw new RuntimeException('Guest cannot rate'); }
        if ($mode==='locked' && $xpath->query('//form')->length!==0) { throw new RuntimeException('Inactive subscriber cannot edit'); }
        if ($mode==='manage' && $xpath->query('//input[@type="file"]')->length!==2) { throw new RuntimeException('Missing uploads'); }
        if ($mode==='manage' && $xpath->query('//input[@id="business-slug" and @readonly and not(@name)]')->length!==1) { throw new RuntimeException('Slug must be read-only'); }
        if ($mode==='public' && $xpath->query('//*[@data-fire-player]')->length!==1) { throw new RuntimeException('Assigned camera is missing'); }
        if ($mode==='owner-overview') {
            if ($xpath->query('//*[@class="business-dashboard-middle"]')->length!==1 || $xpath->query('//*[@class="business-dashboard-bottom"]')->length!==1) { throw new RuntimeException('Missing dashboard cards'); }
            if ($xpath->query('//input[@type="file"]')->length!==0) { throw new RuntimeException('Overview should lead to focused editors'); }
            if ($xpath->query('//a[@aria-current="page"]')->length!==1) { throw new RuntimeException('Navigation must highlight active section'); }
        }
        if ($mode==='owner-camera' && $xpath->query('//input[@name="action" and @value="camera"]')->length!==1) { throw new RuntimeException('Dedicated camera settings missing'); }
        if ($mode==='owner-posts' && $xpath->query('//details')->length!==3) { throw new RuntimeException('Publication editors missing'); }
        if ($mode==='admin' && ($xpath->query('//input[@name="camera_url"]')->length!==1 || $xpath->query('//input[@name="camera_poster"]')->length!==1)) { throw new RuntimeException('Admin stream/poster inputs missing'); }
        $checks++;
    }
}
echo "Business markup tests passed: {$checks} template/locale scenarios.\n";

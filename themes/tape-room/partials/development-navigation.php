<?php $developmentSection=$section ?? 'ui'; ?>
<nav class="nav nav-pills tr-development-nav" aria-label="Документация Tape Room">
    <a class="nav-link <?= $developmentSection==='ui'?'active':'' ?>" href="<?= htmlSC(base_href('/tape-room/ui-kit')) ?>" <?= $developmentSection==='ui'?'aria-current="page"':'' ?>><?= $this->partial('site-icon',['name'=>'grid']) ?><span>UI-компоненты</span></a>
    <a class="nav-link <?= $developmentSection==='changelog'?'active':'' ?>" href="<?= htmlSC(base_href('/tape-room/changelog')) ?>" <?= $developmentSection==='changelog'?'aria-current="page"':'' ?>><?= $this->partial('site-icon',['name'=>'changelog']) ?><span>Change Log</span></a>
</nav>

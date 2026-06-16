<?php
/**
 * Search Template
 *
 * Available variables: $query, $results, $total, $pagination.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1>Search</h1>
        <form action="<?= base_href('/search') ?>" method="get">
            <input type="search" name="q" value="<?= htmlSC($query ?? '') ?>">
            <button type="submit">Search</button>
        </form>
        <?php foreach ($results ?? [] as $result): ?>
            <article>
                <h2><a href="<?= htmlSC($result['url']) ?>"><?= htmlSC($result['title']) ?></a></h2>
                <p><?= htmlSC($result['excerpt'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
        <?= $pagination ?? '' ?>
    </div>
</section>
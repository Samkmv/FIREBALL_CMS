<?php
/**
 * Search Template
 *
 * Available variables:
 * $query
 * $results
 * $total
 * $pagination
 */
?>
<main class="container py-5">
    <h1 class="h2 mb-4"><?= print_translation('search_index_title') ?></h1>

    <form class="input-group mb-5" action="<?= base_href('/search') ?>" method="get">
        <input class="form-control" type="search" name="q" value="<?= htmlSC($query ?? '') ?>">
        <button class="btn btn-dark" type="submit"><?= print_translation('search_index_title') ?></button>
    </form>

    <?php if (!empty($results)): ?>
        <div class="vstack gap-4">
            <?php foreach ($results as $result): ?>
                <article class="border-bottom pb-4">
                    <div class="text-body-secondary small text-uppercase"><?= htmlSC($result['type']) ?></div>
                    <h2 class="h5 mb-2">
                        <a href="<?= htmlSC($result['url']) ?>"><?= htmlSC($result['title']) ?></a>
                    </h2>
                    <p class="mb-0"><?= htmlSC($result['excerpt'] ?? '') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php elseif (($query ?? '') !== ''): ?>
        <p class="text-body-secondary"><?= print_translation('search_suggest_empty') ?></p>
    <?php endif; ?>

    <?php if (!empty($pagination)): ?>
        <div class="mt-5"><?= $pagination ?></div>
    <?php endif; ?>
</main>

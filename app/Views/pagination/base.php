<nav aria-label="Page navigation example">
    <ul class="pagination">

        <?php if (!empty($first_page)): ?>
            <li class="page-item">
                <a class="page-link" href="<?= $first_page; ?>" aria-label="First page">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (!empty($back)): ?>
            <li class="page-item">
                <a class="page-link" href="<?= $back; ?>" aria-label="Previous page">
                    <span aria-hidden="true">&lt;</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (!empty($pages_left)): ?>
            <?php foreach ($pages_left as $page_left): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $page_left['link']; ?>">
                        <?= $page_left['number']; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>

        <li class="page-item active"><a class="page-link"><?= $current_page; ?></a></li>

        <?php if (!empty($pages_right)): ?>
            <?php foreach ($pages_right as $page_right): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $page_right['link']; ?>">
                        <?= $page_right['number']; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($forward)): ?>
            <li class="page-item">
                <a class="page-link" href="<?= $forward; ?>" aria-label="Next page">
                    <span aria-hidden="true">&gt;</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (!empty($last_page)): ?>
            <li class="page-item">
                <a class="page-link" href="<?= $last_page; ?>" aria-label="Last page">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        <?php endif; ?>

    </ul>
</nav>
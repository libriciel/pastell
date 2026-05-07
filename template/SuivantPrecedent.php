<?php
/**
 * @var string $link
 * @var string $message
 * @var int $offset
 * @var int $limit
 * @var int $nb_total
 */

?>
<nav class="w-100">
    <ul class="pagination justify-content-center ">
        <li class="page-item">
            <?php if ($offset) : ?>
                <a
                        href="<?php hecho($link); ?>offset=<?php echo max(0, $offset - $limit); ?>"
                        class="page-link btn-link"
                >
                    <i class="fas fa-chevron-left"></i>&nbsp;<?php echo "Page précédente"; ?>
                </a>
            <?php else : ?>
                &nbsp;
            <?php endif; ?>
        </li>
        <li class="page-item">
            <a class="page-link disabled  btn-link">
                <?php echo sprintf($message, ($offset + 1), min($offset + $limit, $nb_total), $nb_total); ?>
            </a>
        </li>
        <li class="page-item">
            <?php if (($offset + $limit) < $nb_total) : ?>
                <a
                        href="<?php hecho($link); ?>offset=<?php echo $offset + $limit ?>"
                        class="page-link btn-link"
                >
                    <?php echo "Page suivante"; ?>&nbsp;<i class="fas fa-chevron-right"></i>
                </a>
            <?php else : ?>
                &nbsp;
            <?php endif; ?>
        </li>
    </ul>
</nav>

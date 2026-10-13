<?php

declare(strict_types=1);

use function Chores\e;

/**
 * Message de confirmation en page (spec § 12) : role="status", annoncé poliment, repère vert et texte écrit.
 * @var string $texte
 * @var ?string $id
 */
$id ??= null;
?>
<p class="confirmation" role="status"<?= $id !== null ? ' id="' . e($id) . '" tabindex="-1"' : '' ?>><?= e($texte) ?></p>

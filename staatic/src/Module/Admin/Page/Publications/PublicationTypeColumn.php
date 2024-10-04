<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Admin\Page\Publications;

use Staatic\WordPress\ListTable\Column\AbstractColumn;
use Staatic\WordPress\Publication\Publication;

final class PublicationTypeColumn extends AbstractColumn
{
    /**
     * @param Publication $publication
     */
    public function render($publication): void
    {
        $result = $publication->type()->label();
        echo $this->applyDecorators($result, $publication);
    }

    public function defaultSortColumn(): ?string
    {
        return null;
    }
}

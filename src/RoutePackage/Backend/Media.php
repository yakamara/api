<?php

namespace Yakamara\Api\RoutePackage\Backend;

use Override;

class Media extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'media/';
    }
}

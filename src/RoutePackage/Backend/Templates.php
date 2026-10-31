<?php

namespace Yakamara\Api\RoutePackage\Backend;

use Override;

class Templates extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'templates/';
    }
}

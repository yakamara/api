<?php

namespace Yakamara\Api\RoutePackage\Backend;

use Override;

class Users extends AbstractMirror
{
    #[Override]
    protected function scopePrefix(): ?string
    {
        return 'users/';
    }
}

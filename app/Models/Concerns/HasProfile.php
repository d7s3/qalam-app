<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasProfile
{
    /**
     * Get the user's initials.
     */
    public function initials(): string
    {
        return Str::upper(mb_substr($this->name, 0, 2));
    }

    /**
     * Get unique avatar inline style (background and text color).
     */
    public function avatarStyle(): string
    {
        $hue = ($this->id * 137) % 360;

        return "background-color: hsl({$hue}, 75%, 93%); color: hsl({$hue}, 80%, 28%);";
    }
}

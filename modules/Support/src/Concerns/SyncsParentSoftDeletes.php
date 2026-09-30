<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SyncsParentSoftDeletes
{
    protected static function bootSyncsParentSoftDeletes(): void
    {
        static::deleting(function (Model $model): void {
            $parent = $model->getParentForSync();

            if (! $parent) {
                return;
            }

            if ($model->isForceDeleting()) {
                $parent->forceDelete();

                return;
            }

            $parent->delete();
        });

        static::restoring(function (Model $model): void {
            $parent = $model->getParentForSync();

            if (! $parent) {
                return;
            }

            $parent->restore();
        });
    }

    abstract protected function getParentForSync(): ?Model;
}

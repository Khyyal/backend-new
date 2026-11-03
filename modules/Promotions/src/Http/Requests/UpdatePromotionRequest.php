<?php

namespace Modules\Promotions\Http\Requests;

use Modules\Promotions\Models\Promotion;

class UpdatePromotionRequest extends StorePromotionRequest
{
    protected bool $isUpdate = true;

    protected function currentPromotion(): ?Promotion
    {
        $promotion = $this->route('promotion');

        return $promotion instanceof Promotion ? $promotion : null;
    }
}
